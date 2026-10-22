<?php

namespace App\Domains\Cms\Http\Controllers\Admin;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\CategoryAttribute;
use App\Domains\Cms\Models\Collection;
use App\Domains\Cms\Models\MenuItem;
use App\Domains\Cms\Models\Page;
use App\Domains\Cms\Settings\HomeSettings;
use App\Domains\Cms\Support\Blocks;
use App\Domains\Cms\Support\SiteNavigation;
use App\Domains\Listings\Models\Listing;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Website content: pages, menus, investment collections and the home page.
 * Requires cms.manage; every change is audit-logged.
 */
class CmsController extends Controller
{
    /** Page addresses that would clash with the application's own routes. */
    public const RESERVED = ['admin', 'auctions', 'login', 'logout', 'register', 'dashboard', 'account', 'settings', 'invitations',
        'sitemap', 'storage', 'up', 'horizon', 'two-factor-challenge', 'forgot-password', 'reset-password', 'email', 'user',
        'build', 'password', 'insights', 'gallery', 'sell-with-us', 'api', 'passkeys', 'confirm-password', 'verify-email'];

    private const LINK = '#^(/[A-Za-z0-9/_\-?=&.%]*|https://\S+|mailto:\S+|tel:[+0-9 ]+)$#';

    public function pages(): Response
    {
        return Inertia::render('admin/cms/pages', [
            'pages' => Page::orderBy('title')->get()->map(fn (Page $p): array => [
                'id' => $p->id, 'title' => $p->title, 'slug' => $p->slug, 'status' => $p->status,
                'system' => $p->is_system, 'needsContent' => $p->needs_content, 'updatedAt' => $p->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storePage(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $title = $request->validate(['title' => ['required', 'string', 'max:160']])['title'];
        $page = Page::create(['title' => $title, 'slug' => $this->uniqueSlug($title), 'status' => 'draft', 'blocks' => [], 'updated_by' => $actor->id]);

        Audit::record('cms', 'page_created', "Created page {$page->title}", $page, $actor);

        return redirect()->route('admin.cms.pages.edit', $page);
    }

    public function editPage(Page $page): Response
    {
        return Inertia::render('admin/cms/page-edit', [
            'page' => [
                'id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'status' => $page->status, 'blocks' => $page->blocks,
                'system' => $page->is_system, 'needsContent' => $page->needs_content,
                'seoTitle' => $page->seo_title, 'seoDescription' => $page->seo_description,
            ],
            'blockTypes' => Blocks::TYPES,
        ]);
    }

    public function updatePage(Request $request, Page $page, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::notIn(self::RESERVED), Rule::unique('pages', 'slug')->ignore($page->id)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'blocks' => ['present', 'array', 'max:40'],
            'needs_content' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:300'],
        ], ['slug.not_in' => __('That address is used by the platform. Choose another.')]);

        $wasPublished = $page->status === 'published';
        $page->fill([
            'title' => $data['title'], 'slug' => $page->is_system ? $page->slug : $data['slug'], 'status' => $data['status'],
            'blocks' => Blocks::clean($data['blocks']), 'needs_content' => (bool) ($data['needs_content'] ?? false),
            'seo_title' => $data['seo_title'] ?? null, 'seo_description' => $data['seo_description'] ?? null, 'updated_by' => $actor->id,
        ]);
        if (! $wasPublished && $page->status === 'published') {
            $page->published_at = Carbon::now();
        }
        $page->save();

        Audit::record('cms', $page->status === 'published' ? 'page_published' : 'page_saved', "Saved page {$page->title}", $page, $actor, ['status' => $page->status]);
        $this->toast('Page saved.');

        return back();
    }

    public function destroyPage(Page $page, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_if($page->is_system, 403, __('Standard pages can be edited or unpublished but not deleted.'));

        $title = $page->title;
        $page->delete();
        Audit::record('cms', 'page_deleted', "Deleted page {$title}", null, $actor);

        return redirect()->route('admin.cms.pages');
    }

    public function menus(): Response
    {
        $menu = fn (string $name) => MenuItem::where('menu', $name)->orderBy('sort_order')->get(['label', 'url', 'new_tab'])
            ->map(fn (MenuItem $i): array => ['label' => $i->label, 'url' => $i->url, 'newTab' => $i->new_tab]);

        return Inertia::render('admin/cms/menus', [
            'header' => $menu('header'),
            'footer' => $menu('footer'),
            'suggestions' => Page::where('status', 'published')->orderBy('title')->get(['title', 'slug'])->map(fn (Page $p) => ['label' => $p->title, 'url' => "/{$p->slug}"])
                ->merge(Category::where('is_visible', true)->orderBy('sort_order')->get(['name', 'slug'])->map(fn (Category $c) => ['label' => "{$c->name} auctions", 'url' => "/auctions/{$c->slug}"]))
                ->prepend(['label' => 'All auctions', 'url' => '/auctions'])->values(),
        ]);
    }

    public function updateMenu(Request $request, string $menu, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless(in_array($menu, ['header', 'footer'], true), 404);
        $items = $request->validate([
            'items' => ['present', 'array', 'max:20'],
            'items.*.label' => ['required', 'string', 'max:60'],
            'items.*.url' => ['required', 'string', 'max:255', 'regex:'.self::LINK],
            'items.*.newTab' => ['boolean'],
        ], ['items.*.url.regex' => __('Use a page path like /about or a full https:// link.')])['items'];

        DB::transaction(function () use ($menu, $items): void {
            MenuItem::where('menu', $menu)->delete();
            foreach (array_values($items) as $i => $item) {
                MenuItem::create(['menu' => $menu, 'label' => $item['label'], 'url' => $item['url'], 'new_tab' => (bool) ($item['newTab'] ?? false), 'sort_order' => $i]);
            }
        });
        SiteNavigation::forget();

        Audit::record('cms', 'menu_updated', "Updated the {$menu} menu", null, $actor, ['attributes' => ['items' => array_column($items, 'label')]]);
        $this->toast('Menu saved.');

        return back();
    }

    public function collections(): Response
    {
        return Inertia::render('admin/cms/collections', [
            'collections' => Collection::with(['category:id,name', 'attribute:id,label'])->orderBy('sort_order')->get()->map(fn (Collection $c): array => [
                'id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'categoryId' => $c->category_id,
                'attributeId' => $c->category_attribute_id, 'attributeValue' => $c->attribute_value, 'renovatedOnly' => $c->renovated_only,
                'visible' => $c->is_visible, 'count' => $c->listings()->count(),
            ]),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'attributes' => CategoryAttribute::where('type', 'select')->orderBy('label')->get(['id', 'category_id', 'label', 'options']),
        ]);
    }

    public function saveCollection(Request $request, #[CurrentUser] User $actor, ?Collection $collection = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_attribute_id' => ['nullable', 'integer', 'exists:category_attributes,id'],
            'attribute_value' => ['nullable', 'required_with:category_attribute_id', 'string', 'max:120'],
            'renovated_only' => ['boolean'],
            'is_visible' => ['boolean'],
        ]);

        $collection ??= new Collection(['slug' => $this->uniqueCollectionSlug($data['name']), 'sort_order' => (int) Collection::max('sort_order') + 1]);
        $collection->fill($data)->save();

        Audit::record('cms', 'collection_saved', "Saved collection {$collection->name}", $collection, $actor);
        $this->toast('Collection saved.');

        return back();
    }

    public function destroyCollection(Collection $collection, #[CurrentUser] User $actor): RedirectResponse
    {
        $name = $collection->name;
        $collection->delete();
        Audit::record('cms', 'collection_deleted', "Deleted collection {$name}", null, $actor);

        return back();
    }

    public function home(HomeSettings $home): Response
    {
        return Inertia::render('admin/cms/home', [
            'home' => $home->toArray(),
            'listings' => Listing::public()->orderBy('title')->get(['ulid', 'title'])->map(fn (Listing $l) => ['ulid' => $l->ulid, 'title' => $l->title]),
        ]);
    }

    public function updateHome(Request $request, HomeSettings $home, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'hero_title' => ['required', 'string', 'max:120'],
            'hero_text' => ['required', 'string', 'max:400'],
            'steps' => ['required', 'array', 'min:2', 'max:5'],
            'steps.*.title' => ['required', 'string', 'max:60'],
            'steps.*.text' => ['required', 'string', 'max:240'],
            'ffc_number' => ['nullable', 'string', 'max:40'],
            'show_calendar' => ['boolean'],
            'featured_listing' => ['nullable', 'string', Rule::exists('listings', 'ulid')],
        ]);

        $home->hero_title = $data['hero_title'];
        $home->hero_text = $data['hero_text'];
        $home->steps = array_values(array_map(fn (array $s): array => ['title' => $s['title'], 'text' => $s['text']], $data['steps']));
        $home->ffc_number = (string) ($data['ffc_number'] ?? '');
        $home->show_calendar = (bool) ($data['show_calendar'] ?? false);
        $home->featured_listing = $data['featured_listing'] ?? null;
        $home->save();
        SiteNavigation::forget();

        Audit::record('cms', 'home_updated', 'Updated the home page', null, $actor);
        $this->toast('Home page saved.');

        return back();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'page';
        if (in_array($base, self::RESERVED, true)) {
            $base .= '-page';
        }
        $slug = $base;
        for ($n = 2; Page::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function uniqueCollectionSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'collection';
        $slug = $base;
        for ($n = 2; Collection::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);
    }
}
