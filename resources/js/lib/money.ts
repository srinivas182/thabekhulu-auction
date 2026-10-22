/** R 3 150 000: rand, space-separated thousands, no decimals (brand book). */
export function rand(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    return (
        'R ' +
        Math.round(value)
            .toString()
            .replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
    );
}
