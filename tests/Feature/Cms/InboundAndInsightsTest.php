<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Cms\Models\Gallery;
use App\Domains\Cms\Models\Page;
use App\Domains\Cms\Models\Post;
use App\Domains\Leads\Models\Enquiry;
use App\Domains\Leads\Models\Lead;
use App\Domains\Leads\Notifications\EnquiryReplyMail;
use App\Domains\Leads\Notifications\OfficeAlert;
use App\Domains\Settings\Settings\GeneralSettings;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CmsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class, CmsSeeder::class]);
    $this->editor = User::factory()->staff(SystemRole::ContentEditor)->create();
    $this->manager = User::factory()->staff(SystemRole::ListingsManager)->create(); // leads.view + leads.manage + enquiries.view
    $this->support = User::factory()->staff(SystemRole::Support)->create();        // enquiries.respond
});

function leadForm(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sizwe Mthembu', 'email' => 'Sizwe@Example.com', 'mobile' => '0821234567', 'property_type' => 'mining',
        'location' => 'Emalahleni', 'asking_price' => 5000000, 'description' => 'Coal prospecting right, 640 ha.', 'consent' => '1',
    ], $overrides);
}

it('stores a Sell with us lead with private photos and alerts the office', function () {
    $this->post('/sell-with-us', leadForm(['photos' => [UploadedFile::fake()->image('site.jpg', 800, 600)]]))->assertSessionHasNoErrors();

    $lead = Lead::firstOrFail();
    expect($lead->email)->toBe('sizwe@example.com')->and($lead->mobile)->toBe('+27821234567')->and($lead->status)->toBe('new');

    $photo = $lead->getFirstMedia('photos');
    expect($photo->disk)->toBe('local');
    Storage::disk('public')->assertMissing($photo->getPathRelativeToRoot());

    Notification::assertSentTo(new AnonymousNotifiable, OfficeAlert::class,
        fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === app(GeneralSettings::class)->contact_email);
});

it('requires consent and a South African mobile number', function () {
    $this->post('/sell-with-us', leadForm(['consent' => null, 'mobile' => '12345']))->assertSessionHasErrors(['consent', 'mobile']);
    expect(Lead::count())->toBe(0);
});

it('silently discards submissions that fill the honeypot', function () {
    $this->post('/sell-with-us', leadForm(['website' => 'http://spam.example']))->assertSessionHasNoErrors();
    $this->post('/enquiries', ['name' => 'x', 'email' => 'x@example.com', 'subject' => 'x', 'message' => 'x', 'website' => 'spam']);

    expect(Lead::count())->toBe(0)->and(Enquiry::count())->toBe(0);
    Notification::assertNothingSent();
});

it('limits public forms to 5 submissions an hour per address', function () {
    RateLimiter::clear('public-forms');
    foreach (range(1, 5) as $i) {
        $this->post('/enquiries', ['name' => 'A', 'email' => "a{$i}@example.com", 'subject' => 'Hi', 'message' => 'Hello'])->assertSessionHasNoErrors();
    }

    $this->post('/enquiries', ['name' => 'A', 'email' => 'a6@example.com', 'subject' => 'Hi', 'message' => 'Hello'])->assertStatus(429);
});

it('lets staff work a lead, keeping notes and photos private to permitted staff', function () {
    $this->post('/sell-with-us', leadForm(['photos' => [UploadedFile::fake()->image('site.jpg')]]));
    $lead = Lead::firstOrFail();
    $photo = $lead->getFirstMedia('photos');

    $this->actingAs($this->manager)->put(route('admin.leads.update', $lead), ['status' => 'contacted', 'note' => 'Called; valuation next week.'])->assertSessionHasNoErrors();
    expect($lead->fresh()->status)->toBe('contacted')->and($lead->notes()->count())->toBe(1);

    $this->actingAs($this->manager)->get(route('admin.leads.photo', [$lead, $photo]))->assertOk();
    $this->actingAs($this->editor)->get(route('admin.leads.photo', [$lead, $photo]))->assertForbidden();
    $this->actingAs($this->editor)->get(route('admin.leads.index'))->assertForbidden();
});

it('answers an enquiry by email and records the reply', function () {
    $this->post('/enquiries', ['name' => 'Ayesha', 'email' => 'ayesha@example.com', 'subject' => 'Viewing in Midrand', 'message' => 'Can I view on Saturday?']);
    $enquiry = Enquiry::firstOrFail();

    $this->actingAs($this->manager)->post(route('admin.enquiries.reply', $enquiry), ['body' => 'Yes, 10:00 suits.'])->assertForbidden();
    $this->actingAs($this->support)->post(route('admin.enquiries.reply', $enquiry), ['body' => 'Yes, 10:00 suits.'])->assertSessionHasNoErrors();

    expect($enquiry->fresh()->status)->toBe('answered')->and($enquiry->replies()->count())->toBe(1);
    Notification::assertSentTo(new AnonymousNotifiable, EnquiryReplyMail::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'ayesha@example.com');
    expect(AuditEntry::where('log_name', 'enquiries')->where('event', 'replied')->exists())->toBeTrue();
});

it('shows the contact form on the contact page', function () {
    $this->get('/contact')->assertInertia(fn (Assert $p) => $p->component('public/page')->where('page.slug', 'contact'));
});

it('publishes, schedules and hides articles', function () {
    $this->actingAs($this->editor)->post(route('admin.cms.posts.store'), ['title' => 'How mining right transfers work']);
    $post = Post::firstOrFail();
    $save = fn (array $o) => $this->actingAs($this->editor)->post(route('admin.cms.posts.update', $post), array_merge([
        'title' => $post->title, 'slug' => $post->slug, 'topic' => 'Mining', 'excerpt' => 'Section 11 explained.',
        'body' => '<p>Text<script>x</script></p>', 'status' => 'published', 'published_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ], $o));

    $save([])->assertSessionHasNoErrors();
    $this->get("/insights/{$post->slug}")->assertNotFound();
    $this->get('/insights')->assertInertia(fn (Assert $p) => $p->has('posts.data', 0));

    $save(['published_at' => now()->subHour()->setTimezone('Africa/Johannesburg')->format('Y-m-d\TH:i'), 'cover' => UploadedFile::fake()->image('c.jpg')]);
    expect($post->fresh()->body)->not->toContain('script');

    $html = $this->get("/insights/{$post->slug}")->assertOk()->getContent();
    expect($html)->toContain('BlogPosting');
    $this->get('/insights')->assertInertia(fn (Assert $p) => $p->has('posts.data', 1));

    $save(['status' => 'draft']);
    $this->get("/insights/{$post->slug}")->assertNotFound();
});

it('shows gallery albums that have images', function () {
    $this->actingAs($this->editor)->post(route('admin.cms.galleries.store'), ['title' => 'Viewing day', 'is_visible' => true]);
    $gallery = Gallery::firstOrFail();
    $this->get('/gallery')->assertInertia(fn (Assert $p) => $p->has('albums', 0));

    $this->actingAs($this->editor)->post(route('admin.cms.galleries.images.store', $gallery), ['files' => [UploadedFile::fake()->image('a.jpg')]]);
    $media = $gallery->fresh()->getFirstMedia('images');
    $this->actingAs($this->editor)->put(route('admin.cms.galleries.images.update', [$gallery, $media]), ['alt' => '', 'caption' => 'x'])->assertSessionHasErrors('alt');
    $this->actingAs($this->editor)->put(route('admin.cms.galleries.images.update', [$gallery, $media]), ['alt' => 'Buyers at the viewing', 'caption' => 'Midrand']);

    $this->get('/gallery')->assertInertia(fn (Assert $p) => $p->has('albums', 1)->where('albums.0.images.0.alt', 'Buyers at the viewing'));
});

it('only allows page images uploaded to that page, with alt text', function () {
    $about = Page::where('slug', 'about')->firstOrFail();
    $fees = Page::where('slug', 'fees')->firstOrFail();

    $id = $this->actingAs($this->editor)->post(route('admin.cms.pages.images', $fees), ['file' => UploadedFile::fake()->image('x.jpg')])->json('id');
    $save = fn (Page $page, array $block) => $this->actingAs($this->editor)->put(route('admin.cms.pages.update', $page), [
        'title' => $page->title, 'slug' => $page->slug, 'status' => 'published', 'blocks' => [$block],
    ]);

    $save($about, ['type' => 'image', 'media_id' => $id, 'alt' => 'Office'])->assertSessionHasErrors('blocks.0');
    $save($fees, ['type' => 'image', 'media_id' => $id, 'alt' => ''])->assertSessionHasErrors('blocks.0.alt');
    $save($fees, ['type' => 'image', 'media_id' => $id, 'alt' => 'Fee table'])->assertSessionHasNoErrors();

    $this->get('/fees')->assertInertia(fn (Assert $p) => $p->where('page.blocks.0.alt', 'Fee table')->where('page.blocks.0.url', fn ($u) => is_string($u)));
});
