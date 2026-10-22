<?php

namespace App\Domains\Cms\Support;

use App\Domains\Cms\Models\Page;
use App\Domains\Listings\Support\HtmlCleaner;
use Illuminate\Validation\ValidationException;

/**
 * Page content blocks. Each block is validated and its HTML cleaned before
 * saving, so public pages only ever render safe markup.
 *
 * Types: rich_text {html}, two_column {left, right}, cta {title, text,
 * button_label, button_url}, faq {items: [{question, answer}]}.
 */
final class Blocks
{
    public const TYPES = ['rich_text' => 'Text', 'image' => 'Image', 'two_column' => 'Two columns', 'cta' => 'Call to action', 'faq' => 'Questions and answers'];

    /**
     * @param  array<int, mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function clean(array $blocks, ?Page $page = null): array
    {
        $clean = [];

        foreach (array_values($blocks) as $i => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;

            $clean[] = match ($type) {
                'rich_text' => ['type' => 'rich_text', 'html' => HtmlCleaner::clean((string) ($block['html'] ?? '')) ?? ''],
                'two_column' => [
                    'type' => 'two_column',
                    'left' => HtmlCleaner::clean((string) ($block['left'] ?? '')) ?? '',
                    'right' => HtmlCleaner::clean((string) ($block['right'] ?? '')) ?? '',
                ],
                'cta' => self::cta($block, $i),
                'image' => self::image($block, $i, $page),
                'faq' => [
                    'type' => 'faq',
                    'items' => array_values(array_filter(array_map(fn ($item): ?array => is_array($item) && filled($item['question'] ?? null)
                        ? ['question' => mb_substr(strip_tags((string) $item['question']), 0, 300), 'answer' => HtmlCleaner::clean((string) ($item['answer'] ?? '')) ?? '']
                        : null, (array) ($block['items'] ?? [])))),
                ],
                default => throw ValidationException::withMessages(["blocks.{$i}" => __('Unknown block type.')]),
            };
        }

        return $clean;
    }

    /**
     * Image blocks may only use images uploaded to this page; alt text is required.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private static function image(array $block, int $i, ?Page $page): array
    {
        $id = (int) ($block['media_id'] ?? 0);
        $owned = $page !== null && $page->getMedia('images')->contains('id', $id);

        if (! $owned) {
            throw ValidationException::withMessages(["blocks.{$i}" => __('Upload the image to this page first.')]);
        }
        if (blank($block['alt'] ?? null)) {
            throw ValidationException::withMessages(["blocks.{$i}.alt" => __('Describe the image for screen readers and search engines.')]);
        }

        return [
            'type' => 'image',
            'media_id' => $id,
            'alt' => mb_substr(strip_tags((string) $block['alt']), 0, 200),
            'caption' => mb_substr(strip_tags((string) ($block['caption'] ?? '')), 0, 300),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, string>
     */
    private static function cta(array $block, int $i): array
    {
        $url = trim((string) ($block['button_url'] ?? ''));

        if ($url !== '' && ! preg_match('#^(/[A-Za-z0-9/_\-?=&.%]*|https://\S+|mailto:\S+|tel:[+0-9 ]+)$#', $url)) {
            throw ValidationException::withMessages(["blocks.{$i}.button_url" => __('Use a page path like /auctions or a full https:// link.')]);
        }

        return [
            'type' => 'cta',
            'title' => mb_substr(strip_tags((string) ($block['title'] ?? '')), 0, 160),
            'text' => mb_substr(strip_tags((string) ($block['text'] ?? '')), 0, 500),
            'button_label' => mb_substr(strip_tags((string) ($block['button_label'] ?? '')), 0, 60),
            'button_url' => $url,
        ];
    }

    /**
     * FAQPage structured data for any question blocks.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    public static function faqSchema(array $blocks): ?array
    {
        $items = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'faq') {
                foreach ((array) $block['items'] as $item) {
                    $items[] = ['@type' => 'Question', 'name' => $item['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $item['answer'])]];
                }
            }
        }

        return $items === [] ? null : ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items];
    }

    /**
     * Blocks with image URLs added, for rendering and editing.
     *
     * @return list<array<string, mixed>>
     */
    public static function withImageUrls(Page $page): array
    {
        $media = $page->getMedia('images')->keyBy('id');

        return array_map(function (array $block) use ($media): array {
            if (($block['type'] ?? null) === 'image') {
                $m = $media->get($block['media_id'] ?? 0);
                $block['url'] = $m === null ? null : ($m->hasGeneratedConversion('large') ? $m->getUrl('large') : $m->getUrl());
            }

            return $block;
        }, $page->blocks);
    }
}
