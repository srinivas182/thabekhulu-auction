<?php

use App\Domains\Access\Http\Controllers\RoleController;
use App\Domains\Audit\Http\Controllers\AuditController;
use App\Domains\Catalogue\Http\Controllers\CategoryController;
use App\Domains\Catalogue\Http\Controllers\LocationController;
use App\Domains\Cms\Http\Controllers\Admin\CmsController;
use App\Domains\Cms\Http\Controllers\Admin\MediaContentController;
use App\Domains\Cms\Http\Controllers\Public\HomeController;
use App\Domains\Cms\Http\Controllers\Public\InsightsController;
use App\Domains\Cms\Http\Controllers\Public\PageController;
use App\Domains\Identity\Http\Controllers\DashboardRedirectController;
use App\Domains\Identity\Http\Controllers\InvitationController;
use App\Domains\Identity\Http\Controllers\StaffController;
use App\Domains\Leads\Http\Controllers\InboxController;
use App\Domains\Leads\Http\Controllers\Public\InboundController;
use App\Domains\Listings\Http\Controllers\ListingAssetController;
use App\Domains\Listings\Http\Controllers\ListingController;
use App\Domains\Listings\Http\Controllers\ListingWorkflowController;
use App\Domains\Listings\Http\Controllers\Public\AuctionController;
use App\Domains\Platform\Http\Controllers\AdminDashboardController;
use App\Domains\Platform\Http\Controllers\FeatureController;
use App\Domains\Quotes\Http\Controllers\Public\AccountQuotesController;
use App\Domains\Quotes\Http\Controllers\Public\ClientQuoteController;
use App\Domains\Quotes\Http\Controllers\QuoteController;
use App\Domains\Quotes\Http\Controllers\QuoteReportController;
use App\Domains\Quotes\Http\Controllers\QuoteSetupController;
use App\Domains\Quotes\Http\Controllers\QuoteWorkflowController;
use App\Domains\Settings\Http\Controllers\SettingsController;
use App\Domains\Theme\Http\Controllers\ThemeController;
use App\Domains\Verification\Http\Controllers\BankAccountController;
use App\Domains\Verification\Http\Controllers\BuyerVerificationController;
use App\Domains\Verification\Http\Controllers\KycReviewController;
use App\Domains\Verification\Http\Controllers\ProviderController;
use App\Domains\Verification\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| Public website
*/
Route::get('/', HomeController::class)->name('home');
Route::get('sitemap.xml', [AuctionController::class, 'sitemap'])->name('sitemap');
Route::post('webhooks/verification/{provider}', WebhookController::class)->middleware('throttle:120,1')->name('webhooks.verification');
Route::get('insights', [InsightsController::class, 'index'])->name('insights.index');
Route::get('insights/{slug}', [InsightsController::class, 'show'])->name('insights.show');
Route::get('gallery', [InsightsController::class, 'gallery'])->name('gallery');
Route::get('sell-with-us', [InboundController::class, 'sellForm'])->name('sell');
Route::post('sell-with-us', [InboundController::class, 'storeLead'])->middleware('throttle:public-forms')->name('sell.store');
Route::post('enquiries', [InboundController::class, 'storeEnquiry'])->middleware('throttle:public-forms')->name('enquiries.store');
Route::get('auctions', [AuctionController::class, 'index'])->name('auctions.index');
Route::get('auctions/{category:slug}', [AuctionController::class, 'index'])->name('auctions.category');
Route::get('auctions/{category:slug}/{city}', [AuctionController::class, 'index'])->name('auctions.city');
Route::get('auctions/{category:slug}/{city}/{listing}', [AuctionController::class, 'show'])->name('auctions.show');

/*
| Staff invitations (signed-out users accept their invite here)
*/
Route::middleware(['guest', 'throttle:invitations'])->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [InvitationController::class, 'store'])->name('invitations.accept');
});

/*
| Signed-in home: routes each account type to its own area
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
    // Buyer area placeholder; Sprint 4 builds the buyer dashboard.
    Route::inertia('account', 'account/dashboard')->name('account');
    Route::get('account/quotes', AccountQuotesController::class)->name('account.quotes');
    Route::get('account/bank', [BankAccountController::class, 'show'])->name('account.bank');
    Route::post('account/bank', [BankAccountController::class, 'store'])->middleware('throttle:10,1')->name('account.bank.store');
    Route::prefix('account/verification')->name('account.verification')->group(function () {
        Route::get('/', [BuyerVerificationController::class, 'show']);
        Route::post('identity', [BuyerVerificationController::class, 'identity'])->name('.identity');
        Route::post('selfie', [BuyerVerificationController::class, 'selfie'])->name('.selfie');
        Route::post('address', [BuyerVerificationController::class, 'address'])->name('.address');
        Route::post('submit', [BuyerVerificationController::class, 'submit'])->middleware('throttle:5,1')->name('.submit');
    });
});

/*
| Quotations for clients: signed, expiring links (no account needed)
*/
Route::middleware('signed')->prefix('q')->name('quotes.public.')->group(function () {
    Route::get('{quote}', [ClientQuoteController::class, 'show'])->name('show');
    Route::get('{quote}/pdf', [ClientQuoteController::class, 'pdf'])->name('pdf');
    Route::post('{quote}/accept', [ClientQuoteController::class, 'accept'])->middleware('throttle:10,1')->name('accept');
    Route::post('{quote}/decline', [ClientQuoteController::class, 'decline'])->middleware('throttle:10,1')->name('decline');
});

/*
| Back office: staff only, two-factor required, every page permission-checked
*/
Route::middleware(['auth', 'verified', 'staff', 'staff.2fa', 'admin.ip'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

        Route::get('staff', [StaffController::class, 'index'])->middleware('can:staff.view')->name('staff.index');
        Route::post('staff/invitations', [StaffController::class, 'invite'])->middleware('can:staff.invite')->name('staff.invite');
        Route::delete('staff/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])->middleware('can:staff.invite')->name('staff.invitations.revoke');
        Route::put('staff/{user}/role', [StaffController::class, 'changeRole'])->middleware('can:staff.invite')->name('staff.role');
        Route::post('staff/{user}/deactivate', [StaffController::class, 'deactivate'])->middleware('can:staff.deactivate')->name('staff.deactivate');
        Route::post('staff/{user}/reactivate', [StaffController::class, 'reactivate'])->middleware('can:staff.deactivate')->name('staff.reactivate');

        Route::get('roles', [RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
        Route::middleware('can:roles.manage')->group(function () {
            Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        Route::get('listings', [ListingController::class, 'index'])->middleware('can:listings.view')->name('listings.index');
        Route::post('listings', [ListingController::class, 'store'])->middleware('can:listings.create')->name('listings.store');
        Route::get('listings/{listing}', [ListingController::class, 'edit'])->middleware('can:listings.view')->name('listings.edit');
        Route::put('listings/{listing}/sections/{section}', [ListingController::class, 'update'])->middleware('can:listings.edit')->name('listings.update');
        Route::delete('listings/{listing}', [ListingController::class, 'destroy'])->middleware('can:listings.delete')->name('listings.destroy');

        Route::middleware('can:listings.edit')->group(function () {
            Route::post('listings/{listing}/media/{collection}', [ListingAssetController::class, 'uploadMedia'])->name('listings.media.store');
            Route::put('listings/{listing}/media/{collection}/order', [ListingAssetController::class, 'reorderMedia'])->name('listings.media.order');
            Route::put('listings/{listing}/media/item/{media}', [ListingAssetController::class, 'updateMedia'])->name('listings.media.update');
            Route::delete('listings/{listing}/media/item/{media}', [ListingAssetController::class, 'destroyMedia'])->name('listings.media.destroy');
            Route::post('listings/{listing}/documents/{type}', [ListingAssetController::class, 'uploadDocument'])->name('listings.documents.store');
            Route::delete('listings/{listing}/documents/{document}', [ListingAssetController::class, 'destroyDocument'])->name('listings.documents.destroy');
        });
        Route::get('listings/{listing}/documents/{document}', [ListingAssetController::class, 'downloadDocument'])->middleware('can:listings.view')->name('listings.documents.download');

        Route::post('listings/{listing}/submit', [ListingWorkflowController::class, 'submit'])->middleware('can:listings.submit')->name('listings.submit');
        Route::middleware('can:listings.publish')->group(function () {
            Route::post('listings/{listing}/approve', [ListingWorkflowController::class, 'approve'])->name('listings.approve');
            Route::post('listings/{listing}/request-changes', [ListingWorkflowController::class, 'requestChanges'])->name('listings.request-changes');
            Route::post('listings/{listing}/withdraw', [ListingWorkflowController::class, 'withdraw'])->name('listings.withdraw');
        });
        Route::post('listings/{listing}/reopen', [ListingWorkflowController::class, 'reopen'])->middleware('can:listings.edit')->name('listings.reopen');

        Route::middleware('can:compliance.signoff')->group(function () {
            Route::post('listings/{listing}/compliance/{item}', [ListingAssetController::class, 'signOff'])->name('listings.compliance.signoff');
            Route::delete('listings/{listing}/compliance/{item}', [ListingAssetController::class, 'withdrawSignOff'])->name('listings.compliance.withdraw');
        });

        Route::middleware('can:cms.manage')->prefix('cms')->name('cms.')->group(function () {
            Route::get('pages', [CmsController::class, 'pages'])->name('pages');
            Route::post('pages', [CmsController::class, 'storePage'])->name('pages.store');
            Route::get('pages/{page}', [CmsController::class, 'editPage'])->name('pages.edit');
            Route::put('pages/{page}', [CmsController::class, 'updatePage'])->name('pages.update');
            Route::delete('pages/{page}', [CmsController::class, 'destroyPage'])->name('pages.destroy');
            Route::get('menus', [CmsController::class, 'menus'])->name('menus');
            Route::put('menus/{menu}', [CmsController::class, 'updateMenu'])->name('menus.update');
            Route::get('collections', [CmsController::class, 'collections'])->name('collections');
            Route::post('collections', [CmsController::class, 'saveCollection'])->name('collections.store');
            Route::put('collections/{collection}', [CmsController::class, 'saveCollection'])->name('collections.update');
            Route::delete('collections/{collection}', [CmsController::class, 'destroyCollection'])->name('collections.destroy');
            Route::get('home', [CmsController::class, 'home'])->name('home');
            Route::post('pages/{page}/images', [MediaContentController::class, 'uploadPageImage'])->name('pages.images');
            Route::get('posts', [MediaContentController::class, 'posts'])->name('posts');
            Route::post('posts', [MediaContentController::class, 'storePost'])->name('posts.store');
            Route::get('posts/{post}', [MediaContentController::class, 'editPost'])->name('posts.edit');
            Route::post('posts/{post}', [MediaContentController::class, 'updatePost'])->name('posts.update');
            Route::delete('posts/{post}', [MediaContentController::class, 'destroyPost'])->name('posts.destroy');
            Route::get('galleries', [MediaContentController::class, 'galleries'])->name('galleries');
            Route::post('galleries', [MediaContentController::class, 'saveGallery'])->name('galleries.store');
            Route::put('galleries/{gallery}', [MediaContentController::class, 'saveGallery'])->name('galleries.update');
            Route::delete('galleries/{gallery}', [MediaContentController::class, 'destroyGallery'])->name('galleries.destroy');
            Route::post('galleries/{gallery}/images', [MediaContentController::class, 'uploadGalleryImages'])->name('galleries.images.store');
            Route::put('galleries/{gallery}/images/{media}', [MediaContentController::class, 'updateGalleryImage'])->name('galleries.images.update');
            Route::delete('galleries/{gallery}/images/{media}', [MediaContentController::class, 'destroyGalleryImage'])->name('galleries.images.destroy');
            Route::put('home', [CmsController::class, 'updateHome'])->name('home.update');
        });

        Route::middleware('can:quotes.create')->prefix('quotes')->name('quotes.')->group(function () {
            Route::get('/', [QuoteController::class, 'index'])->name('index');
            Route::post('/', [QuoteController::class, 'store'])->name('store');
            Route::get('clients', [QuoteSetupController::class, 'clients'])->name('clients');
            Route::post('clients', [QuoteSetupController::class, 'saveClient'])->name('clients.store');
            Route::put('clients/{client}', [QuoteSetupController::class, 'saveClient'])->name('clients.update');
            Route::middleware('can:quotes.settings')->group(function () {
                Route::get('catalogue', [QuoteSetupController::class, 'catalogue'])->name('catalogue');
                Route::post('catalogue', [QuoteSetupController::class, 'saveItem'])->name('catalogue.store');
                Route::put('catalogue/{item}', [QuoteSetupController::class, 'saveItem'])->name('catalogue.update');
                Route::get('templates', [QuoteSetupController::class, 'templates'])->name('templates');
                Route::put('templates/{template}', [QuoteSetupController::class, 'updateTemplate'])->name('templates.update');
                Route::delete('templates/{template}', [QuoteSetupController::class, 'destroyTemplate'])->name('templates.destroy');
                Route::get('settings', [QuoteSetupController::class, 'settings'])->name('settings');
                Route::put('settings', [QuoteSetupController::class, 'updateSettings'])->name('settings.update');
            });
            Route::middleware('can:quotes.view_all')->group(function () {
                Route::get('reports', [QuoteReportController::class, 'index'])->name('reports');
                Route::get('reports/export', [QuoteReportController::class, 'export'])->name('reports.export');
            });
            Route::post('{quote}/submit', [QuoteWorkflowController::class, 'submit'])->name('submit');
            Route::post('{quote}/approve', [QuoteWorkflowController::class, 'approve'])->middleware('can:quotes.approve')->name('approve');
            Route::post('{quote}/return', [QuoteWorkflowController::class, 'returnToDraft'])->middleware('can:quotes.approve')->name('return');
            Route::post('{quote}/send', [QuoteWorkflowController::class, 'send'])->middleware('can:quotes.send')->name('send');
            Route::post('{quote}/version', [QuoteWorkflowController::class, 'newVersion'])->name('version');
            Route::get('{quote}', [QuoteController::class, 'edit'])->name('edit');
            Route::put('{quote}', [QuoteController::class, 'update'])->name('update');
            Route::delete('{quote}', [QuoteController::class, 'destroy'])->name('destroy');
            Route::get('{quote}/pdf', [QuoteController::class, 'pdf'])->name('pdf');
            Route::post('{quote}/template', [QuoteController::class, 'saveAsTemplate'])->middleware('can:quotes.settings')->name('template');
        });

        Route::middleware('can:kyc.view')->prefix('kyc')->name('kyc.')->group(function () {
            Route::get('/', [KycReviewController::class, 'index'])->name('index');
            Route::get('{verification}', [KycReviewController::class, 'show'])->name('show');
            Route::get('{verification}/documents/{document}', [KycReviewController::class, 'document'])->name('document');
            Route::post('{verification}/approve', [KycReviewController::class, 'approve'])->middleware('can:kyc.approve')->name('approve');
            Route::post('{verification}/reject', [KycReviewController::class, 'reject'])->middleware('can:kyc.reject')->name('reject');
            Route::post('{verification}/request-info', [KycReviewController::class, 'requestInfo'])->middleware('can:kyc.reject')->name('request-info');
            Route::post('{verification}/screen', [KycReviewController::class, 'screen'])->middleware('can:kyc.approve')->name('screen');
            Route::get('banks/{bank}/proof', [KycReviewController::class, 'bankProof'])->name('bank-proof');
            Route::post('banks/{bank}', [KycReviewController::class, 'decideBank'])->name('bank');
        });

        Route::middleware('can:verification.providers')->prefix('verification')->name('verification.')->group(function () {
            Route::get('providers', [ProviderController::class, 'index'])->name('providers');
            Route::put('providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
            Route::post('providers/{provider}/test', [ProviderController::class, 'test'])->name('providers.test');
            Route::put('routes', [ProviderController::class, 'routes'])->name('routes');
        });

        Route::get('leads', [InboxController::class, 'leads'])->middleware('can:leads.view')->name('leads.index');
        Route::get('leads/{lead}', [InboxController::class, 'showLead'])->middleware('can:leads.view')->name('leads.show');
        Route::put('leads/{lead}', [InboxController::class, 'updateLead'])->middleware('can:leads.manage')->name('leads.update');
        Route::get('leads/{lead}/photos/{media}', [InboxController::class, 'leadPhoto'])->middleware('can:leads.view')->name('leads.photo');
        Route::get('enquiries', [InboxController::class, 'enquiries'])->middleware('can:enquiries.view')->name('enquiries.index');
        Route::get('enquiries/{enquiry}', [InboxController::class, 'showEnquiry'])->middleware('can:enquiries.view')->name('enquiries.show');
        Route::post('enquiries/{enquiry}/reply', [InboxController::class, 'reply'])->middleware('can:enquiries.respond')->name('enquiries.reply');
        Route::post('enquiries/{enquiry}/close', [InboxController::class, 'close'])->middleware('can:enquiries.respond')->name('enquiries.close');

        Route::middleware('can:categories.manage')->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::post('categories/{category}/fields', [CategoryController::class, 'storeField'])->name('categories.fields.store');
            Route::put('categories/{category}/fields/{field}', [CategoryController::class, 'updateField'])->name('categories.fields.update');
            Route::delete('categories/{category}/fields/{field}', [CategoryController::class, 'destroyField'])->name('categories.fields.destroy');
            Route::post('categories/{category}/fields/{field}/move', [CategoryController::class, 'moveField'])->name('categories.fields.move');
            Route::post('categories/{category}/documents', [CategoryController::class, 'storeDocument'])->name('categories.documents.store');
            Route::put('categories/{category}/documents/{document}', [CategoryController::class, 'updateDocument'])->name('categories.documents.update');
            Route::delete('categories/{category}/documents/{document}', [CategoryController::class, 'destroyDocument'])->name('categories.documents.destroy');

            Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
            Route::post('locations/cities', [LocationController::class, 'store'])->name('locations.cities.store');
            Route::post('locations/cities/{city}/toggle', [LocationController::class, 'toggle'])->name('locations.cities.toggle');
        });

        Route::get('settings/{group?}', [SettingsController::class, 'edit'])
            ->whereIn('group', ['general', 'auction', 'security'])
            ->middleware('can:settings.view')->name('settings.edit');
        Route::put('settings/{group}', [SettingsController::class, 'update'])
            ->whereIn('group', ['general', 'auction', 'security'])
            ->middleware('can:settings.manage')->name('settings.update');

        Route::get('audit', [AuditController::class, 'index'])->middleware('can:audit.view')->name('audit.index');
        Route::get('audit/export', [AuditController::class, 'export'])->middleware('can:audit.export')->name('audit.export');

        Route::middleware('can:theme.manage')->prefix('theme/{scope}')->whereIn('scope', ['website', 'admin'])->name('theme.')->group(function () {
            Route::get('/', [ThemeController::class, 'edit'])->name('edit');
            Route::post('draft', [ThemeController::class, 'saveDraft'])->name('draft.save');
            Route::delete('draft', [ThemeController::class, 'discard'])->name('draft.discard');
            Route::post('publish', [ThemeController::class, 'publish'])->name('publish');
            Route::post('reset', [ThemeController::class, 'reset'])->name('reset');
            Route::post('versions/{version}/restore', [ThemeController::class, 'restore'])->name('restore');
        });

        Route::get('features', [FeatureController::class, 'index'])->middleware('can:features.manage')->name('features.index');
        Route::put('features/{flag}', [FeatureController::class, 'update'])->middleware('can:features.manage')->name('features.update');
    });

require __DIR__.'/settings.php';

/*
| Content pages at the top level (/about, /privacy…). Registered last so
| application routes always win; reserved words are blocked on save.
*/
Route::get('{slug}', PageController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('pages.show');
