import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index,
    status as statusRoute,
    store,
    update,
} from '@/routes/admin/events';

type F = {
    title: string;
    description: string | null;
    kind: string;
    venue: string | null;
    online_link: string | null;
    starts_at: string;
    ends_at: string;
    price: number;
    capacity: number;
};
type Props = { event: (F & { ulid: string; status: string }) | null };

export default function EventForm({ event }: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [f, setF] = useState<F>(
        event ?? {
            title: '',
            description: '',
            kind: 'in_person',
            venue: '',
            online_link: '',
            starts_at: '',
            ends_at: '',
            price: 0,
            capacity: 50,
        },
    );
    const set = (k: keyof F, v: string | number) => setF({ ...f, [k]: v });
    const save = () =>
        event
            ? router.put(update(event.ulid).url, f as never, {
                  preserveScroll: true,
              })
            : router.post(store().url, f as never);

    return (
        <>
            <Head title={event ? event.title : 'New event'} />
            <div className="flex max-w-3xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <h1 className="font-display text-3xl">
                    {event ? event.title : 'New event'}{' '}
                    {event && (
                        <span className="text-base font-normal text-muted-foreground">
                            · {event.status}
                        </span>
                    )}
                </h1>
                <div className="grid gap-4 rounded-xl border border-border bg-card p-5">
                    <div className="grid gap-1.5">
                        <Label htmlFor="t">Title</Label>
                        <Input
                            id="t"
                            value={f.title}
                            onChange={(e) => set('title', e.target.value)}
                        />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="d">Description</Label>
                        <RichTextEditor
                            id="d"
                            label="Description"
                            value={f.description ?? ''}
                            onChange={(v) => set('description', v)}
                        />
                    </div>
                    <fieldset className="flex gap-4 text-sm">
                        <legend className="mb-1 text-sm font-medium">
                            Where
                        </legend>
                        {[
                            ['in_person', 'In person'],
                            ['online', 'Online'],
                        ].map(([k, l]) => (
                            <label
                                key={k}
                                className="flex items-center gap-1.5"
                            >
                                <input
                                    type="radio"
                                    checked={f.kind === k}
                                    onChange={() => set('kind', k)}
                                    className="size-4 accent-primary"
                                />
                                {l}
                            </label>
                        ))}
                    </fieldset>
                    {f.kind === 'in_person' ? (
                        <div className="grid gap-1.5">
                            <Label htmlFor="v">Venue</Label>
                            <Input
                                id="v"
                                value={f.venue ?? ''}
                                onChange={(e) => set('venue', e.target.value)}
                            />
                            <InputError message={errors.venue} />
                        </div>
                    ) : (
                        <div className="grid gap-1.5">
                            <Label htmlFor="o">
                                Online link (shown to ticket holders only)
                            </Label>
                            <Input
                                id="o"
                                value={f.online_link ?? ''}
                                onChange={(e) =>
                                    set('online_link', e.target.value)
                                }
                            />
                            <InputError message={errors.online_link} />
                        </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="s">Starts (SAST)</Label>
                            <Input
                                id="s"
                                type="datetime-local"
                                value={f.starts_at}
                                onChange={(e) =>
                                    set('starts_at', e.target.value)
                                }
                            />
                            <InputError message={errors.starts_at} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="e">Ends (SAST)</Label>
                            <Input
                                id="e"
                                type="datetime-local"
                                value={f.ends_at}
                                onChange={(e) => set('ends_at', e.target.value)}
                            />
                            <InputError message={errors.ends_at} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="p">
                                Ticket price (R, 0 for free)
                            </Label>
                            <Input
                                id="p"
                                inputMode="decimal"
                                value={f.price}
                                onChange={(e) => set('price', e.target.value)}
                            />
                            <InputError message={errors.price} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="c">Capacity</Label>
                            <Input
                                id="c"
                                inputMode="numeric"
                                value={f.capacity}
                                onChange={(e) =>
                                    set('capacity', e.target.value)
                                }
                            />
                            <InputError message={errors.capacity} />
                        </div>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button onClick={save}>Save</Button>
                    {event && event.status === 'draft' && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.put(
                                    statusRoute(event.ulid).url,
                                    { status: 'published' },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Publish
                        </Button>
                    )}
                    {event && event.status === 'published' && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                window.confirm(
                                    'Cancel this event? Ticket holders must be told; paid tickets are refunded through Paystack.',
                                ) &&
                                router.put(
                                    statusRoute(event.ulid).url,
                                    { status: 'cancelled' },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Cancel event
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

EventForm.layout = { breadcrumbs: [{ title: 'Events', href: index() }] };
