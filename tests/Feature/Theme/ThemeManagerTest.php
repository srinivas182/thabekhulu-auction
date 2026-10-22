<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Theme\Models\ThemeVersion;
use App\Domains\Theme\Support\ThemePalette;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function themeInput(array $light = [], array $extra = []): array
{
    $d = ThemePalette::defaults();

    return array_merge([
        'light' => array_merge($d['light'], $light),
        'dark' => $d['dark'],
        'fonts' => 'thabekhulu',
        'radius' => 'soft',
    ], $extra);
}

function themeCss(string $html): string
{
    preg_match('/<style[^>]*id="theme">(.*?)<\/style>/s', $html, $m);

    return $m[1] ?? '';
}

it('shows the theme manager for each area', function (string $scope) {
    $this->actingAs($this->admin)->get(route('admin.theme.edit', $scope))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/theme/edit')->where('scope', $scope)->has('checks', 40));
})->with(['website', 'admin']);

it('requires the theme permission', function () {
    $finance = User::factory()->staff(SystemRole::Finance)->create();

    $this->actingAs($finance)->get(route('admin.theme.edit', 'website'))->assertForbidden();
    $this->actingAs($finance)->post(route('admin.theme.draft.save', 'website'), themeInput())->assertForbidden();
});

it('keeps a draft off the live site until it is published', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.theme.draft.save', 'website'), themeInput(['primary' => '#1f4e79']))
        ->assertSessionHasNoErrors();

    expect(themeCss($this->get(route('home'))->getContent()))->toContain('--primary:#173b33')->not->toContain('#1f4e79');

    $this->actingAs($this->admin)->post(route('admin.theme.publish', 'website'))->assertSessionHasNoErrors();

    expect(themeCss($this->get(route('home'))->getContent()))->toContain('--primary:#1f4e79')
        ->and(AuditEntry::where('log_name', 'theme')->where('event', 'published')->exists())->toBeTrue();
});

it('blocks colours that are hard to read', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.theme.draft.save', 'website'), themeInput(['muted_text' => '#cfd3d0']))
        ->assertSessionHasErrors('contrast');

    expect(ThemeVersion::count())->toBe(0);
});

it('blocks a background that makes the fixed status colours unreadable', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.theme.draft.save', 'website'), themeInput(['surface' => '#8a9a90', 'background' => '#f2f3ee']))
        ->assertSessionHasErrors('contrast');
});

it('rejects colours that are not hex and ignores keys it does not know', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.theme.draft.save', 'website'), themeInput(['primary' => 'red']))
        ->assertSessionHasErrors('light.primary');

    $this->actingAs($this->admin)
        ->post(route('admin.theme.draft.save', 'website'), themeInput(['status_live' => '#ff0000']));

    expect(ThemeVersion::firstOrFail()->values['light'])->not->toHaveKey('status_live');
});

it('themes the website and back office separately', function () {
    $this->actingAs($this->admin)->post(route('admin.theme.draft.save', 'admin'), themeInput(['primary' => '#1f4e79']));
    $this->actingAs($this->admin)->post(route('admin.theme.publish', 'admin'));

    expect(themeCss($this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent()))->toContain('--primary:#1f4e79');
    expect(themeCss($this->get(route('home'))->getContent()))->toContain('--primary:#173b33');
});

it('applies the chosen font pairing and corner style', function () {
    $this->actingAs($this->admin)->post(route('admin.theme.draft.save', 'website'), themeInput([], ['fonts' => 'classic', 'radius' => 'round']));
    $this->actingAs($this->admin)->post(route('admin.theme.publish', 'website'));

    expect(themeCss($this->get(route('home'))->getContent()))
        ->toContain("--font-display:'Playfair Display'")
        ->toContain("--font-sans:'Source Sans 3'")
        ->toContain('--radius:1rem');
});

it('restores an earlier version and resets to the brand', function () {
    foreach (['#1f4e79', '#3b2f6b'] as $primary) {
        $this->actingAs($this->admin)->post(route('admin.theme.draft.save', 'website'), themeInput(['primary' => $primary]));
        $this->actingAs($this->admin)->post(route('admin.theme.publish', 'website'));
    }
    $first = ThemeVersion::where('values->light->primary', '#1f4e79')->firstOrFail();

    $this->actingAs($this->admin)->post(route('admin.theme.restore', ['scope' => 'website', 'version' => $first->id]))->assertSessionHasNoErrors();
    expect(themeCss($this->get(route('home'))->getContent()))->toContain('--primary:#1f4e79');

    $this->actingAs($this->admin)->post(route('admin.theme.reset', 'website'));
    expect(themeCss($this->get(route('home'))->getContent()))->toContain('--primary:#173b33')
        ->and(ThemeVersion::where('status', 'published')->count())->toBe(1);
});

it('discards a draft', function () {
    $this->actingAs($this->admin)->post(route('admin.theme.draft.save', 'website'), themeInput(['primary' => '#1f4e79']));
    $this->actingAs($this->admin)->delete(route('admin.theme.draft.discard', 'website'));

    expect(ThemeVersion::where('status', 'draft')->exists())->toBeFalse();
});

it('sanitises uploaded SVG logos', function () {
    Storage::fake('public');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10"/></svg>';

    $this->actingAs($this->admin)->post(route('admin.theme.draft.save', 'website'), themeInput([], [
        'logo_light' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
    ]))->assertSessionHasNoErrors();

    $path = ThemeVersion::firstOrFail()->values['logo_light'];
    $stored = Storage::disk('public')->get($path);

    expect($stored)->toContain('<rect')->not->toContain('<script')->not->toContain('onload');
});

it('resizes favicons and uses them once published', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('admin.theme.draft.save', 'website'), themeInput([], [
        'favicon' => UploadedFile::fake()->image('icon.png', 400, 300),
    ]))->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.theme.publish', 'website'));

    $values = ThemeVersion::where('status', 'published')->firstOrFail()->values;
    expect(getimagesizefromstring(Storage::disk('public')->get($values['favicon']))[0])->toBe(32)
        ->and(getimagesizefromstring(Storage::disk('public')->get($values['favicon_apple']))[0])->toBe(180);

    $this->get(route('home'))->assertSee(Storage::disk('public')->url($values['favicon']), escape: false);
});

it('rejects oversized or unsupported files', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('admin.theme.draft.save', 'website'), themeInput([], [
        'logo_light' => UploadedFile::fake()->create('logo.png', 2048, 'image/png'),
    ]))->assertSessionHasErrors('logo_light');

    $this->actingAs($this->admin)->post(route('admin.theme.draft.save', 'website'), themeInput([], [
        'favicon' => UploadedFile::fake()->create('icon.gif', 10, 'image/gif'),
    ]))->assertSessionHasErrors('favicon');
});
