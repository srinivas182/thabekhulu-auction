/**
 * WCAG contrast maths for instant feedback in the theme manager.
 * Mirrors app/Domains/Theme/Support/Contrast.php; the server decides.
 */
const channel = (c: number) =>
    c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;

const rgb = (hex: string): [number, number, number] => {
    const h = hex.replace('#', '');

    return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16) / 255) as [
        number,
        number,
        number,
    ];
};

export const isHex = (value: string) => /^#[0-9a-fA-F]{6}$/.test(value);

export function luminance(hex: string): number {
    const [r, g, b] = rgb(hex);

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

export function ratio(foreground: string, background: string): number {
    const a = luminance(foreground);
    const b = luminance(background);

    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

function toHsl(hex: string): [number, number, number] {
    const [r, g, b] = rgb(hex);
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;

    if (max === min) {
        return [0, 0, l];
    }

    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    let h =
        max === r
            ? (g - b) / d + (g < b ? 6 : 0)
            : max === g
              ? (b - r) / d + 2
              : (r - g) / d + 4;
    h /= 6;

    return [h, s, l];
}

function fromHsl(h: number, s: number, l: number): string {
    const hue = (p: number, q: number, t: number) => {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;

        return p;
    };
    let r = l;
    let g = l;
    let b = l;

    if (s !== 0) {
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;
        r = hue(p, q, h + 1 / 3);
        g = hue(p, q, h);
        b = hue(p, q, h - 1 / 3);
    }

    return (
        '#' +
        [r, g, b]
            .map((v) =>
                Math.round(v * 255)
                    .toString(16)
                    .padStart(2, '0'),
            )
            .join('')
    );
}

/** Nearest colour (same hue) reaching the minimum ratio against the background. */
export function suggest(
    foreground: string,
    background: string,
    minimum: number,
): string {
    if (ratio(foreground, background) >= minimum) {
        return foreground.toLowerCase();
    }

    const [h, s, start] = toHsl(foreground);
    const step = luminance(background) > 0.18 ? -0.005 : 0.005;
    let l = start;

    for (let i = 0; i <= 200; i++) {
        l = Math.max(0, Math.min(1, l + step));
        const candidate = fromHsl(h, s, l);

        if (ratio(candidate, background) >= minimum) {
            return candidate;
        }

        if (l <= 0 || l >= 1) break;
    }

    return step < 0 ? '#000000' : '#ffffff';
}
