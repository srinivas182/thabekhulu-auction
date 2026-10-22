<?php

namespace App\Domains\Cms\Http\Controllers\Public;

use App\Domains\Cms\Models\Gallery;
use App\Domains\Cms\Models\Post;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class InsightsController extends Controller
{
    public function index(): Response
    {
        $posts = Post::visible()->with(['author:id,first_name,last_name', 'media'])->latest('published_at')->paginate(9)
            ->through(fn (Post $p): array => $this->card($p));

        return Inertia::render('public/insights/index', ['posts' => $posts]);
    }

    public function show(string $slug): Response
    {
        $post = Post::visible()->with(['author:id,first_name,last_name', 'media'])->where('slug', $slug)->firstOrFail();
        $card = $this->card($post);

        return Inertia::render('public/insights/show', [
            'post' => $card + ['body' => $post->body, 'image' => $this->url($post->getFirstMedia('cover'), 'large')],
            'seo' => ['title' => ($post->seo_title ?: $post->title).' | Thabekhulu Investor insights', 'description' => $post->seo_description ?: (string) $post->excerpt],
        ])->withViewData(['structuredData' => [[
            '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post->title,
            'datePublished' => $post->published_at?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => ['@type' => 'Organization', 'name' => 'Thabekhulu Development Group'],
            'image' => $card['image'], 'description' => $post->excerpt,
        ]]]);
    }

    public function gallery(): Response
    {
        return Inertia::render('public/gallery', [
            'albums' => Gallery::where('is_visible', true)->orderBy('sort_order')->with('media')->get()
                ->filter(fn (Gallery $g) => $g->getMedia('images')->isNotEmpty())
                ->map(fn (Gallery $g): array => [
                    'title' => $g->title, 'description' => $g->description,
                    'images' => $g->getMedia('images')->map(fn (Media $m): array => [
                        'thumb' => $this->url($m, 'thumb'), 'large' => $this->url($m, 'large'),
                        'alt' => (string) $m->getCustomProperty('alt', ''), 'caption' => (string) $m->getCustomProperty('caption', ''),
                    ])->values()->all(),
                ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function card(Post $p): array
    {
        return [
            'title' => $p->title, 'slug' => $p->slug, 'topic' => $p->topic, 'excerpt' => $p->excerpt,
            'date' => $p->published_at?->setTimezone((string) config('platform.display_timezone'))->format('j F Y'),
            'image' => $this->url($p->getFirstMedia('cover'), 'card'),
        ];
    }

    private function url(?Media $m, string $conversion): ?string
    {
        return $m === null ? null : ($m->hasGeneratedConversion($conversion) ? $m->getUrl($conversion) : $m->getUrl());
    }
}
