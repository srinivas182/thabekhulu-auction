<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Catalogue\Enums\DepositTerms;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Catalogue\Models\Province;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $this->mining = Category::where('slug', 'mining')->firstOrFail();
});

it('seeds the four categories, mine profile fields, document types and locations', function () {
    expect(Category::orderBy('sort_order')->pluck('slug')->all())->toBe(['homes', 'land', 'mining', 'malls'])
        ->and($this->mining->default_deposit)->toBe(1200000)
        ->and($this->mining->default_deposit_terms)->toBe(DepositTerms::Security)
        ->and($this->mining->requires_compliance_notice)->toBeTrue()
        ->and($this->mining->attributeDefinitions()->pluck('key')->all())->toContain('commodity', 'right_type', 'right_reference', 'deal_structure', 'hdsa_ownership')
        ->and($this->mining->documentTypes()->where('visibility', 'approved_nda')->count())->toBe(5)
        ->and(City::count())->toBeGreaterThan(40);
});

it('can be seeded again without duplicating or overwriting admin changes', function () {
    $this->mining->update(['default_deposit' => 1500000]);

    $this->seed(CatalogueSeeder::class);

    expect(Category::count())->toBe(4)
        ->and($this->mining->fresh()->default_deposit)->toBe(1500000);
});

it('is limited to staff with categories.manage', function () {
    $support = User::factory()->staff(SystemRole::Support)->create();

    $this->actingAs($support)->get(route('admin.categories.index'))->assertForbidden();
    $this->actingAs($support)->get(route('admin.locations.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.categories.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/categories/index')->has('categories', 4));
});

it('saves category defaults with the fee in basis points and audits the change', function () {
    $this->actingAs($this->admin)->put(route('admin.categories.update', $this->mining), [
        'name' => 'Mining', 'default_deposit' => 1250000, 'default_fee_percent' => 7.5,
        'default_deposit_terms' => 'security', 'requires_compliance_notice' => true, 'is_visible' => true,
    ])->assertSessionHasNoErrors();

    $mining = $this->mining->fresh();
    expect($mining->default_fee_bps)->toBe(750)
        ->and($mining->default_deposit)->toBe(1250000);

    $entry = AuditEntry::where('event', 'category_updated')->firstOrFail();
    expect($entry->properties['attributes'])->toHaveKeys(['default_deposit', 'default_fee_bps']);
});

it('rejects an unknown deposit treatment and a fee above 50%', function () {
    $this->actingAs($this->admin)->put(route('admin.categories.update', $this->mining), [
        'name' => 'Mining', 'default_deposit' => 1, 'default_fee_percent' => 60, 'default_deposit_terms' => 'forfeit',
    ])->assertSessionHasErrors(['default_fee_percent', 'default_deposit_terms']);
});

it('creates new categories hidden with placeholder defaults', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Industrial'])
        ->assertRedirect(route('admin.categories.edit', 'industrial'));

    $industrial = Category::where('slug', 'industrial')->firstOrFail();
    expect($industrial->is_visible)->toBeFalse()
        ->and($industrial->default_fee_bps)->toBe(2000);
});

it('adds key-fact fields with a fixed key and requires choices for lists', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.fields.store', $this->mining), [
        'label' => 'Life of mine', 'type' => 'number', 'unit' => 'years', 'key_facts' => true,
    ])->assertSessionHasNoErrors();

    $field = $this->mining->attributeDefinitions()->where('key', 'life_of_mine')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.categories.fields.update', [$this->mining, $field]), [
        'label' => 'Remaining life of mine', 'type' => 'number', 'unit' => 'years',
    ]);
    expect($field->fresh()->key)->toBe('life_of_mine')
        ->and($field->fresh()->label)->toBe('Remaining life of mine');

    $this->actingAs($this->admin)->post(route('admin.categories.fields.store', $this->mining), [
        'label' => 'Grade', 'type' => 'select',
    ])->assertSessionHasErrors('options');

    $this->actingAs($this->admin)->post(route('admin.categories.fields.store', $this->mining), [
        'label' => 'Commodity', 'type' => 'text',
    ])->assertSessionHasErrors('label');
});

it('stores list choices one per line', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.fields.store', $this->mining), [
        'label' => 'Mining method', 'type' => 'select', 'options' => "Open pit\n Underground \n\nAlluvial",
    ]);

    expect($this->mining->attributeDefinitions()->where('key', 'mining_method')->firstOrFail()->options)
        ->toBe(['Open pit', 'Underground', 'Alluvial']);
});

it('reorders fields', function () {
    [$first, $second] = $this->mining->attributeDefinitions()->take(2)->get()->all();

    $this->actingAs($this->admin)->post(route('admin.categories.fields.move', [$this->mining, $second]), ['direction' => 'up']);

    expect($this->mining->attributeDefinitions()->first()->id)->toBe($second->id);
});

it('does not let a field from one category be edited through another', function () {
    $homesField = Category::where('slug', 'homes')->firstOrFail()->attributeDefinitions()->firstOrFail();

    $this->actingAs($this->admin)->delete(route('admin.categories.fields.destroy', [$this->mining, $homesField]))->assertNotFound();
});

it('manages document types with a visibility level and formats', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.documents.store', $this->mining), [
        'name' => 'Social and labour plan', 'mandatory' => true, 'visibility' => 'approved_nda',
        'formats' => ['pdf'], 'max_size_mb' => 30,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->post(route('admin.categories.documents.store', $this->mining), [
        'name' => 'Public brochure', 'visibility' => 'public', 'formats' => [], 'max_size_mb' => 5,
    ])->assertSessionHasErrors(['visibility', 'formats']);
});

it('adds, rejects duplicate and hides cities', function () {
    $limpopo = Province::where('code', 'LP')->firstOrFail();

    $this->actingAs($this->admin)->post(route('admin.locations.cities.store'), ['province_id' => $limpopo->id, 'name' => 'Musina'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.locations.cities.store'), ['province_id' => $limpopo->id, 'name' => 'Polokwane'])->assertSessionHasErrors('name');

    $musina = City::where('slug', 'musina')->firstOrFail();
    $this->actingAs($this->admin)->post(route('admin.locations.cities.toggle', $musina));

    expect($musina->fresh()->is_active)->toBeFalse();
});
