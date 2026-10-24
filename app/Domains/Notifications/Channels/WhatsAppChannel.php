<?php

namespace App\Domains\Notifications\Channels;

use App\Domains\Notifications\Settings\WhatsAppSettings;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp Business Cloud API: template messages only (required outside a
 * 24-hour customer window), and only to buyers who opted in (POPIA consent).
 * Failures are logged, never thrown: email is always sent as well.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $settings = app(WhatsAppSettings::class);
        if (! $settings->ready() || ! $notifiable instanceof User || $notifiable->whatsapp_opt_in_at === null || ! $notifiable->whatsapp_number || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        /** @var array{template: string, params: list<string>} $message */
        $message = $notification->toWhatsApp($notifiable);

        try {
            $response = Http::timeout(15)->withToken($settings->access_token)->post(
                "https://graph.facebook.com/{$settings->api_version}/{$settings->phone_number_id}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'to' => preg_replace('/\D/', '', $notifiable->whatsapp_number),
                    'type' => 'template',
                    'template' => [
                        'name' => $message['template'],
                        'language' => ['code' => $settings->language],
                        'components' => [['type' => 'body', 'parameters' => array_map(fn (string $p) => ['type' => 'text', 'text' => $p], $message['params'])]],
                    ],
                ],
            );
            if (! $response->successful()) {
                Log::warning('WhatsApp message not accepted', ['status' => $response->status(), 'error' => $response->json('error.message')]);
            }
        } catch (Throwable $e) {
            Log::warning('WhatsApp unavailable', ['error' => $e->getMessage()]);
        }
    }
}
