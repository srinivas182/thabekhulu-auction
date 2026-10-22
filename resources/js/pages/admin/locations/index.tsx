import { Form, Head, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/locations';
import cities from '@/routes/admin/locations/cities';

type Province = {
    id: number;
    name: string;
    cities: { id: number; name: string; active: boolean }[];
};

export default function Locations({ provinces }: { provinces: Province[] }) {
    return (
        <>
            <Head title="Locations" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Locations</h1>
                    <p className="mt-1 max-w-2xl text-muted-foreground">
                        Cities available for listings and premium slots. Hidden
                        cities stay on existing listings but can’t be chosen for
                        new ones.
                    </p>
                </div>

                <Form
                    {...cities.store.form()}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="flex max-w-2xl flex-col gap-3 rounded-xl border border-border bg-card p-5 sm:flex-row sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="province">Province</Label>
                                <select
                                    id="province"
                                    name="province_id"
                                    required
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {provinces.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="city">City or town</Label>
                                <Input id="city" name="name" required />
                                <InputError message={errors.name} />
                            </div>
                            <Button disabled={processing}>Add city</Button>
                        </>
                    )}
                </Form>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {provinces.map((p) => (
                        <section
                            key={p.id}
                            className="rounded-xl border border-border bg-card p-5"
                            aria-labelledby={`p-${p.id}`}
                        >
                            <h2 id={`p-${p.id}`} className="font-semibold">
                                {p.name}
                            </h2>
                            <ul className="mt-3 grid gap-1 text-sm">
                                {p.cities.map((c) => (
                                    <li
                                        key={c.id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            className={
                                                c.active
                                                    ? ''
                                                    : 'text-muted-foreground line-through'
                                            }
                                        >
                                            {c.name}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.post(
                                                    cities.toggle(c.id).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {c.active ? 'Hide' : 'Show'}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

Locations.layout = {
    breadcrumbs: [{ title: 'Locations', href: index() }],
};
