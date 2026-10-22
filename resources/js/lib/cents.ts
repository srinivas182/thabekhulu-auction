import { rand } from '@/lib/money';

/** R 1 250 000.50 from cents (quotations show cents; auction prices don't). */
export function randCents(cents: number | null | undefined): string {
    if (cents === null || cents === undefined) return '—';
    const whole = rand(Math.trunc(cents / 100));
    const part = String(Math.abs(cents % 100)).padStart(2, '0');

    return `${whole}.${part}`;
}
