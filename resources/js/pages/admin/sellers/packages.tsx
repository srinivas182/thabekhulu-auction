import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { randCents } from '@/lib/cents';
import { packages as packagesRoute } from '@/routes/admin/sellers';
import packageRoutes from '@/routes/admin/sellers/packages';

type Props = {
    packages: {
        id: number;
        name: string;
        price: number;
        duration_days: number;
        max_listings: number;
        active: boolean;
    }[];
    sellers: {
        id: number;
        name: string;
        email: string;
        expires: string | null;
    }[];
};

export default function SellerPackages({ packages, sellers }: Props) {
    return (
        <>
            <Head title="Seller packages" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Seller packages</h1>
                    <p className="mt-1 text-muted-foreground">
                        Phase 2 sells packages online; for now staff define them
                        and assign them to sellers. A seller’s auction may not
                        end after their package expires.
                    </p>
                </div>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {packages.map((p) => (
                        <li
                            key={p.id}
                            className="flex justify-between px-5 py-3 text-sm"
                        >
                            <span className="font-medium">{p.name}</span>
                            <span className="text-muted-foreground">
                                {randCents(p.price)} · {p.duration_days} days ·
                                up to {p.max_listings} listings
                            </span>
                        </li>
                    ))}
                    {packages.length === 0 && (
                        <li className="px-5 py-6 text-center text-muted-foreground">
                            No packages yet.
                        </li>
                    )}
                </ul>
                <Form
                    action={packageRoutes.store().url}
                    method="post"
                    resetOnSuccess
                    className="grid gap-3 rounded-xl border border-border bg-card p-5 sm:grid-cols-4 sm:items-end"
                >
                    {({ processing }) => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="n">Name</Label>
                                <Input id="n" name="name" />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="p">Price (R)</Label>
                                <Input
                                    id="p"
                                    name="price"
                                    inputMode="decimal"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="d">Days</Label>
                                <Input
                                    id="d"
                                    name="duration_days"
                                    inputMode="numeric"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="m">Listings</Label>
                                <Input
                                    id="m"
                                    name="max_listings"
                                    inputMode="numeric"
                                />
                            </div>
                            <Button disabled={processing} className="w-fit">
                                Add package
                            </Button>
                        </>
                    )}
                </Form>
                <Form
                    action={packageRoutes.assign().url}
                    method="post"
                    className="grid gap-3 rounded-xl border border-border bg-card p-5 sm:grid-cols-[1.4fr_1fr_1fr_auto] sm:items-end"
                >
                    {({ processing }) => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="s">Seller</Label>
                                <select
                                    id="s"
                                    name="user_id"
                                    className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                >
                                    {sellers.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} ({s.email})
                                            {s.expires
                                                ? ` · until ${s.expires}`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="pk">Package</Label>
                                <select
                                    id="pk"
                                    name="seller_package_id"
                                    className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                >
                                    {packages.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="st">Starts</Label>
                                <Input id="st" type="date" name="starts_on" />
                            </div>
                            <Button disabled={processing} className="w-fit">
                                Assign
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

SellerPackages.layout = {
    breadcrumbs: [{ title: 'Seller packages', href: packagesRoute() }],
};
