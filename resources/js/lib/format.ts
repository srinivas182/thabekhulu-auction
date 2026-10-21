const displayTimeZone = 'Africa/Johannesburg';

/**
 * Formats an ISO date-time in South African time, e.g. "21 Oct 2026, 14:05".
 */
export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-ZA', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: displayTimeZone,
    }).format(new Date(iso));
}
