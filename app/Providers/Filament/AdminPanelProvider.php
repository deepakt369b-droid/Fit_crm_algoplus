<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use App\Filament\Resources\WhatsappAutomations\WhatsappAutomationResource;
use App\Filament\Resources\WhatsappBroadcasts\WhatsappBroadcastResource;
use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use App\Filament\Resources\WhatsappKnowledgeBaseArticles\WhatsappKnowledgeBaseArticleResource;
use App\Filament\Resources\WhatsappPhoneNumbers\WhatsappPhoneNumberResource;
use App\Filament\Resources\WhatsappTemplates\WhatsappTemplateResource;
use App\Http\Middleware\SetAppLocale;
use App\Models\Gym;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Filament panel provider for the main admin panel.
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Configure the panel.
     */
    public function panel(Panel $panel): Panel
    {
        return $this->basePanel($panel)
            ->tenant(Gym::class, slugAttribute: 'slug', ownershipRelationship: 'gym')
            ->navigation(fn (NavigationBuilder $builder) => $this->buildNavigation($builder));
    }

    /**
     * Configure the base panel options.
     */
    public function basePanel(Panel $panel): Panel
    {
        // Roles are global (not per-gym): Spatie's Role model has no `gym`
        // relation, so the panel's tenant scoping throws a LogicException on
        // the Shield roles page. Opt the resource out of tenancy — Filament's
        // own public switch for exactly this case. Must run BEFORE the panel
        // boots and registers tenancy global scopes on resource models.
        RoleResource::scopeToTenant(false);

        return $this->sharedPanelStyling($panel)
            ->default()
            ->id('admin')
            ->path('/')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                Settings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->plugins([FilamentShieldPlugin::make()
                ->navigationIcon(fn (): null => null)
                ->activeNavigationIcon(fn (): null => null)]);
    }

    /**
     * Styling, theming, middleware and render hooks shared by every panel
     * (the branch-scoped admin panel and the superadmin panel). Deliberately
     * excludes id/path/default and resource/page/widget registration, since
     * those differ per panel.
     */
    public function sharedPanelStyling(Panel $panel): Panel
    {
        return $panel
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->passwordReset()
            ->brandName('FitCRM')
            // Single asset for both themes - no dark-mode-optimized variant
            // was supplied (the source image has an opaque light card
            // background, not transparent), so it renders as a light chip
            // on the dark sidebar rather than blending in. Swap in a proper
            // dark variant here if one is produced later.
            ->brandLogo('/images/logo.jpg')
            ->darkModeBrandLogo('/images/logo.jpg')
            ->brandLogoHeight('5rem')
            ->favicon('/images/favicon.jpg')
            ->unsavedChangesAlerts()
            ->colors($this->colors())
            ->defaultThemeMode(ThemeMode::Light)
            ->sidebarWidth('12rem')
            ->middleware([
                SetAppLocale::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseNotifications()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): HtmlString => new HtmlString(
                    Blade::render('@livewire(\\App\\Filament\\Livewire\\LocaleSwitcher::class, [], key(\'locale-switcher\'))')
                ),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): HtmlString => $this->brandAttributionHtml(),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_END,
                fn (): HtmlString => $this->brandAttributionHtml(),
            );
    }

    /**
     * Render the "Powered by Algo Plus" attribution shown in the panel footer
     * and on simple-layout pages (login, password reset).
     */
    protected function brandAttributionHtml(): HtmlString
    {
        return new HtmlString(Blade::render(
            '<p class="fi-footer text-center text-sm text-gray-500 dark:text-gray-400 py-4">{{ __(\'app.branding.attribution\') }}</p>'
        ));
    }

    /**
     * Build grouped navigation for the admin panel.
     */
    protected function buildNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $administration = [
            ...Settings::getNavigationItems(),
            ...UserResource::getNavigationItems(),
            ...RoleResource::getNavigationItems(),
        ];

        $sales = [
            ...EnquiryResource::getNavigationItems(),
            ...FollowUpResource::getNavigationItems(),
        ];

        $billing = [
            ...InvoiceResource::getNavigationItems(),
            ...ExpenseResource::getNavigationItems(),
        ];

        $memberships = [
            ...MemberResource::getNavigationItems(),
            ...PlanResource::getNavigationItems(),
            ...ServiceResource::getNavigationItems(),
            ...SubscriptionResource::getNavigationItems(),
        ];

        $accessControl = [
            ...DeviceResource::getNavigationItems(),
        ];

        $marketing = [
            ...WhatsappConversationResource::getNavigationItems(),
            ...WhatsappBroadcastResource::getNavigationItems(),
            ...WhatsappAutomationResource::getNavigationItems(),
            ...WhatsappKnowledgeBaseArticleResource::getNavigationItems(),
            ...WhatsappContactResource::getNavigationItems(),
            ...WhatsappTemplateResource::getNavigationItems(),
            ...WhatsappPhoneNumberResource::getNavigationItems(),
        ];

        return $builder
            ->groups([
                NavigationGroup::make(__('app.navigation.groups.sales'))
                    ->icon('heroicon-o-shopping-cart')
                    ->items($sales)
                    ->collapsed(false),

                NavigationGroup::make(__('app.navigation.groups.memberships'))
                    ->icon('heroicon-o-user-group')
                    ->items($memberships)
                    ->collapsed(false),

                NavigationGroup::make(__('app.navigation.groups.billing'))
                    ->icon('heroicon-o-document-text')
                    ->items($billing)
                    ->collapsed(false),

                NavigationGroup::make(__('app.navigation.groups.access_control'))
                    ->icon('heroicon-o-qr-code')
                    ->items($accessControl)
                    ->collapsed(false),

                NavigationGroup::make(__('app.navigation.groups.marketing'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->items($marketing)
                    ->collapsed(false),

                NavigationGroup::make(__('app.navigation.groups.administration'))
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->items($administration)
                    ->collapsed(false),
            ])
            ->item(
                NavigationItem::make(__('app.navigation.dashboard'))
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn () => Dashboard::getUrl())
                    ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.dashboard'))
            );
    }

    /**
     * Panel color palette.
     *
     * @return array<string, mixed>
     */
    protected function colors(): array
    {
        return [
            'primary' => [
                50 => '#b3fefc',
                100 => '#37f2ee',
                200 => '#2dcdc9',
                300 => '#24adaa',
                400 => '#1c908d',
                500 => '#157573',
                600 => '#0e5c5a',
                700 => '#084543',
                800 => '#042f2e',
                900 => '#021f1e',
                950 => '#011413',
            ],
            'danger' => Color::Rose,
            'gray' => Color::Gray,
            'info' => Color::Blue,
            'success' => Color::Emerald,
            'warning' => Color::Orange,
        ];
    }
}
