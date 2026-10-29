<?php

namespace Database\Seeders;

use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Catalogue\Models\Province;
use App\Domains\Catalogue\Support\CityCoordinates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starting catalogue (sprint 3 spec 4.2–4.5, revision 2). Idempotent:
 * existing categories, fields and document types are left as admins
 * changed them; only missing ones are added. Deposit and fee defaults are
 * placeholders until Thabekhulu confirms them.
 */
class CatalogueSeeder extends Seeder
{
    private const ANY = ['pdf', 'jpg', 'png'];

    public function run(): void
    {
        foreach ($this->categories() as $order => $data) {
            $category = Category::firstOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'description' => $data['description'],
                'icon' => $data['icon'],
                'default_deposit' => $data['deposit'],
                'default_fee_bps' => 2000,
                'default_deposit_terms' => $data['terms'],
                'requires_compliance_notice' => $data['notice'],
                'sort_order' => $order,
            ]);

            foreach ($data['attributes'] as $i => [$key, $label, $type, $unit, $options, $required, $card, $filter]) {
                $category->attributeDefinitions()->firstOrCreate(['key' => $key], [
                    'label' => $label, 'type' => $type, 'unit' => $unit, 'options' => $options,
                    'is_required' => $required, 'show_on_card' => $card, 'show_in_key_facts' => true,
                    'is_filterable' => $filter, 'sort_order' => $i,
                ]);
            }

            foreach ($data['documents'] as $i => [$name, $mandatory, $visibility]) {
                $category->documentTypes()->firstOrCreate(['name' => $name], [
                    'is_mandatory' => $mandatory, 'visibility' => $visibility,
                    'accepted_formats' => self::ANY, 'max_size_mb' => 20, 'sort_order' => $i,
                ]);
            }
        }

        foreach ($this->locations() as [$code, $province, $cities]) {
            $p = Province::firstOrCreate(['code' => $code], ['name' => $province, 'slug' => Str::slug($province)]);

            foreach ($cities as $city) {
                [$lat, $lng] = CityCoordinates::for($city) ?? [null, null];
                City::firstOrCreate(['province_id' => $p->id, 'slug' => Str::slug($city)], ['name' => $city, 'latitude' => $lat, 'longitude' => $lng]);
            }
        }
    }

    /**
     * Attribute rows: key, label, type, unit, options, required, on card, filterable.
     * Document rows: name, mandatory, visibility.
     *
     * @return list<array<string, mixed>>
     */
    private function categories(): array
    {
        $yesPending = ['Granted', 'Pending', 'Not required'];

        return [
            [
                'name' => 'Homes', 'slug' => 'homes', 'icon' => 'house', 'deposit' => 50000, 'terms' => 'applied', 'notice' => false,
                'description' => 'Houses, townhouses and apartments, many renovated by Thabekhulu before sale.',
                'attributes' => [
                    ['bedrooms', 'Bedrooms', 'number', null, null, true, true, true],
                    ['bathrooms', 'Bathrooms', 'number', null, null, true, true, false],
                    ['garages', 'Garages', 'number', null, null, false, false, false],
                    ['erf_size', 'Erf size', 'number', 'm²', null, false, true, false],
                    ['floor_area', 'Floor area', 'number', 'm²', null, false, false, false],
                    ['levies', 'Levies', 'number', 'R/month', null, false, false, false],
                    ['rates', 'Rates and taxes', 'number', 'R/month', null, false, false, false],
                ],
                'documents' => [
                    ['Title deed', true, 'verified'], ['SG diagram', false, 'verified'], ['Zoning certificate', false, 'verified'],
                    ['Approved building plans', false, 'verified'], ['Electrical compliance certificate', true, 'verified'],
                    ['Rates clearance status', false, 'internal'],
                ],
            ],
            [
                'name' => 'Land', 'slug' => 'land', 'icon' => 'trees', 'deposit' => 50000, 'terms' => 'applied', 'notice' => false,
                'description' => 'Farms, plots and development land with zoning and title documents ready to review.',
                'attributes' => [
                    ['hectares', 'Area', 'number', 'ha', null, true, true, true],
                    ['zoning', 'Zoning', 'select', null, ['Agricultural', 'Residential', 'Commercial', 'Industrial', 'Mixed use'], true, true, true],
                    ['water_rights', 'Water rights', 'boolean', null, null, false, false, true],
                    ['services', 'Services available', 'text', null, null, false, false, false],
                    ['access', 'Road access', 'text', null, null, false, false, false],
                ],
                'documents' => [
                    ['Title deed', true, 'verified'], ['SG diagram', true, 'verified'], ['Zoning certificate', true, 'verified'],
                    ['Water rights documents', false, 'verified'], ['Environmental reports', false, 'verified'],
                ],
            ],
            [
                'name' => 'Mining', 'slug' => 'mining', 'icon' => 'pickaxe', 'deposit' => 1200000, 'terms' => 'security', 'notice' => true,
                'description' => 'Mining and prospecting rights and mining properties, sold subject to verification and regulatory approval.',
                'attributes' => [
                    ['commodity', 'Commodity', 'select', null, ['Coal', 'Gold', 'Platinum group metals', 'Chrome', 'Manganese', 'Iron ore', 'Diamonds', 'Copper', 'Other'], true, true, true],
                    ['right_type', 'Right type', 'select', null, ['Mining right', 'Prospecting right', 'Mining permit'], true, true, true],
                    ['right_reference', 'Right reference number', 'text', null, null, true, false, false],
                    ['right_status', 'Right status', 'select', null, ['Granted', 'Application pending', 'Renewal in progress'], true, false, true],
                    ['right_expiry', 'Right expiry date', 'date', null, null, false, false, false],
                    ['area', 'Area', 'number', 'ha', null, true, true, false],
                    ['resource_statement', "Competent person's report available", 'boolean', null, null, false, false, true],
                    ['infrastructure', 'Infrastructure on site', 'text', null, null, false, false, false],
                    ['environmental_authorisation', 'Environmental authorisation', 'select', null, $yesPending, true, false, false],
                    ['water_use_licence', 'Water use licence', 'select', null, $yesPending, false, false, false],
                    ['hdsa_ownership', 'HDSA ownership', 'number', '%', null, false, false, false],
                    ['deal_structure', 'Deal structure', 'select', null, ['Transfer of right (section 11 consent)', 'Share sale', 'Asset sale'], true, false, true],
                ],
                'documents' => [
                    ['Mining or prospecting right', true, 'approved_nda'], ['Environmental authorisation', true, 'approved_nda'],
                    ['Environmental management programme', false, 'approved_nda'], ["Competent person's report", false, 'approved_nda'],
                    ['Water use licence', false, 'approved_nda'], ["Seller's authority to sell", true, 'internal'],
                    ['Company documents (CIPC)', false, 'internal'],
                ],
            ],
            [
                'name' => 'Malls', 'slug' => 'malls', 'icon' => 'store', 'deposit' => 50000, 'terms' => 'applied', 'notice' => false,
                'description' => 'Shopping centres and retail property with rent rolls and lease schedules for bidders.',
                'attributes' => [
                    ['gla', 'Gross lettable area', 'number', 'm²', null, true, true, false],
                    ['tenants', 'Tenants', 'number', null, null, true, true, false],
                    ['occupancy', 'Occupancy', 'number', '%', null, false, true, false],
                    ['net_operating_income', 'Net operating income', 'number', 'R/year', null, false, false, false],
                    ['anchor_tenant', 'Anchor tenant', 'text', null, null, false, false, false],
                ],
                'documents' => [
                    ['Title deed', true, 'verified'], ['Rent roll', true, 'approved_nda'], ['Lease schedule', false, 'approved_nda'],
                    ['Operating cost statements', false, 'approved_nda'], ['Approved building plans', false, 'verified'],
                    ['Compliance certificates', false, 'verified'],
                ],
            ],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: list<string>}> */
    private function locations(): array
    {
        return [
            ['GP', 'Gauteng', ['Johannesburg', 'Pretoria', 'Midrand', 'Centurion', 'Sandton', 'Soweto', 'Germiston', 'Vereeniging']],
            ['WC', 'Western Cape', ['Cape Town', 'Stellenbosch', 'Paarl', 'George', 'Knysna', 'Hermanus', 'Worcester']],
            ['KZN', 'KwaZulu-Natal', ['Durban', 'Pietermaritzburg', 'Umhlanga', 'Ballito', 'Richards Bay', 'Newcastle']],
            ['EC', 'Eastern Cape', ['Gqeberha', 'East London', 'Mthatha', 'Makhanda']],
            ['FS', 'Free State', ['Bloemfontein', 'Welkom', 'Bethlehem', 'Sasolburg']],
            ['LP', 'Limpopo', ['Polokwane', 'Tzaneen', 'Thohoyandou', 'Mokopane', 'Lephalale']],
            ['MP', 'Mpumalanga', ['Mbombela', 'Emalahleni', 'Middelburg', 'Secunda', 'Barberton']],
            ['NW', 'North West', ['Rustenburg', 'Mahikeng', 'Klerksdorp', 'Potchefstroom', 'Brits']],
            ['NC', 'Northern Cape', ['Kimberley', 'Upington', 'Kathu', 'Springbok', 'Kuruman']],
        ];
    }
}
