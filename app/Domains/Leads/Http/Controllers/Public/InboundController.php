<?php

namespace App\Domains\Leads\Http\Controllers\Public;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Leads\Models\Enquiry;
use App\Domains\Leads\Models\Lead;
use App\Domains\Leads\Notifications\OfficeAlert;
use App\Domains\Listings\Models\Listing;
use App\Domains\Settings\Settings\GeneralSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "Sell with us" and contact forms. Protected by a honeypot field
 * and a rate limit; submissions alert the office by email.
 */
class InboundController extends Controller
{
    public function sellForm(): Response
    {
        return Inertia::render('public/sell-with-us', [
            'types' => Category::where('is_visible', true)->orderBy('sort_order')->get(['name', 'slug'])
                ->map(fn (Category $c) => ['value' => $c->slug, 'label' => $c->name])->push(['value' => 'other', 'label' => 'Other']),
        ]);
    }

    public function storeLead(Request $request, GeneralSettings $general): RedirectResponse
    {
        if ($this->isBot($request)) {
            return $this->thanks('Thank you. Our team will contact you within two working days.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^(\+27|0)[6-8][0-9]{8}$/'],
            'property_type' => ['required', 'string', 'max:40', Rule::in(Category::pluck('slug')->push('other')->all())],
            'location' => ['required', 'string', 'max:120'],
            'asking_price' => ['nullable', 'integer', 'min:0'],
            'description' => ['required', 'string', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'consent' => ['accepted'],
        ], [
            'mobile.regex' => __('Enter a South African mobile number, for example 082 123 4567.'),
            'consent.accepted' => __('Please agree so we can contact you about your property.'),
        ]);

        $lead = Lead::create([
            'name' => $data['name'], 'email' => strtolower($data['email']),
            'mobile' => str_starts_with($data['mobile'], '0') ? '+27'.substr($data['mobile'], 1) : $data['mobile'],
            'property_type' => $data['property_type'], 'location' => $data['location'], 'asking_price' => $data['asking_price'] ?? null,
            'description' => strip_tags($data['description']), 'consent_at' => now(), 'ip_address' => $request->ip(),
        ]);

        /** @var list<UploadedFile> $photos */
        $photos = $request->file('photos', []);
        foreach ($photos as $photo) {
            $lead->addMedia($photo)->usingFileName(Str::random(24).'.'.strtolower($photo->getClientOriginalExtension()))->toMediaCollection('photos');
        }

        Audit::record('leads', 'received', "New “Sell with us” lead from {$lead->name}", $lead, null);
        $this->alertOffice($general, "New property lead: {$lead->location}", "{$lead->name} wants to sell a property ({$lead->property_type}) in {$lead->location}.", route('admin.leads.show', $lead));

        return $this->thanks('Thank you. Our team will contact you within two working days.');
    }

    public function storeEnquiry(Request $request, GeneralSettings $general): RedirectResponse
    {
        if ($this->isBot($request)) {
            return $this->thanks('Thank you for your message. We’ll reply by email.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:3000'],
            'listing' => ['nullable', 'string', Rule::exists('listings', 'ulid')],
        ]);

        $enquiry = Enquiry::create([
            'name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null,
            'subject' => strip_tags($data['subject']), 'message' => strip_tags($data['message']), 'ip_address' => $request->ip(),
            'listing_id' => isset($data['listing']) ? Listing::where('ulid', $data['listing'])->value('id') : null,
        ]);

        Audit::record('enquiries', 'received', "New enquiry from {$enquiry->name}: {$enquiry->subject}", $enquiry, null);
        $this->alertOffice($general, "New enquiry: {$enquiry->subject}", "{$enquiry->name} sent a message from the website.", route('admin.enquiries.show', $enquiry));

        return $this->thanks('Thank you for your message. We’ll reply by email.');
    }

    /** Bots fill the hidden "website" field; they get a normal-looking thank you and nothing is stored. */
    private function isBot(Request $request): bool
    {
        return filled($request->input('website'));
    }

    private function alertOffice(GeneralSettings $general, string $subject, string $summary, string $url): void
    {
        if (filled($general->contact_email)) {
            Notification::route('mail', $general->contact_email)->notify(new OfficeAlert($subject, $summary, $url));
        }
    }

    private function thanks(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);
        Inertia::flash('submitted', true);

        return back();
    }
}
