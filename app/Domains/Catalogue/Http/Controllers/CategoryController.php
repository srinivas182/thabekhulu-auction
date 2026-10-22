<?php

namespace App\Domains\Catalogue\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Enums\AttributeType;
use App\Domains\Catalogue\Enums\DepositTerms;
use App\Domains\Catalogue\Enums\DocumentVisibility;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\CategoryAttribute;
use App\Domains\Catalogue\Models\DocumentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Categories with their commercial defaults, key-fact fields and document
 * types (sprint 3 spec 4.2–4.4). Every change is audit-logged.
 */
class CategoryController extends Controller
{
    private const FORMATS = ['pdf', 'jpg', 'png', 'docx', 'xlsx'];

    public function index(): Response
    {
        return Inertia::render('admin/categories/index', [
            'categories' => Category::withCount(['attributeDefinitions', 'documentTypes'])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $c): array => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'visible' => $c->is_visible,
                    'deposit' => $c->default_deposit,
                    'feePercent' => $c->default_fee_bps / 100,
                    'depositTerms' => $c->default_deposit_terms->label(),
                    'fields' => (int) $c->getAttribute('attribute_definitions_count'),
                    'documents' => (int) $c->getAttribute('document_types_count'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')]]);

        $category = Category::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'default_deposit' => 50000,
            'default_fee_bps' => 2000,
            'default_deposit_terms' => DepositTerms::Applied,
            'is_visible' => false,
            'sort_order' => (int) Category::max('sort_order') + 1,
        ]);

        Audit::record('catalogue', 'category_created', "Created category {$category->name}", $category);

        return redirect()->route('admin.categories.edit', $category);
    }

    public function edit(Category $category): Response
    {
        $category->load(['attributeDefinitions', 'documentTypes']);

        return Inertia::render('admin/categories/edit', [
            'category' => [
                'slug' => $category->slug,
                'name' => $category->name,
                'description' => $category->description,
                'icon' => $category->icon,
                'defaultDeposit' => $category->default_deposit,
                'defaultFeePercent' => $category->default_fee_bps / 100,
                'defaultDepositTerms' => $category->default_deposit_terms->value,
                'requiresComplianceNotice' => $category->requires_compliance_notice,
                'visible' => $category->is_visible,
                'seoTitle' => $category->seo_title,
                'seoDescription' => $category->seo_description,
            ],
            'fields' => $category->attributeDefinitions->map(fn (CategoryAttribute $a): array => [
                'id' => $a->id, 'key' => $a->key, 'label' => $a->label, 'type' => $a->type->value,
                'unit' => $a->unit, 'options' => $a->options ?? [], 'required' => $a->is_required,
                'onCard' => $a->show_on_card, 'keyFacts' => $a->show_in_key_facts, 'filterable' => $a->is_filterable,
            ]),
            'documents' => $category->documentTypes->map(fn (DocumentType $d): array => [
                'id' => $d->id, 'name' => $d->name, 'mandatory' => $d->is_mandatory,
                'visibility' => $d->visibility->value, 'formats' => $d->accepted_formats, 'maxSizeMb' => $d->max_size_mb,
            ]),
            'options' => [
                'types' => collect(AttributeType::cases())->map(fn (AttributeType $t) => ['value' => $t->value, 'label' => $t->label()]),
                'visibilities' => collect(DocumentVisibility::cases())->map(fn (DocumentVisibility $v) => ['value' => $v->value, 'label' => $v->label()]),
                'depositTerms' => collect(DepositTerms::cases())->map(fn (DepositTerms $t) => ['value' => $t->value, 'label' => $t->label()]),
                'formats' => self::FORMATS,
            ],
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'default_deposit' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'default_fee_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'default_deposit_terms' => ['required', Rule::enum(DepositTerms::class)],
            'requires_compliance_notice' => ['boolean'],
            'is_visible' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:300'],
        ]);

        $before = $category->only(array_keys($this->columns($data)));
        $category->fill($this->columns($data))->save();

        Audit::record('catalogue', 'category_updated', "Updated category {$category->name}", $category, null, [
            'old' => array_intersect_key($before, $category->getChanges()),
            'attributes' => array_diff_key($category->getChanges(), ['updated_at' => true]),
        ]);

        $this->toast('Category saved.');

        return back();
    }

    public function storeField(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateField($request);
        $key = Str::snake(Str::ascii($data['label']));

        if ($category->attributeDefinitions()->where('key', $key)->exists()) {
            return back()->withErrors(['label' => __('This category already has a field with that name.')]);
        }

        $field = $category->attributeDefinitions()->create($this->fieldColumns($data) + [
            'key' => $key,
            'sort_order' => (int) $category->attributeDefinitions()->max('sort_order') + 1,
        ]);

        Audit::record('catalogue', 'field_created', "Added field “{$field->label}” to {$category->name}", $field);
        $this->toast('Field added.');

        return back();
    }

    public function updateField(Request $request, Category $category, CategoryAttribute $field): RedirectResponse
    {
        abort_unless($field->category_id === $category->id, 404);

        // The key never changes: listing values are stored against it.
        $field->fill($this->fieldColumns($this->validateField($request)))->save();

        Audit::record('catalogue', 'field_updated', "Updated field “{$field->label}” in {$category->name}", $field, null, [
            'attributes' => array_diff_key($field->getChanges(), ['updated_at' => true]),
        ]);
        $this->toast('Field saved.');

        return back();
    }

    public function destroyField(Category $category, CategoryAttribute $field): RedirectResponse
    {
        abort_unless($field->category_id === $category->id, 404);

        $label = $field->label;
        $field->delete();

        Audit::record('catalogue', 'field_deleted', "Removed field “{$label}” from {$category->name}", $category);
        $this->toast('Field removed.');

        return back();
    }

    public function moveField(Request $request, Category $category, CategoryAttribute $field): RedirectResponse
    {
        abort_unless($field->category_id === $category->id, 404);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $fields = $category->attributeDefinitions()->get()->values();
        $index = $fields->search(fn (CategoryAttribute $f) => $f->id === $field->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($fields[$swap])) {
            $ordered = $fields->all();
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach ($ordered as $position => $f) {
                $f->forceFill(['sort_order' => $position])->saveQuietly();
            }
        }

        return back();
    }

    public function storeDocument(Request $request, Category $category): RedirectResponse
    {
        $document = $category->documentTypes()->create($this->documentColumns($this->validateDocument($request)) + [
            'sort_order' => (int) $category->documentTypes()->max('sort_order') + 1,
        ]);

        Audit::record('catalogue', 'document_type_created', "Added document type “{$document->name}” to {$category->name}", $document);
        $this->toast('Document type added.');

        return back();
    }

    public function updateDocument(Request $request, Category $category, DocumentType $document): RedirectResponse
    {
        abort_unless($document->category_id === $category->id, 404);

        $document->fill($this->documentColumns($this->validateDocument($request)))->save();

        Audit::record('catalogue', 'document_type_updated', "Updated document type “{$document->name}” in {$category->name}", $document, null, [
            'attributes' => array_diff_key($document->getChanges(), ['updated_at' => true]),
        ]);
        $this->toast('Document type saved.');

        return back();
    }

    public function destroyDocument(Category $category, DocumentType $document): RedirectResponse
    {
        abort_unless($document->category_id === $category->id, 404);

        $name = $document->name;
        $document->delete();

        Audit::record('catalogue', 'document_type_deleted', "Removed document type “{$name}” from {$category->name}", $category);
        $this->toast('Document type removed.');

        return back();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'default_deposit' => (int) $data['default_deposit'],
            'default_fee_bps' => (int) round(((float) $data['default_fee_percent']) * 100),
            'default_deposit_terms' => $data['default_deposit_terms'],
            'requires_compliance_notice' => (bool) ($data['requires_compliance_notice'] ?? false),
            'is_visible' => (bool) ($data['is_visible'] ?? false),
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function validateField(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'unit' => ['nullable', 'string', 'max:20'],
            'options' => ['nullable', 'required_if:type,select', 'string', 'max:2000'],
            'required' => ['boolean'],
            'on_card' => ['boolean'],
            'key_facts' => ['boolean'],
            'filterable' => ['boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fieldColumns(array $data): array
    {
        $options = $data['type'] === AttributeType::Select->value
            ? array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['options'] ?? '')) ?: [])))
            : null;

        return [
            'label' => $data['label'],
            'type' => $data['type'],
            'unit' => $data['unit'] ?? null,
            'options' => $options,
            'is_required' => (bool) ($data['required'] ?? false),
            'show_on_card' => (bool) ($data['on_card'] ?? false),
            'show_in_key_facts' => (bool) ($data['key_facts'] ?? false),
            'is_filterable' => (bool) ($data['filterable'] ?? false),
        ];
    }

    /** @return array<string, mixed> */
    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mandatory' => ['boolean'],
            'visibility' => ['required', Rule::enum(DocumentVisibility::class)],
            'formats' => ['required', 'array', 'min:1'],
            'formats.*' => [Rule::in(self::FORMATS)],
            'max_size_mb' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function documentColumns(array $data): array
    {
        return [
            'name' => $data['name'],
            'is_mandatory' => (bool) ($data['mandatory'] ?? false),
            'visibility' => $data['visibility'],
            'accepted_formats' => array_values(array_unique((array) $data['formats'])),
            'max_size_mb' => (int) $data['max_size_mb'],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $n = 2;
        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);
    }
}
