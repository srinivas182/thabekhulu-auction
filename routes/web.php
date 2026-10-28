<?php

use App\Domains\Access\Http\Controllers\RoleController;
use App\Domains\Audit\Http\Controllers\AuditController;
use App\Domains\Bidding\Http\Controllers\AuctionMonitorController;
use App\Domains\Bidding\Http\Controllers\BidController;
use App\Domains\Catalogue\Http\Controllers\CategoryController;
use App\Domains\Catalogue\Http\Controllers\LocationController;
use App\Domains\Cms\Http\Controllers\Admin\CmsController;
use App\Domains\Cms\Http\Controllers\Admin\MediaContentController;
use App\Domains\Cms\Http\Controllers\Public\HomeController;
use App\Domains\Cms\Http\Controllers\Public\InsightsController;
use App\Domains\Cms\Http\Controllers\Public\PageController;
use App\Domains\Compliance\Http\Controllers\ComplianceController;
use App\Domains\Compliance\Http\Controllers\ReportsController;
use App\Domains\DataRoom\Http\Controllers\BuyerDashboardController;
use App\Domains\DataRoom\Http\Controllers\DataRoomController;
use App\Domains\DataRoom\Http\Controllers\NdaSettingsController;
use App\Domains\Events\Http\Controllers\EventAdminController;
use App\Domains\Events\Http\Controllers\EventController;
use App\Domains\Growth\Http\Controllers\GrowthController;
use App\Domains\Identity\Http\Controllers\DashboardRedirectController;
use App\Domains\Identity\Http\Controllers\InvitationController;
use App\Domains\Identity\Http\Controllers\StaffController;
use App\Domains\Leads\Http\Controllers\InboxController;
use App\Domains\Leads\Http\Controllers\Public\InboundController;
use App\Domains\Listings\Http\Controllers\ListingAssetController;
use App\Domains\Listings\Http\Controllers\ListingController;
use App\Domains\Listings\Http\Controllers\ListingWorkflowController;
use App\Domains\Listings\Http\Controllers\Public\AuctionController;
use App\Domains\Notifications\Http\NotificationPreferenceController;
use App\Domains\Payments\Http\Controllers\BuyerDepositController;
use App\Domains\Payments\Http\Controllers\FinanceController;
use App\Domains\Payments\Http\Controllers\PaymentProviderController;
use App\Domains\Payments\Http\Controllers\PaymentWebhookController;
use App\Domains\Payments\Http\Controllers\RefundController;
use App\Domains\Platform\Email\EmailProviderController;
use App\Domains\Platform\Email\EmailTemplateController;
use App\Domains\Platform\Http\Controllers\AdminDashboardController;
use App\Domains\Platform\Http\Controllers\FeatureController;
use App\Domains\Premium\Http\PremiumController;
use App\Domains\Privacy\Http\RetentionController;
use App\Domains\Quotes\Http\Controllers\Public\AccountQuotesController;
use App\Domains\Quotes\Http\Controllers\Public\ClientQuoteController;
use App\Domains\Quotes\Http\Controllers\QuoteController;
use App\Domains\Quotes\Http\Controllers\QuoteReportController;
use App\Domains\Quotes\Http\Controllers\QuoteSetupController;
use App\Domains\Quotes\Http\Controllers\QuoteWorkflowController;
use App\Domains\Sellers\Http\Controllers\SellerAdminController;
use App\Domains\Sellers\Http\Controllers\SellerController;
use App\Domains\Settings\Http\Controllers\SettingsController;
use App\Domains\Settlement\Http\Controllers\PayoutController;
use App\Domains\Settlement\Http\Controllers\SettlementController;
use App\Domains\Theme\Http\Controllers\ThemeController;
use App\Domains\Verification\Http\Controllers\BankAccountController;
use App\Domains\Verification\Http\Controllers\BidderApprovalController;
use App\Domains\Verification\Http\Controllers\BiddingController;
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
// Seller module (Sprint 8a): every route 404s while the "sellers" flag is off.
Route::middleware('feature:sellers')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('sell/register', [SellerController::class, 'showRegister'])->name('seller.register');
        Route::post('sell/register', [SellerController::class, 'register'])->middleware('throttle:5,1')->name('seller.register.store');
    });
    Route::middleware('auth')->group(function () {
        Route::get('seller', [SellerController::class, 'dashboard'])->name('seller.dashboard');
        Route::post('seller/submissions', [SellerController::class, 'submit'])->middleware('throttle:10,1')->name('seller.submit');
    });
});
Route::post('newsletter', [GrowthController::class, 'subscribe'])->middleware('throttle:5,1')->name('newsletter.subscribe');
Route::get('newsletter/confirm/{subscriber}', [GrowthController::class, 'confirm'])->middleware('signed')->name('newsletter.confirm');
Route::get('newsletter/unsubscribe/{subscriber}', [GrowthController::class, 'unsubscribe'])->middleware('signed')->name('newsletter.unsubscribe');
Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/{slug}', [EventController::class, 'show'])->name('events.show');
Route::post('events/{slug}/order', [EventController::class, 'order'])->middleware('throttle:5,1')->name('events.order');
Route::get('tickets/{order}', [EventController::class, 'tickets'])->middleware('signed')->name('events.tickets');
Route::get('tickets/{order}/pdf', [EventController::class, 'ticketsPdf'])->middleware('signed')->name('events.tickets.pdf');
Route::post('webhooks/payments/tradesafe/{secret}', [PaymentWebhookController::class, 'tradesafe'])->middleware('throttle:120,1')->name('webhooks.payments.tradesafe');
Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)->middleware('throttle:120,1')->name('webhooks.payments');
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
    Route::get('account', BuyerDashboardController::class)->name('account');
    Route::get('deposits/{listing}', [BuyerDepositController::class, 'show'])->name('deposits.show');
    Route::post('deposits/{listing}/pay', [BuyerDepositController::class, 'pay'])->middleware('throttle:10,1')->name('deposits.pay');
    Route::post('deposits/{listing}', [BuyerDepositController::class, 'open'])->middleware('throttle:10,1')->name('deposits.open');
    Route::get('receipts/{number}', [BuyerDepositController::class, 'receipt'])->name('receipts.download');
    Route::get('data-room/{listing}', [DataRoomController::class, 'show'])->name('dataroom.show');
    Route::post('data-room/{listing}/nda', [DataRoomController::class, 'acceptNda'])->middleware('throttle:10,1')->name('dataroom.nda');
    Route::get('data-room/{listing}/documents/{document}', [DataRoomController::class, 'download'])->middleware('throttle:60,1')->name('dataroom.download');
    Route::get('account/quotes', AccountQuotesController::class)->name('account.quotes');
    Route::get('account/entities', [BiddingController::class, 'entities'])->name('account.entities');
    Route::post('account/entities', [BiddingController::class, 'storeEntity'])->middleware('throttle:10,1')->name('account.entities.store');
    Route::get('account/approvals', [BiddingController::class, 'approvals'])->name('account.approvals');
    Route::post('auctions/bid/{listing}', [BidController::class, 'store'])->middleware('throttle:30,1')->name('auctions.bid');
    Route::post('auctions/tender/{listing}', [BidController::class, 'tender'])->middleware('throttle:10,1')->name('auctions.tender');
    Route::post('auctions/apply/{listing}', [BiddingController::class, 'apply'])->middleware('throttle:10,1')->name('auctions.apply');
    Route::get('account/saved', [GrowthController::class, 'mine'])->name('account.saved');
    Route::post('account/saved-searches', [GrowthController::class, 'saveSearch'])->middleware('throttle:10,1')->name('account.saved-searches.store');
    Route::delete('account/saved-searches/{ulid}', [GrowthController::class, 'deleteSearch'])->name('account.saved-searches.destroy');
    Route::post('auctions/watch/{listing}', [GrowthController::class, 'toggleWatch'])->middleware('throttle:30,1')->name('auctions.watch');
    Route::get('account/purchases', [SettlementController::class, 'mine'])->name('account.purchases');
    Route::post('account/purchases/{case}/accept', [SettlementController::class, 'accept'])->middleware('throttle:10,1')->name('account.purchases.accept');
    Route::post('account/purchases/{case}/decline', [SettlementController::class, 'decline'])->middleware('throttle:10,1')->name('account.purchases.decline');
    Route::get('account/notifications', [NotificationPreferenceController::class, 'show'])->name('account.notifications');
    Route::put('account/notifications', [NotificationPreferenceController::class, 'update'])->name('account.notifications.update');
    Route::get('account/bank', [BankAccountController::class, 'show'])->name('account.bank');
    Route::post('account/bank', [BankAccountController::class, 'store'])->middleware('throttle:10,1')->name('account.bank.store');
    Route::prefix('account/verification')->name('account.verification')->group(function () {
        Route::get('/', [BuyerVerificationController::class, 'show']);
        Route::post('identity', [BuyerVerificationController::class, 'identity'])->name('.identity');
        Route::post('selfie', [BuyerVerificationController::class, 'selfie'])->name('.selfie');
        Route::post('address', [BuyerVerificationController::class, 'address'])->name('.address');
        Route::post('submit', [BuyerVerificationController::class, 'submit'])->middleware('throttle:5,1')->name('.submit');
        Route::post('reply', [BuyerVerificationController::class, 'reply'])->middleware('throttle:10,1')->name('.reply');
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
        Route::middleware('can:auctions.monitor')->group(function () {
            Route::get('listings/{listing}/auction', [AuctionMonitorController::class, 'show'])->name('listings.auction');
            Route::get('listings/{listing}/bidders-record', [AuctionMonitorController::class, 'biddersRecord'])->name('listings.bidders-record');
            Route::post('listings/{listing}/auction/pause', [AuctionMonitorController::class, 'pause'])->middleware('can:auctions.pause')->name('listings.auction.pause');
            Route::post('bids/{bid}/void', [AuctionMonitorController::class, 'void'])->middleware('can:auctions.void_bid')->name('bids.void');
            Route::post('listings/{listing}/tender/request', [AuctionMonitorController::class, 'requestOpening'])->middleware('can:auctions.open_tender')->name('listings.tender.request');
            Route::post('listings/{listing}/tender/confirm', [AuctionMonitorController::class, 'confirmOpening'])->middleware('can:auctions.open_tender')->name('listings.tender.confirm');
        });
        Route::get('listings/{listing}/bidders', [BidderApprovalController::class, 'bidders'])->middleware('can:bidders.approve')->name('listings.bidders');
        Route::post('bidders/{approval}', [BidderApprovalController::class, 'decide'])->middleware('can:bidders.approve')->name('bidders.decide');
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

        Route::middleware('can:payments.providers')->prefix('finance/providers')->name('finance.providers.')->group(function () {
            Route::get('/', [PaymentProviderController::class, 'index'])->name('index');
            Route::put('deposit-provider', [PaymentProviderController::class, 'depositProvider'])->name('deposit');
            Route::put('{provider}', [PaymentProviderController::class, 'update'])->name('update');
            Route::post('{provider}/test', [PaymentProviderController::class, 'test'])->name('test');
        });
        Route::post('listings/{listing}/relist', [ListingController::class, 'relist'])->middleware('can:listings.publish')->name('listings.relist');
        Route::middleware('can:premium.manage')->prefix('premium')->name('premium.')->group(function () {
            Route::get('/', [PremiumController::class, 'index'])->name('index');
            Route::post('/', [PremiumController::class, 'store'])->name('store');
            Route::delete('{slot}', [PremiumController::class, 'destroy'])->name('destroy');
        });
        Route::get('newsletter/export', [GrowthController::class, 'export'])->middleware('can:cms.manage')->name('newsletter.export');
        // Super admin only (checked in the controller; super admins hold every permission).
        Route::get('settings/retention', [RetentionController::class, 'show'])->middleware('can:settings.manage')->name('settings.retention');
        Route::put('settings/retention', [RetentionController::class, 'update'])->middleware('can:settings.manage')->name('settings.retention.update');
        Route::middleware('can:compliance.review')->prefix('compliance')->name('compliance.')->group(function () {
            Route::get('/', [ComplianceController::class, 'index'])->name('index');
            Route::get('{ulid}', [ComplianceController::class, 'show'])->name('show');
            Route::put('{ulid}', [ComplianceController::class, 'update'])->name('update');
            Route::get('{ulid}/evidence', [ComplianceController::class, 'evidence'])->name('evidence');
        });
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('register', [ReportsController::class, 'register'])->middleware('can:buyers.view')->name('register');
            Route::get('register.csv', [ReportsController::class, 'registerCsv'])->middleware(['can:buyers.view', 'can:reports.export'])->name('register.csv');
            Route::get('finance', [ReportsController::class, 'finance'])->middleware('can:reports.view')->name('finance');
            Route::get('finance/{report}.csv', [ReportsController::class, 'financeCsv'])->middleware('can:reports.export')->name('finance.csv');
        });
        Route::prefix('events')->name('events.')->group(function () {
            Route::middleware('can:events.manage')->group(function () {
                Route::get('/', [EventAdminController::class, 'index'])->name('index');
                Route::get('create', [EventAdminController::class, 'create'])->name('create');
                Route::post('/', [EventAdminController::class, 'store'])->name('store');
                Route::get('{event}/edit', [EventAdminController::class, 'edit'])->name('edit');
                Route::put('{event}', [EventAdminController::class, 'update'])->name('update');
                Route::put('{event}/status', [EventAdminController::class, 'status'])->name('status');
                Route::get('{event}/attendees', [EventAdminController::class, 'attendees'])->name('attendees');
            });
            Route::get('{event}/check-in', [EventAdminController::class, 'checkInPage'])->middleware('can:events.checkin')->name('checkin');
            Route::post('{event}/check-in', [EventAdminController::class, 'checkIn'])->middleware(['can:events.checkin', 'throttle:120,1'])->name('checkin.store');
        });
        Route::prefix('settlement')->name('settlement.')->group(function () {
            Route::get('/', [SettlementController::class, 'index'])->name('index');
            Route::get('{case}', [SettlementController::class, 'show'])->name('show');
            Route::get('{case}/steps/{step}', [SettlementController::class, 'document'])->name('document');
            Route::post('{case}/steps', [SettlementController::class, 'recordStep'])->middleware('can:settlement.manage')->name('step');
            Route::post('{case}/complete', [SettlementController::class, 'complete'])->middleware('can:settlement.manage')->name('complete');
            Route::post('{case}/default', [SettlementController::class, 'declareDefault'])->middleware('can:auctions.settle')->name('default');
            Route::post('{case}/default/confirm', [SettlementController::class, 'confirmDefault'])->middleware('can:settlement.confirm_default')->name('default.confirm');
            Route::post('{case}/payout', [PayoutController::class, 'prepare'])->middleware('can:payouts.record')->name('payout');
            Route::post('payouts/{payout}/approve', [PayoutController::class, 'approve'])->middleware('can:payouts.approve')->name('payout.approve');
            Route::post('payouts/{payout}/paid', [PayoutController::class, 'paid'])->middleware('can:payouts.record')->name('payout.paid');
            Route::post('{case}/invoice', [PayoutController::class, 'invoice'])->middleware('can:payouts.record')->name('invoice');
            Route::get('invoices/{invoice}', [PayoutController::class, 'invoicePdf'])->middleware('can:payouts.view')->name('invoice.pdf');
            Route::post('{case}/transfer', [PayoutController::class, 'stage'])->middleware('can:settlement.manage')->name('transfer');
        });
        Route::middleware('can:refunds.view')->prefix('finance/refunds')->name('finance.refunds.')->group(function () {
            Route::get('/', [RefundController::class, 'index'])->name('index');
            Route::post('schedule', [RefundController::class, 'schedule'])->middleware('can:refunds.issue')->name('schedule');
            Route::post('prepare', [RefundController::class, 'prepare'])->middleware('can:refunds.issue')->name('prepare');
            Route::delete('{refund}', [RefundController::class, 'cancel'])->middleware('can:refunds.issue')->name('cancel');
            Route::get('batches/{batch}', [RefundController::class, 'batch'])->name('batch');
            Route::post('batches/{batch}/approve', [RefundController::class, 'approve'])->middleware('can:refunds.approve')->name('approve');
            Route::post('batches/{batch}/paid', [RefundController::class, 'recordPayment'])->middleware('can:refunds.issue')->name('paid');
            Route::get('batches/{batch}/bank-file', [RefundController::class, 'bankFile'])->middleware('can:refunds.issue')->name('bank-file');
            Route::get('ledger.csv', [RefundController::class, 'ledgerExport'])->name('ledger');
        });
        Route::middleware('can:deposits.view')->prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [FinanceController::class, 'index'])->name('index');
            Route::post('imports', [FinanceController::class, 'import'])->middleware('can:deposits.reconcile')->name('import');
            Route::post('lines/{line}', [FinanceController::class, 'resolve'])->middleware('can:deposits.reconcile')->name('resolve');
        });
        Route::middleware('can:settings.manage')->group(function () {
            Route::prefix('email-templates')->name('email-templates.')->controller(EmailTemplateController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{key}', 'edit')->name('edit');
                Route::put('{key}', 'update')->name('update');
                Route::delete('{key}', 'reset')->name('reset');
                Route::post('{key}/preview', 'preview')->middleware('throttle:120,1')->name('preview');
                Route::post('{key}/test', 'test')->middleware('throttle:6,1')->name('test');
            });
            Route::get('settings/email', [EmailProviderController::class, 'show'])->name('settings.email');
            Route::put('settings/email', [EmailProviderController::class, 'update'])->name('settings.email.update');
            Route::post('settings/email/test', [EmailProviderController::class, 'test'])->middleware('throttle:6,1')->name('settings.email.test');
            Route::get('notifications/whatsapp', [NotificationPreferenceController::class, 'settings'])->name('notifications.whatsapp');
            Route::put('notifications/whatsapp', [NotificationPreferenceController::class, 'updateSettings'])->name('notifications.whatsapp.update');
            Route::get('finance/settings', [FinanceController::class, 'settings'])->name('finance.settings');
            Route::put('finance/settings', [FinanceController::class, 'updateSettings'])->name('finance.settings.update');
            Route::get('data-room/settings', [NdaSettingsController::class, 'edit'])->name('dataroom.settings');
            Route::put('data-room/settings', [NdaSettingsController::class, 'update'])->name('dataroom.settings.update');
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
            Route::get('entities/{entity}', [BidderApprovalController::class, 'entity'])->name('entity');
            Route::get('entities/{entity}/documents/{document}', [BidderApprovalController::class, 'entityDocument'])->name('entity-document');
            Route::post('entities/{entity}', [BidderApprovalController::class, 'decideEntity'])->middleware('can:kyc.approve_company')->name('entity.decide');
            Route::get('funds/{funds}', [BidderApprovalController::class, 'fundsDocument'])->name('funds-document');
            Route::post('funds/{funds}', [BidderApprovalController::class, 'decideFunds'])->middleware('can:kyc.approve')->name('funds.decide');
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
        Route::get('leads/{lead}/documents/{document}', [SellerAdminController::class, 'document'])->middleware('can:leads.view')->name('leads.document');
        Route::post('leads/{lead}/convert', [SellerAdminController::class, 'convert'])->middleware('can:listings.create')->name('leads.convert');
        Route::middleware(['feature:sellers', 'can:sellers.manage'])->prefix('sellers')->name('sellers.')->group(function () {
            Route::get('packages', [SellerAdminController::class, 'packages'])->name('packages');
            Route::post('packages', [SellerAdminController::class, 'storePackage'])->name('packages.store');
            Route::post('packages/assign', [SellerAdminController::class, 'assign'])->name('packages.assign');
        });
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
