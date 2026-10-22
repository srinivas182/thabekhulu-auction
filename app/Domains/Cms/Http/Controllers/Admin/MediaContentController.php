<?php

namespace App\Domains\Cms\Http\Controllers\Admin;

use App\Domains\Audit\Support\Audit;
use App\Domains\Cms\Models\Gallery;
use App\Domains\Cms\Models\Page;
use App\Domains\Cms\Models\Post;
use App\Domains\Listings\Support\HtmlCleaner;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Investor insights posts, gallery albums and page images (cms.manage).
 */
class MediaContentController extends Controller
{
    public function posts(): Response
    {
        return Inertia::render('admin/cms/posts', [
            'posts' => Post::latest('updated_at')->get()->map(fn (Post $p): array => [
                'id' => $p->id, 'title' => $p->title, 'topic' => $p->topic, 'status' => $this->postState($p),
                'publishedAt' => $p->published_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storePost(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $title = $request->validate(['title' => ['required', 'string', 'max:160']])['title'];
        $post = Post::create(['title' => $title, 'slug' => $this->unique(Post::class, $title), 'topic' => Post::TOPICS[0], 'author_id' => $actor->id]);
        Audit::record('cms', 'post_created', "Created article {$post->title}", $post, $actor);

        return redirect()->route('admin.cms.posts.edit', $post);
    }

    public function editPost(Post $post): Response
    {
        $tz = (string) config('platform.display_timezone');

        return Inertia::render('admin/cms/post-edit', [
            'post' => [
                'id' => $post->id, 'title' => $post->title, 'slug' => $post->slug, 'topic' => $post->topic, 'excerpt' => $post->excerpt,
                'body' => $post->body, 'status' => $post->status, 'state' => $this->postState($post),
                'publishedAt' => $post->published_at?->setTimezone($tz)->format('Y-m-d\TH:i'),
                'seoTitle' => $post->seo_title, 'seoDescription' => $post->seo_description,
                'cover' => $post->getFirstMediaUrl('cover') ?: null,
            ],
            'topics' => Post::TOPICS,
        ]);
    }

    public function updatePost(Request $request, Post $post, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('posts', 'slug')->ignore($post->id)],
            'topic' => ['required', Rule::in(Post::TOPICS)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:200000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'required_if:status,published', 'date'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $post->fill([
            'title' => $data['title'], 'slug' => $data['slug'], 'topic' => $data['topic'], 'excerpt' => $data['excerpt'] ?? null,
            'body' => HtmlCleaner::clean($data['body'] ?? null), 'status' => $data['status'],
            'published_at' => filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at'], (string) config('platform.display_timezone'))->utc() : null,
            'seo_title' => $data['seo_title'] ?? null, 'seo_description' => $data['seo_description'] ?? null,
        ])->save();

        $cover = $request->file('cover');
        if ($cover instanceof UploadedFile) {
            $post->addMedia($cover)->usingFileName(Str::random(24).'.'.strtolower($cover->getClientOriginalExtension()))->toMediaCollection('cover');
        }

        Audit::record('cms', 'post_saved', "Saved article {$post->title} ({$this->postState($post)})", $post, $actor);
        $this->toast('Article saved.');

        return back();
    }

    public function destroyPost(Post $post, #[CurrentUser] User $actor): RedirectResponse
    {
        $title = $post->title;
        $post->delete();
        Audit::record('cms', 'post_deleted', "Deleted article {$title}", null, $actor);

        return redirect()->route('admin.cms.posts');
    }

    public function galleries(): Response
    {
        return Inertia::render('admin/cms/galleries', [
            'galleries' => Gallery::orderBy('sort_order')->with('media')->get()->map(fn (Gallery $g): array => [
                'id' => $g->id, 'title' => $g->title, 'description' => $g->description, 'visible' => $g->is_visible,
                'images' => $g->getMedia('images')->map(fn (Media $m): array => [
                    'id' => $m->id, 'url' => $m->getUrl(), 'alt' => (string) $m->getCustomProperty('alt', ''), 'caption' => (string) $m->getCustomProperty('caption', ''),
                ])->values(),
            ]),
        ]);
    }

    public function saveGallery(Request $request, #[CurrentUser] User $actor, ?Gallery $gallery = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'is_visible' => ['boolean'],
        ]);
        $gallery ??= new Gallery(['slug' => $this->unique(Gallery::class, $data['title']), 'sort_order' => (int) Gallery::max('sort_order') + 1]);
        $gallery->fill($data)->save();

        Audit::record('cms', 'gallery_saved', "Saved album {$gallery->title}", $gallery, $actor);
        $this->toast('Album saved.');

        return back();
    }

    public function destroyGallery(Gallery $gallery, #[CurrentUser] User $actor): RedirectResponse
    {
        $title = $gallery->title;
        $gallery->delete();
        Audit::record('cms', 'gallery_deleted', "Deleted album {$title}", null, $actor);

        return back();
    }

    public function uploadGalleryImages(Request $request, Gallery $gallery): RedirectResponse
    {
        $request->validate(['files' => ['required', 'array', 'max:30'], 'files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360']]);

        /** @var list<UploadedFile> $files */
        $files = $request->file('files');
        foreach ($files as $file) {
            $gallery->addMedia($file)->usingFileName(Str::random(24).'.'.strtolower($file->getClientOriginalExtension()))
                ->withCustomProperties(['alt' => '', 'caption' => ''])->toMediaCollection('images');
        }
        $this->toast(count($files).' image(s) added.');

        return back();
    }

    public function updateGalleryImage(Request $request, Gallery $gallery, Media $media): RedirectResponse
    {
        $this->owns($gallery, $media);
        $data = $request->validate(['alt' => ['required', 'string', 'max:200'], 'caption' => ['nullable', 'string', 'max:300']]);
        $media->setCustomProperty('alt', $data['alt'])->setCustomProperty('caption', (string) ($data['caption'] ?? ''))->save();

        return back();
    }

    public function destroyGalleryImage(Gallery $gallery, Media $media): RedirectResponse
    {
        $this->owns($gallery, $media);
        $media->delete();

        return back();
    }

    /** Image uploads for page image blocks. Returns JSON for the editor. */
    public function uploadPageImage(Request $request, Page $page): JsonResponse
    {
        $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $media = $page->addMedia($file)->usingFileName(Str::random(24).'.'.strtolower($file->getClientOriginalExtension()))->toMediaCollection('images');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()]);
    }

    private function owns(Gallery $gallery, Media $media): void
    {
        abort_unless($media->model_type === $gallery->getMorphClass() && (int) $media->model_id === $gallery->id, 404);
    }

    private function postState(Post $p): string
    {
        if ($p->status !== 'published') {
            return 'Draft';
        }

        return $p->published_at !== null && $p->published_at->isFuture() ? 'Scheduled' : 'Published';
    }

    /** @param class-string<Model> $model */
    private function unique(string $model, string $title): string
    {
        $base = Str::slug($title) ?: 'item';
        $slug = $base;
        for ($n = 2; $model::query()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);
    }
}
