import type { SVGAttributes } from 'react';

/**
 * Thabekhulu mark: three rising peaks with the winning-bid dot.
 * Peaks use currentColor; the dot uses the brand accent token.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="-4 -2 80 68"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            <path
                fill="currentColor"
                d="M51.00,14.00 L39.43,38.23 L52.14,58.00 L72.00,58.00ZM50.00,58.00 L32.00,30.00 L20.70,47.58 L28.22,58.00ZM0.00,58.00 L13.00,40.00 L26.00,58.00Z"
            />
            <circle
                cx="51.00"
                cy="6.50"
                r="4.20"
                className="fill-brand-accent"
            />
        </svg>
    );
}
