<?php

namespace App\Services;

use App\Contracts\ToolRegistryInterface;
use App\Data\AiToolDefinition;
use App\Enums\UserRole;
use App\Models\User;

class VvrToolRegistry implements ToolRegistryInterface
{
    public function forUser(User $user): array
    {
        return array_values(array_filter($this->definitions(), fn (AiToolDefinition $tool): bool => $tool->allows($user->role)));
    }

    public function find(string $name): ?AiToolDefinition
    {
        return collect($this->definitions())->firstWhere('name', $name);
    }

    /** @return array<int, AiToolDefinition> */
    private function definitions(): array
    {
        $all = UserRole::cases();
        $propertyManagers = [UserRole::Owner, UserRole::Partner, UserRole::AcquisitionManager, UserRole::DispositionManager, UserRole::VirtualAssistant, UserRole::Admin];
        $financialPropertyManagers = [UserRole::Owner, UserRole::Partner, UserRole::AcquisitionManager, UserRole::DispositionManager, UserRole::Admin];
        $taskManagers = array_values(array_filter($all, fn (UserRole $role): bool => $role !== UserRole::ReadOnly));
        $surplusReaders = array_values(array_filter($all, fn (UserRole $role): bool => $role !== UserRole::Marketing));
        $surplusManagers = [UserRole::Owner, UserRole::Partner, UserRole::AcquisitionManager, UserRole::VirtualAssistant, UserRole::Admin];
        $object = fn (array $properties, array $required = []): array => ['type' => 'object', 'additionalProperties' => false, 'properties' => $properties, 'required' => $required];

        return [
            new AiToolDefinition('get_properties', 'Search authorized property records using filters and pagination.', $object(['search' => ['type' => 'string'], 'status' => ['type' => 'string'], 'county' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50]]), $all, 0, false),
            new AiToolDefinition('get_property', 'Retrieve one authorized property by its database ID.', $object(['property_id' => ['type' => 'integer']], ['property_id']), $all, 0, false),
            new AiToolDefinition('create_property', 'Create a property from user-reviewed and validated fields.', $object(['approved_plan_id' => ['type' => 'integer']], ['approved_plan_id']), $financialPropertyManagers, 2, true),
            new AiToolDefinition('update_property', 'Update allowlisted fields on an existing property using exact user-approved changes.', $object(['property_id' => ['type' => 'integer'], 'changes' => ['type' => 'object']], ['property_id', 'changes']), $propertyManagers, 2, true),
            new AiToolDefinition('change_pipeline_stage', 'Move a property to an allowed pipeline status.', $object(['property_id' => ['type' => 'integer'], 'status' => ['type' => 'string']], ['property_id', 'status']), $propertyManagers, 2, true),
            new AiToolDefinition('update_marketability_checklist', 'Update selected approved property checklist entries and optional HTTPS evidence links.', $object(['property_id' => ['type' => 'integer'], 'items' => ['type' => 'array', 'items' => $object(['key' => ['type' => 'string', 'enum' => ['max_bid', 'property_card', 'acquisition_deed', 'quiet_title_final_judgment']], 'completed' => ['type' => 'boolean'], 'evidence_url' => ['type' => 'string']], ['key', 'completed'])]], ['property_id', 'items']), $propertyManagers, 2, true),
            new AiToolDefinition('create_auction_event', 'Create a validated tax-deed or foreclosure auction calendar event.', $object(['property_id' => ['type' => 'integer'], 'parcel_number' => ['type' => 'string'], 'event_type' => ['type' => 'string', 'enum' => ['tax_deed_auction', 'foreclosure_auction']], 'date' => ['type' => 'string'], 'time' => ['type' => 'string'], 'auction_url' => ['type' => 'string'], 'property_address' => ['type' => 'string'], 'county' => ['type' => 'string', 'enum' => ['putnam', 'osceola', 'marion', 'polk', 'brevard', 'orange']], 'max_bid' => ['type' => 'number'], 'notes' => ['type' => 'string']], ['parcel_number', 'event_type', 'date', 'time', 'auction_url', 'property_address', 'county']), $propertyManagers, 2, true),
            new AiToolDefinition('create_calendar_item', 'AI creation of general calendar items remains disabled during this milestone.', $object([]), $propertyManagers, 2, true, false),
            new AiToolDefinition('create_task', 'Create and assign a validated CRM task.', $object(['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']], 'assigned_user_id' => ['type' => 'integer'], 'due_at' => ['type' => 'string'], 'subject' => ['type' => 'string']], ['title']), $taskManagers, 2, true),
            new AiToolDefinition('search_buyers', 'Search contacts classified as buyers, investors, builders, or developers.', $object(['search' => ['type' => 'string'], 'type' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50]]), $all, 0, false),
            new AiToolDefinition('analyze_data', 'Analyze an authorized, bounded CRM dataset without changing records.', $object(['report' => ['type' => 'string'], 'filters' => ['type' => 'object']], ['report']), $all, 0, false),
            new AiToolDefinition('search_sops', 'Search VVR standard operating procedures by title, summary, procedure text, department, or status.', $object(['search' => ['type' => 'string'], 'department' => ['type' => 'string'], 'status' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20]]), $all, 0, false),
            new AiToolDefinition('get_sop', 'Retrieve one authorized VVR standard operating procedure.', $object(['sop_id' => ['type' => 'integer']], ['sop_id']), $all, 0, false),
            new AiToolDefinition('search_surplus_cases', 'Search authorized surplus recovery cases by case, claimant, parcel, county, or pipeline stage.', $object(['search' => ['type' => 'string'], 'status' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50]]), $surplusReaders, 0, false),
            new AiToolDefinition('get_surplus_case', 'Retrieve one authorized surplus recovery case.', $object(['surplus_case_id' => ['type' => 'integer']], ['surplus_case_id']), $surplusReaders, 0, false),
            new AiToolDefinition('update_surplus_case', 'Update allowlisted fields on a surplus case using exact user-approved changes. Agreed and actual recovery fees may never exceed 12%.', $object(['surplus_case_id' => ['type' => 'integer'], 'changes' => ['type' => 'object']], ['surplus_case_id', 'changes']), $surplusManagers, 2, true),
        ];
    }
}
