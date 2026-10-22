import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index, store } from '@/routes/admin/categories';

type Category = {
    slug: string;
    name: string;
    visible: boolean;
    deposit: number;
    feePercent: number;
    depositTerms: string;
    fields: number;
    documents: number;
};

export const rand = (value: number) =>
    'R ' + Math.round(value).toLocaleString('en-ZA').replace(/,/g, ' ');

export default function CategoriesIndex({
    categories,
}: {
    categories: Category[];
}) {
    return (
        <>
            <Head title="Categories" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Categories</h1>
                    <p className="mt-1 max-w-2xl text-muted-foreground">
                        Each category sets the default deposit, platform fee and
                        deposit terms for new listings, and the key facts and
                        documents every listing needs.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b border-border text-muted-foreground">
                            <tr>
                                <th className="px-5 py-3 font-medium">
                                    Category
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Default deposit
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Platform fee
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Deposit terms
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Key facts
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Documents
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {categories.map((c) => (
                                <tr key={c.slug}>
                                    <td className="px-5 py-3">
                                        <Link
                                            href={edit(c.slug)}
                                            className="font-medium text-foreground underline-offset-4 hover:underline"
                                        >
                                            {c.name}
                                        </Link>
                                        {!c.visible && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                Hidden
                                            </span>
                                        )}
                                    </td>
                                    <td className="tabular px-5 py-3">
                                        {rand(c.deposit)}
                                    </td>
                                    <td className="tabular px-5 py-3">
                                        {c.feePercent}%
                                    </td>
                                    <td className="px-5 py-3">
                                        {c.depositTerms}
                                    </td>
                                    <td className="tabular px-5 py-3">
                                        {c.fields}
                                    </td>
                                    <td className="tabular px-5 py-3">
                                        {c.documents}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Form
                    {...store.form()}
                    className="flex max-w-xl flex-col gap-3 rounded-xl border border-border bg-card p-5 sm:flex-row sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="new-category">
                                    New category
                                </Label>
                                <Input
                                    id="new-category"
                                    name="name"
                                    required
                                    placeholder="e.g. Industrial"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <Button disabled={processing}>Add category</Button>
                        </>
                    )}
                </Form>
                <p className="-mt-3 text-sm text-muted-foreground">
                    New categories start hidden, so you can set them up before
                    they appear on the website.
                </p>
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: index() }],
};
