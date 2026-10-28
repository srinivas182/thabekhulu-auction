<?php

namespace App\Domains\Platform\Email;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Builds an email from its template (admin wording or the default) and the details supplied by the system. */
final class TemplateMail
{
    /**
     * The template as it stands: an admin's saved wording, or the default.
     *
     * @return array{subject: string, greeting: string, body: string, after: string, button: string|null, edited: bool}
     */
    public static function current(string $key): array
    {
        $d = EmailTemplates::all()[$key] ?? throw new \InvalidArgumentException("Unknown email template {$key}");
        $row = Schema::hasTable('email_templates') ? DB::table('email_templates')->where('key', $key)->first() : null;

        return $row === null
            ? ['subject' => $d['subject'], 'greeting' => $d['greeting'], 'body' => $d['body'], 'after' => $d['after'], 'button' => $d['button'], 'edited' => false]
            : ['subject' => (string) $row->subject, 'greeting' => (string) $row->greeting, 'body' => (string) $row->body, 'after' => (string) $row->after,
                'button' => $d['button'] === null ? null : ($row->button ?: $d['button']), 'edited' => true];
    }

    /**
     * @param  array<string, string|int|null>  $vars
     * @param  array{subject?: string, greeting?: string, body?: string, after?: string, button?: string|null}|null  $override  unsaved wording, for previews
     */
    public static function make(string $key, array $vars, ?string $url = null, ?string $salutation = null, ?array $override = null): MailMessage
    {
        $t = array_merge(self::current($key), $override ?? []);
        $fill = fn (string $text, bool $plain = false) => self::fill($text, $vars, $plain);

        $mail = (new MailMessage)->subject($fill($t['subject'], true))->greeting($fill($t['greeting']));
        foreach (self::paragraphs($fill($t['body'])) as $p) {
            $mail->line($p);
        }
        if ($url !== null && ($t['button'] ?? null)) {
            $mail->action($fill((string) $t['button'], true), $url); // the link is always the system's
        }
        foreach (self::paragraphs($fill($t['after'])) as $p) {
            $mail->line($p);
        }

        // Two spaces before the newline make a Markdown line break, so the name sits on its own line.
        return $mail->salutation(str_replace("\n", "  \n", $salutation ?? "Kind regards,\nThabekhulu Development Group"));
    }

    /**
     * Placeholders a text uses.
     *
     * @return list<string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $m);

        return array_values(array_unique($m[1]));
    }

    /** @param array<string, string|int|null> $vars */
    private static function fill(string $text, array $vars, bool $plain = false): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($vars, $plain) {
            $v = (string) ($vars[$m[1]] ?? '');

            // Details are text, never formatting: escape Markdown so a name or title can't become a link.
            return $plain ? $v : (string) preg_replace('/([\\\\`*_\[\]()#<>!|])/', '\\\\$1', $v);
        }, $text);
    }

    /** @return list<string> */
    private static function paragraphs(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', trim($text)) ?: []), fn ($p) => $p !== ''));
    }
}
