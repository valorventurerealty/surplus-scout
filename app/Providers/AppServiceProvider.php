<?php

namespace App\Providers;

use App\Contracts\AiProviderInterface;
use App\Contracts\PropertyDocumentExtractionInterface;
use App\Contracts\SurplusDocumentExtractionInterface;
use App\Contracts\ToolRegistryInterface;
use App\Contracts\ToolExecutorInterface;
use App\Contracts\AgentOrchestratorInterface;
use App\Contracts\ApprovalServiceInterface;
use App\Contracts\GoogleCalendarGatewayInterface;
use App\Contracts\SurplusResearch\CountySurplusSourceInterface;
use App\Contracts\SurplusResearch\PdfTextExtractorInterface;
use App\Contracts\SurplusResearch\CountyOwnerResearchProviderInterface;
use App\Contracts\SurplusResearch\TrimNoticeExtractorInterface;
use App\Models\User;
use App\Services\GeminiAiProvider;
use App\Services\GeminiPropertyDocumentExtraction;
use App\Services\GeminiSurplusDocumentExtraction;
use App\Services\VvrToolRegistry;
use App\Services\VvrToolExecutor;
use App\Services\VvrAiActionService;
use App\Services\GoogleCalendarApiClient;
use App\Services\SurplusResearch\Osceola\OsceolaClerkSource;
use App\Services\SurplusResearch\SafePdfTextExtractor;
use App\Services\SurplusResearch\Osceola\OsceolaPropertyAppraiserProvider;
use App\Services\SurplusResearch\Osceola\OsceolaTrimNoticeExtractor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, GeminiAiProvider::class);
        $this->app->bind(PropertyDocumentExtractionInterface::class, GeminiPropertyDocumentExtraction::class);
        $this->app->bind(SurplusDocumentExtractionInterface::class, GeminiSurplusDocumentExtraction::class);
        $this->app->singleton(ToolRegistryInterface::class, VvrToolRegistry::class);
        $this->app->bind(ToolExecutorInterface::class, VvrToolExecutor::class);
        $this->app->bind(AgentOrchestratorInterface::class, VvrAiActionService::class);
        $this->app->bind(ApprovalServiceInterface::class, VvrAiActionService::class);
        $this->app->bind(GoogleCalendarGatewayInterface::class, GoogleCalendarApiClient::class);
        $this->app->bind(CountySurplusSourceInterface::class, OsceolaClerkSource::class);
        $this->app->bind(PdfTextExtractorInterface::class, SafePdfTextExtractor::class);
        $this->app->bind(TrimNoticeExtractorInterface::class, OsceolaTrimNoticeExtractor::class);
        $this->app->bind(CountyOwnerResearchProviderInterface::class, OsceolaPropertyAppraiserProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewFinancialWorkspace', fn (User $user): bool => $user->canViewPropertyFinancials());
        Gate::define('manageGoogleCalendarIntegration', fn (User $user): bool => $user->canManageIntegrations());
    }
}
