import type { ReactNode } from 'react';

export const field =
    'h-12 w-full rounded-[10px] border border-input bg-card px-3 text-base';
export const area =
    'min-h-32 w-full rounded-[10px] border border-input bg-card px-3 py-2 text-base';

export function FormField({
    label,
    error,
    children,
    hint,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <label className="flex flex-col gap-1.5 text-sm font-semibold">
            {label}
            {children}
            {hint && (
                <span className="font-normal text-muted-foreground">
                    {hint}
                </span>
            )}
            {error && (
                <span className="font-semibold text-destructive">{error}</span>
            )}
        </label>
    );
}

/** Hidden from people and screen readers; bots that fill it are ignored. */
export function Honeypot() {
    return (
        <div
            aria-hidden="true"
            className="absolute -left-[9999px] h-0 w-0 overflow-hidden"
        >
            <label>
                Leave this empty
                <input
                    type="text"
                    name="website"
                    tabIndex={-1}
                    autoComplete="off"
                />
            </label>
        </div>
    );
}
