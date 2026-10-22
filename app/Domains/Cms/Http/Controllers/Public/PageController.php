<?php

namespace App\Domains\Cms\Http\Controllers\Public;

use App\Domains\Cms\Models\Page;
use App\Domains\Cms\Support\Blocks;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $page = Page::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $faq = Blocks::faqSchema($page->blocks);

        return Inertia::render('public/page', [
            'page' => ['title' => $page->title, 'slug' => $page->slug, 'blocks' => Blocks::withImageUrls($page), 'updatedAt' => $page->updated_at?->format('j F Y')],
            'seo' => ['title' => ($page->seo_title ?: $page->title).' | Thabekhulu', 'description' => (string) $page->seo_description],
        ])->withViewData($faq !== null ? ['structuredData' => [$faq]] : []);
    }
}
