<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $order->reference }}</title>
<style>
    @page { margin: 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #14201c; }
    .ticket { border: 2px solid #1d4a3c; border-radius: 6px; padding: 14px; margin-bottom: 14px; page-break-inside: avoid; }
    .title { font-size: 15pt; font-weight: bold; color: #1d4a3c; }
    .muted { color: #55635d; }
    .code { font-size: 16pt; font-weight: bold; letter-spacing: 2px; }
    td { vertical-align: top; }
</style>
</head>
<body>
@foreach ($order->tickets as $i => $t)
    <div class="ticket">
        <table width="100%"><tr>
            <td>
                <div class="title">{{ $order->event->title }}</div>
                <div>{{ $order->event->starts_at->setTimezone($tz)->format('l j F Y, H:i') }} SAST</div>
                <div class="muted">{{ $order->event->kind === 'online' ? 'Online event' : $order->event->venue }}</div>
                <p>Ticket {{ $i + 1 }} of {{ $order->quantity }} · {{ $order->name }}</p>
                <div class="muted">Door code</div>
                <div class="code">{{ $t->code }}</div>
                <p class="muted">Each ticket admits one person once. Order {{ $order->reference }}.</p>
            </td>
            <td width="170" style="text-align:right"><img src="{{ $qrs[$t->id] }}" width="160" height="160" alt="QR code"></td>
        </tr></table>
    </div>
@endforeach
</body>
</html>
