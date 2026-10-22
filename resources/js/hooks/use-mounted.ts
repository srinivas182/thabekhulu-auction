import { useEffect, useState } from 'react';

/**
 * False during server rendering and the first client render, true after.
 * Use it for browser-only output (maps, live clocks) so hydration matches.
 */
export function useMounted(): boolean {
    const [mounted, setMounted] = useState(false);

    useEffect(() => setMounted(true), []);

    return mounted;
}
