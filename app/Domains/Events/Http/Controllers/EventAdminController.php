<?php

namespace App\Domains\Events\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Events\Actions\EventService;
use App\Domains\Events\Models\Event;
use App\Domains\Events\Models\Ticket;
use App\Domains\Listings\Support\HtmlCleaner;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff events (events.manage) and door check-in (events.checkin). */
class EventAdminController extends Controller
{
    public function index(): Response
    {
        $tz = (string) config('platform.display_timezone');

        return Inertia::render('admin/events/index', ['events' => Event::orderByDesc('starts_at')->get()->map(fn (Event $e) => [
            'ulid' => $e->ulid, 'title' => $e->title, 'status' => $e->status, 'when' => $e->starts_at->setTimezone($tz)->format('j M Y, H:i'),
            'sold' => $e->seatsTaken(), 'capacity' => $e->capacity, 'checkedIn' => Ticket::where('event_id', $e->id)->whereNotNull('checked_in_at')->count(), 'price' => $e->price,
        ])]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/events/form', ['event' => null]);
    }

    public function edit(Event $event): Response
    {
        $tz = (string) config('platform.display_timezone');

        return Inertia::render('admin/events/form', ['event' => [
            'ulid' => $event->ulid, 'title' => $event->title, 'description' => $event->description, 'kind' => $event->kind, 'venue' => $event->venue,
            'online_link' => $event->online_link, 'starts_at' => $event->starts_at->setTimezone($tz)->format('Y-m-d\TH:i'), 'ends_at' => $event->ends_at->setTimezone($tz)->format('Y-m-d\TH:i'),
            'price' => $event->price / 100, 'capacity' => $event->capacity, 'status' => $event->status,
        ]]);
    }

    public function store(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $event = new Event;
        $this->fill($event, $request);
        $event->status = 'draft';
        $event->slug = $this->slug($event->title);
        $event->save();
        Audit::record('events', 'created', "Created event {$event->title}", $event, $actor);

        return redirect()->route('admin.events.edit', $event);
    }

    public function update(Request $request, Event $event, #[CurrentUser] User $actor): RedirectResponse
    {
        if ($event->status === 'cancelled') {
            return back()->withErrors(['title' => __('A cancelled event cannot be edited.')]);
        }
        $this->fill($event, $request);
        $event->save();
        Audit::record('events', 'updated', "Updated event {$event->title}", $event, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event saved.')]);

        return back();
    }

    public function status(Request $request, Event $event, #[CurrentUser] User $actor): RedirectResponse
    {
        $to = (string) $request->validate(['status' => ['required', Rule::in(['published', 'cancelled', 'draft'])]])['status'];
        $event->forceFill(['status' => $to])->save();
        Audit::record('events', $to, ucfirst($to)." event {$event->title}", $event, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => $to === 'cancelled' ? __('Event cancelled. Contact ticket holders; refunds for paid tickets are issued through Paystack.') : __('Saved.')]);

        return back();
    }

    public function attendees(Event $event): StreamedResponse
    {
        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Order', 'Name', 'Email', 'Mobile', 'Ticket code', 'Checked in'], ',', '"', '\\');
            foreach (Ticket::with('order')->where('event_id', $event->id)->orderBy('id')->get() as $t) {
                fputcsv($out, [$t->order->reference, $t->order->name, $t->order->email, $t->order->mobile, $t->code, $t->checked_in_at?->toDateTimeString()], ',', '"', '\\');
            }
            fclose($out);
        }, "attendees-{$event->slug}.csv", ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, private']);
    }

    public function checkInPage(Event $event): Response
    {
        return Inertia::render('admin/events/checkin', ['event' => ['ulid' => $event->ulid, 'title' => $event->title,
            'checkedIn' => Ticket::where('event_id', $event->id)->whereNotNull('checked_in_at')->count(), 'issued' => Ticket::where('event_id', $event->id)->count()]]);
    }

    public function checkIn(Request $request, Event $event, EventService $service, #[CurrentUser] User $staff): JsonResponse
    {
        $input = (string) $request->validate(['code' => ['required', 'string', 'max:120']])['code'];

        return response()->json($service->checkIn($event, $input, $staff) + ['checkedIn' => Ticket::where('event_id', $event->id)->whereNotNull('checked_in_at')->count()]);
    }

    private function fill(Event $event, Request $request): void
    {
        $tz = (string) config('platform.display_timezone');
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:20000'], 'kind' => ['required', Rule::in(['in_person', 'online'])],
            'venue' => ['nullable', 'required_if:kind,in_person', 'string', 'max:255'], 'online_link' => ['nullable', 'required_if:kind,online', 'url:https', 'max:500'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'], 'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $event->fill([
            'title' => $d['title'], 'description' => HtmlCleaner::clean($d['description'] ?? null), 'kind' => $d['kind'],
            'venue' => $d['kind'] === 'in_person' ? $d['venue'] : null, 'online_link' => $d['kind'] === 'online' ? $d['online_link'] : null,
            'starts_at' => CarbonImmutable::parse($d['starts_at'], $tz)->utc(), 'ends_at' => CarbonImmutable::parse($d['ends_at'], $tz)->utc(),
            'price' => (int) round(((float) $d['price']) * 100), 'capacity' => (int) $d['capacity'],
        ]);
    }

    private function slug(string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        for ($i = 2; Event::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
