<?php

namespace NinjaPortal\Mfa;

use NinjaPortal\Api\Contracts\Auth\AuthFlowInterface;
use NinjaPortal\Mfa\Auth\MfaAuthFlow;
use NinjaPortal\Mfa\Console\Commands\PruneMfaChallengesCommand;
use NinjaPortal\Mfa\Contracts\Repositories\MfaChallengeRepositoryInterface;
use NinjaPortal\Mfa\Contracts\Repositories\MfaFactorRepositoryInterface;
use NinjaPortal\Mfa\Contracts\Repositories\MfaProfileRepositoryInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaActorConfigServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaChallengeServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaDriverManagerInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaFactorServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaProfileServiceInterface;
use NinjaPortal\Mfa\Models\MfaChallenge;
use NinjaPortal\Mfa\Models\MfaFactor;
use NinjaPortal\Mfa\Models\MfaProfile;
use NinjaPortal\Mfa\Repositories\MfaChallengeRepository;
use NinjaPortal\Mfa\Repositories\MfaFactorRepository;
use NinjaPortal\Mfa\Repositories\MfaProfileRepository;
use NinjaPortal\Mfa\Services\MfaActorConfigService;
use NinjaPortal\Mfa\Services\MfaChallengeService;
use NinjaPortal\Mfa\Services\MfaDriverManager;
use NinjaPortal\Mfa\Services\MfaFactorService;
use NinjaPortal\Mfa\Services\MfaProfileService;
use NinjaPortal\Mfa\Services\PruneMfaChallengesService;
use NinjaPortal\Mfa\Support\Base32;
use NinjaPortal\Mfa\Support\MfaMask;
use NinjaPortal\Mfa\Support\MfaTokenHasher;
use NinjaPortal\Mfa\Support\TotpService;
use NinjaPortal\Portal\Providers\Concerns\RegistersBindings;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MfaServiceProvider extends PackageServiceProvider
{
    use RegistersBindings;

    protected array $serviceBindings = [
        MfaActorConfigServiceInterface::class => MfaActorConfigService::class,
        MfaDriverManagerInterface::class => MfaDriverManager::class,
        MfaProfileServiceInterface::class => MfaProfileService::class,
        MfaChallengeServiceInterface::class => MfaChallengeService::class,
        MfaFactorServiceInterface::class => MfaFactorService::class,
    ];

    protected array $repositoryBindings = [
        [
            'interface' => MfaProfileRepositoryInterface::class,
            'implementation' => MfaProfileRepository::class,
            'model' => MfaProfile::class,
        ],
        [
            'interface' => MfaFactorRepositoryInterface::class,
            'implementation' => MfaFactorRepository::class,
            'model' => MfaFactor::class,
        ],
        [
            'interface' => MfaChallengeRepositoryInterface::class,
            'implementation' => MfaChallengeRepository::class,
            'model' => MfaChallenge::class,
        ],
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('portal-mfa')
            ->hasConfigFile('portal-mfa')
            ->hasRoutes('api')
            ->hasMigrations([
                '2026_02_24_200000_create_portal_mfa_profiles_table',
                '2026_02_24_200100_create_portal_mfa_factors_table',
                '2026_02_24_200200_create_portal_mfa_challenges_table',
            ])
            ->hasCommand(PruneMfaChallengesCommand::class);
    }

    public function register(): void
    {
        parent::register();

        $this->registerRepositories();
        $this->registerServices();

        $this->app->singleton(Base32::class);
        $this->app->singleton(TotpService::class);
        $this->app->singleton(MfaTokenHasher::class);
        $this->app->singleton(MfaMask::class);
        $this->app->singleton(PruneMfaChallengesService::class);
    }

    public function packageRegistered(): void
    {
        $this->publishes([
            __DIR__.'/../config/portal-mfa.php' => config_path('portal-mfa.php'),
        ], 'portal-mfa-config');
    }

    public function packageBooted(): void
    {
        if (! (bool) config('portal-mfa.enabled', true)) {
            return;
        }

        // Override portal-api auth flow so MFA can challenge before issuing tokens.
        $this->app->bind(AuthFlowInterface::class, MfaAuthFlow::class);
    }
}
