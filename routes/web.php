<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactExportController;
use App\Http\Controllers\ContactIntakeFileController;
use App\Http\Controllers\ContactTaskController;
use App\Http\Controllers\ArmoryController;
use App\Http\Controllers\ArmoryEmailTemplateController;
use App\Http\Controllers\ArmoryEmailTemplateAttachmentController;
use App\Http\Controllers\ArmoryNegotiationController;
use App\Http\Controllers\ArmoryPlaybookController;
use App\Http\Controllers\ArmorySessionController;
use App\Http\Controllers\SalesCopilotController;
use App\Http\Controllers\SalesCopilotFeedbackController;
use App\Http\Controllers\SalesCopilotPlaybookController;
use App\Http\Controllers\FinancialsController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DealContactController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyIntakeFileController;
use App\Http\Controllers\VvrAiController;
use App\Http\Controllers\VvrAiSurplusCsvImportController;
use App\Http\Controllers\VvrAiPreAuctionCsvImportController;
use App\Http\Controllers\SurplusIntakeFileController;
use App\Http\Controllers\PropertyChecklistController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskTemplateController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\SurplusCaseController;
use App\Http\Controllers\SurplusCaseContactController;
use App\Http\Controllers\PreAuctionAcquisitionController;
use App\Http\Controllers\PreAuctionAcquisitionContactController;
use App\Http\Controllers\SopController;
use App\Http\Controllers\EmailSignatureController;
use App\Http\Controllers\OutboundEmailAttachmentController;
use App\Http\Controllers\OutboundEmailController;
use App\Http\Controllers\BesideWebhookController;
use App\Http\Controllers\PhoneInteractionController;
use App\Http\Controllers\GoogleCalendarIntegrationController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\SurplusScoutController;
use App\Http\Controllers\SurplusScoutOsceolaController;
use App\Http\Controllers\SurplusScoutOsceolaOwnerResearchController;
use App\Http\Controllers\WebsiteChatController;
use App\Http\Controllers\WebsiteChatWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::post('integrations/beside/events', BesideWebhookController::class)
    ->middleware(['beside.webhook', 'throttle:60,1'])
    ->name('integrations.beside.events');

Route::post('integrations/website-chat', WebsiteChatWebhookController::class)
    ->middleware(['website-chat.webhook', 'throttle:20,1'])
    ->name('integrations.website-chat');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('sales-copilot', [SalesCopilotController::class, 'index'])->name('sales-copilot.index');
    Route::get('sales-copilot/objection-coach', [SalesCopilotController::class, 'objections'])->name('sales-copilot.objections');
    Route::get('sales-copilot/practice', [SalesCopilotController::class, 'practice'])->name('sales-copilot.practice');
    Route::post('sales-copilot/sessions', [SalesCopilotController::class, 'store'])->middleware('throttle:30,1')->name('sales-copilot.sessions.store');
    Route::get('sales-copilot/sessions/{session}', [SalesCopilotController::class, 'show'])->name('sales-copilot.sessions.show');
    Route::post('sales-copilot/sessions/{session}/coach', [SalesCopilotController::class, 'coach'])->middleware('throttle:30,1')->name('sales-copilot.sessions.coach');
    Route::post('sales-copilot/sessions/{session}/complete', [SalesCopilotController::class, 'complete'])->name('sales-copilot.sessions.complete');
    Route::post('sales-copilot/turns/{turn}/feedback', [SalesCopilotFeedbackController::class, 'store'])->name('sales-copilot.feedback.store');
    Route::get('sales-copilot/playbooks', [SalesCopilotPlaybookController::class, 'index'])->name('sales-copilot.playbooks.index');
    Route::get('sales-copilot/playbooks/create', [SalesCopilotPlaybookController::class, 'create'])->name('sales-copilot.playbooks.create');
    Route::post('sales-copilot/playbooks', [SalesCopilotPlaybookController::class, 'store'])->name('sales-copilot.playbooks.store');
    Route::get('sales-copilot/playbooks/{playbook}/edit', [SalesCopilotPlaybookController::class, 'edit'])->name('sales-copilot.playbooks.edit');
    Route::put('sales-copilot/playbooks/{playbook}', [SalesCopilotPlaybookController::class, 'update'])->name('sales-copilot.playbooks.update');
    Route::redirect('armory/sales-copilot', '/sales-copilot')->name('armory.sales-copilot.index');
    Route::get('surplus-scout', SurplusScoutController::class)->name('surplus-scout.index');
    Route::get('surplus-scout/osceola', [SurplusScoutOsceolaController::class, 'index'])->name('surplus-scout.osceola.index');
    Route::post('surplus-scout/osceola/runs', [SurplusScoutOsceolaController::class, 'store'])->middleware('throttle:3,10')->name('surplus-scout.osceola.runs.store');
    Route::get('surplus-scout/osceola/runs/{researchRun}', [SurplusScoutOsceolaController::class, 'show'])->name('surplus-scout.osceola.runs.show');
    Route::post('surplus-scout/osceola/owner-research', [SurplusScoutOsceolaOwnerResearchController::class, 'store'])
        ->middleware('throttle:5,10')->name('surplus-scout.osceola.owner-research.store');
    Route::post('surplus-scout/osceola/cases/{surplus}/owner-research', [SurplusScoutOsceolaOwnerResearchController::class, 'researchCase'])
        ->middleware('throttle:10,10')->name('surplus-scout.osceola.owner-research.case');
    Route::get('surplus-scout/osceola/owner-research/{ownerResearchBatch}', [SurplusScoutOsceolaOwnerResearchController::class, 'show'])
        ->name('surplus-scout.osceola.owner-research.show');
    Route::get('phone-calls', [PhoneInteractionController::class, 'index'])->name('phone-interactions.index');
    Route::get('phone-calls/{phoneInteraction}', [PhoneInteractionController::class, 'show'])->name('phone-interactions.show');
    Route::get('website-chats', [WebsiteChatController::class, 'index'])->name('website-chats.index');
    Route::get('website-chats/{websiteChat}', [WebsiteChatController::class, 'show'])->name('website-chats.show');
    Route::patch('website-chats/{websiteChat}', [WebsiteChatController::class, 'update'])->name('website-chats.update');
    Route::match(['post', 'patch'], 'phone-calls/{phoneInteraction}/contact', [PhoneInteractionController::class, 'linkContact'])->name('phone-interactions.contact.update');
    Route::get('email/signatures', [EmailSignatureController::class, 'index'])->name('email.signatures.index');
    Route::get('email/signatures/{signature}/edit', [EmailSignatureController::class, 'edit'])->name('email.signatures.edit');
    Route::post('email/signatures/{signature}/save', [EmailSignatureController::class, 'update'])->name('email.signatures.update');
    Route::post('email/compose/save', [OutboundEmailController::class, 'store'])->name('email.store');
    Route::post('email/{outboundEmail}/save', [OutboundEmailController::class, 'update'])->name('email.update');
    // Compatibility endpoints keep already-cached email forms functional during deployment.
    Route::match(['post', 'put'], 'email/signatures/{signature}', [EmailSignatureController::class, 'update']);
    Route::post('email', [OutboundEmailController::class, 'store']);
    Route::match(['post', 'put'], 'email/{outboundEmail}', [OutboundEmailController::class, 'update']);
    Route::post('email/{outboundEmail}/send', [OutboundEmailController::class, 'send'])->name('email.send');
    Route::post('email/{outboundEmail}/cancel', [OutboundEmailController::class, 'cancel'])->name('email.cancel');
    Route::post('email/{outboundEmail}/retry', [OutboundEmailController::class, 'retry'])->name('email.retry');
    Route::get('email/{outboundEmail}/attachments/{attachment}', OutboundEmailAttachmentController::class)->name('email.attachments.download');
    Route::resource('email', OutboundEmailController::class)
        ->except(['store', 'update'])
        ->parameters(['email' => 'outboundEmail']);
    Route::get('vvr-ai', [VvrAiController::class, 'index'])->name('vvr-ai.index');
    Route::post('vvr-ai/property-intakes', [VvrAiController::class, 'store'])->name('vvr-ai.intakes.store');
    Route::post('vvr-ai/surplus-csv-imports', [VvrAiSurplusCsvImportController::class, 'store'])->name('vvr-ai.surplus-csv-imports.store');
    Route::post('vvr-ai/pre-auction-csv-imports', [VvrAiPreAuctionCsvImportController::class, 'store'])->name('vvr-ai.pre-auction-csv-imports.store');
    Route::get('vvr-ai/conversations/{conversation}', [VvrAiController::class, 'show'])->name('vvr-ai.conversations.show');
    Route::post('vvr-ai/conversations/{conversation}/plans/{plan}/approve', [VvrAiController::class, 'approve'])->name('vvr-ai.plans.approve');
    Route::post('vvr-ai/conversations/{conversation}/plans/{plan}/reject', [VvrAiController::class, 'reject'])->name('vvr-ai.plans.reject');
    Route::post('vvr-ai/conversations/{conversation}/surplus-intakes/approve', [VvrAiController::class, 'approveSurplusIntake'])->name('vvr-ai.surplus-intakes.approve');
    Route::post('vvr-ai/conversations/{conversation}/surplus-csv-imports/{import}/approve', [VvrAiSurplusCsvImportController::class, 'approve'])->name('vvr-ai.surplus-csv-imports.approve');
    Route::post('vvr-ai/conversations/{conversation}/pre-auction-csv-imports/{import}/approve', [VvrAiPreAuctionCsvImportController::class, 'approve'])->name('vvr-ai.pre-auction-csv-imports.approve');
    Route::resource('calendar', CalendarEventController::class)->parameters(['calendar' => 'event']);
    Route::get('settings/integrations/google-calendar', [GoogleCalendarIntegrationController::class, 'index'])->name('google-calendar.index');
    Route::get('settings/integrations/google-calendar/connect', [GoogleCalendarIntegrationController::class, 'connect'])->name('google-calendar.connect');
    Route::get('settings/integrations/google-calendar/callback', [GoogleCalendarIntegrationController::class, 'callback'])->name('google-calendar.callback');
    Route::put('settings/integrations/google-calendar', [GoogleCalendarIntegrationController::class, 'update'])->name('google-calendar.update');
    Route::put('settings/integrations/google-calendar/inbound-sync', [GoogleCalendarIntegrationController::class, 'updateInboundSync'])->name('google-calendar.inbound-sync.update');
    Route::post('settings/integrations/google-calendar/inbound-sync/run', [GoogleCalendarIntegrationController::class, 'runInboundSync'])->name('google-calendar.inbound-sync.run');
    Route::post('settings/integrations/google-calendar/sync-upcoming', [GoogleCalendarIntegrationController::class, 'syncUpcoming'])->name('google-calendar.sync-upcoming');
    Route::delete('settings/integrations/google-calendar', [GoogleCalendarIntegrationController::class, 'disconnect'])->name('google-calendar.disconnect');
    Route::post('calendar/{event}/google-sync', [GoogleCalendarIntegrationController::class, 'retry'])->name('calendar.google-sync');
    Route::patch('tasks/bulk-status', [TaskController::class, 'bulkUpdateStatus'])->name('tasks.bulk-status');
    Route::patch('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::resource('tasks', TaskController::class);
    Route::resource('task-templates', TaskTemplateController::class)
        ->parameters(['task-templates' => 'template'])
        ->except(['show']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('armory/email-templates/save', [ArmoryEmailTemplateController::class, 'store'])
        ->name('armory.email-templates.save');
    Route::get('armory/email-templates/{emailTemplate}/attachments/{attachment}', ArmoryEmailTemplateAttachmentController::class)
        ->name('armory.email-templates.attachments.download');
    Route::resource('armory/email-templates', ArmoryEmailTemplateController::class)
        ->parameters(['email-templates' => 'emailTemplate'])
        ->names([
            'index' => 'armory.email-templates.index',
            'create' => 'armory.email-templates.create',
            'store' => 'armory.email-templates.store',
            'show' => 'armory.email-templates.show',
            'edit' => 'armory.email-templates.edit',
            'update' => 'armory.email-templates.update',
            'destroy' => 'armory.email-templates.destroy',
        ]);
    Route::get('armory/negotiations', [ArmoryNegotiationController::class, 'index'])->name('armory.negotiations.index');
    Route::get('armory/negotiations/create', [ArmoryNegotiationController::class, 'create'])->name('armory.negotiations.create');
    Route::post('armory/negotiations', [ArmoryNegotiationController::class, 'store'])->name('armory.negotiations.store');
    Route::get('armory/negotiations/{negotiation}', [ArmoryNegotiationController::class, 'show'])->name('armory.negotiations.show');
    Route::get('armory/negotiations/{negotiation}/edit', [ArmoryNegotiationController::class, 'edit'])->name('armory.negotiations.edit');
    Route::put('armory/negotiations/{negotiation}', [ArmoryNegotiationController::class, 'update'])->name('armory.negotiations.update');
    Route::delete('armory/negotiations/{negotiation}', [ArmoryNegotiationController::class, 'destroy'])->name('armory.negotiations.destroy');
    Route::get('armory/sessions', [ArmorySessionController::class, 'index'])->name('armory.sessions.index');
    Route::get('armory/sessions/start', [ArmorySessionController::class, 'start'])->name('armory.sessions.start');
    Route::get('armory/sessions/{session}', [ArmorySessionController::class, 'show'])->name('armory.sessions.show');
    Route::delete('armory/sessions/{session}', [ArmorySessionController::class, 'destroy'])->name('armory.sessions.destroy');
    Route::post('armory/sessions/{session}/advance', [ArmorySessionController::class, 'advance'])->name('armory.sessions.advance');
    Route::post('armory/sessions/{session}/finish', [ArmorySessionController::class, 'finish'])->name('armory.sessions.finish');
    Route::post('armory/sessions/{session}/abandon', [ArmorySessionController::class, 'abandon'])->name('armory.sessions.abandon');
    Route::get('armory/{script}/playbook', [ArmoryPlaybookController::class, 'show'])->name('armory.playbook.show');
    Route::post('armory/{script}/playbook/steps', [ArmoryPlaybookController::class, 'storeStep'])->name('armory.playbook.steps.store');
    Route::put('armory/playbook/steps/{step}', [ArmoryPlaybookController::class, 'updateStep'])->name('armory.playbook.steps.update');
    Route::delete('armory/playbook/steps/{step}', [ArmoryPlaybookController::class, 'destroyStep'])->name('armory.playbook.steps.destroy');
    Route::post('armory/playbook/steps/{step}/options', [ArmoryPlaybookController::class, 'storeOption'])->name('armory.playbook.options.store');
    Route::put('armory/playbook/options/{option}', [ArmoryPlaybookController::class, 'updateOption'])->name('armory.playbook.options.update');
    Route::delete('armory/playbook/options/{option}', [ArmoryPlaybookController::class, 'destroyOption'])->name('armory.playbook.options.destroy');
    Route::get('armory/{script}/sessions/create', [ArmorySessionController::class, 'create'])->name('armory.sessions.create');
    Route::post('armory/{script}/sessions', [ArmorySessionController::class, 'store'])->name('armory.sessions.store');
    Route::get('armory/{script}/download', [ArmoryController::class, 'download'])->name('armory.download');
    Route::resource('armory', ArmoryController::class)->parameters(['armory' => 'script']);
    Route::get('financials', [FinancialsController::class, 'index'])->name('financials.index');
    Route::get('financials/properties/{property}/edit', [FinancialsController::class, 'edit'])->name('financials.properties.edit');
    Route::put('financials/properties/{property}', [FinancialsController::class, 'update'])->name('financials.properties.update');
    Route::patch('projections/{scenario}/default', [ProjectionController::class, 'makeDefault'])->name('projections.default');
    Route::resource('projections', ProjectionController::class)
        ->parameters(['projections' => 'scenario'])
        ->except(['show']);
    Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline.index');
    Route::patch('pipeline/properties/{property}/stage', [PipelineController::class, 'update'])->name('pipeline.properties.update');
    Route::post('deals/{deal}/contacts', [DealContactController::class, 'store'])->name('deals.contacts.store');
    Route::delete('deals/{deal}/contacts/{party}', [DealContactController::class, 'destroy'])->whereNumber('party')->name('deals.contacts.destroy');
    Route::resource('deals', DealController::class);
    Route::patch('surplus/bulk-stage', [SurplusCaseController::class, 'bulkUpdateStage'])->name('surplus.bulk-stage');
    Route::post('surplus/{surplus}/contacts', [SurplusCaseContactController::class, 'store'])->name('surplus.contacts.store');
    Route::delete('surplus/{surplus}/contacts/{association}', [SurplusCaseContactController::class, 'destroy'])->whereNumber('association')->name('surplus.contacts.destroy');
    Route::resource('surplus', SurplusCaseController::class)->parameters(['surplus' => 'surplus']);
    Route::get('surplus/{surplus}/intake-files/{intakeFile}/download', [SurplusIntakeFileController::class, 'download'])->name('surplus.intake-files.download');
    Route::patch('pre-auction/bulk-stage', [PreAuctionAcquisitionController::class, 'bulkUpdateStage'])->name('pre-auction.bulk-stage');
    Route::post('pre-auction/{preAuction}/contacts', [PreAuctionAcquisitionContactController::class, 'store'])->name('pre-auction.contacts.store');
    Route::delete('pre-auction/{preAuction}/contacts/{association}', [PreAuctionAcquisitionContactController::class, 'destroy'])->whereNumber('association')->name('pre-auction.contacts.destroy');
    Route::resource('pre-auction', PreAuctionAcquisitionController::class)->parameters(['pre-auction' => 'preAuction']);
    Route::get('sops/{sop}/download', [SopController::class, 'download'])->name('sops.download');
    Route::resource('sops', SopController::class);
    Route::post('contacts/export', ContactExportController::class)->middleware('throttle:10,1')->name('contacts.export');
    Route::resource('contacts', ContactController::class);
    Route::get('contacts/{contact}/intake-files/{intakeFile}/download', [ContactIntakeFileController::class, 'download'])
        ->name('contacts.intake-files.download');
    Route::put('properties/{property}/checklist', [PropertyChecklistController::class, 'update'])->name('properties.checklist.update');
    Route::resource('properties', PropertyController::class);
    Route::get('properties/{property}/intake-files/{intakeFile}/download', [PropertyIntakeFileController::class, 'download'])
        ->name('properties.intake-files.download');
    Route::scopeBindings()->group(function () {
        Route::post('contacts/{contact}/tasks', [ContactTaskController::class, 'store'])->name('contacts.tasks.store');
        Route::patch('contacts/{contact}/tasks/{task}/complete', [ContactTaskController::class, 'complete'])->name('contacts.tasks.complete');
        Route::delete('contacts/{contact}/tasks/{task}', [ContactTaskController::class, 'destroy'])->name('contacts.tasks.destroy');
    });
});

require __DIR__.'/auth.php';
