<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportContactsRequest;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Services\ContactDirectoryQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    public function __invoke(ExportContactsRequest $request, ContactDirectoryQuery $directory): StreamedResponse
    {
        $data = $request->validated();
        $query = $directory->build($request->user(), $data)
            ->with([
                'assignedUser:id,name',
                'properties:id,address,city,state', 'ownedProperties:id,owner_contact_id,address,city,state',
                'tasks' => fn ($query) => $query->open()->orderBy('due_at'),
                ...($request->user()->canViewSurplusCases() ? [
                    'surplusCases:id,token,case_number,claimant_contact_id',
                    'associatedSurplusCases:id,token,case_number,claimant_contact_id',
                ] : []),
            ]);
        if (($data['mode'] ?? null) === 'selected') $query->whereIn('contacts.id', $data['contact_ids']);
        $count = (clone $query)->count();

        AuditLog::query()->create([
            'user_id' => $request->user()->id, 'event' => 'exported',
            'auditable_type' => Contact::class, 'auditable_id' => null,
            'new_values' => ['mode' => $data['mode'], 'record_count' => $count, 'filters' => collect($data)->only(['search', 'type', 'status'])->filter()->all()],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        $filename = 'vvr-contacts-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $request): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['First Name', 'Last Name', 'Company', 'Email', 'Phone', 'Type', 'Status', 'Mailing Address 1', 'Mailing Address 2', 'City', 'State', 'Postal Code', 'Country', 'Assigned To', 'Next Follow-up', 'Follow-up Purpose', 'Associated Properties', 'Surplus Cases', 'Open Tasks', 'Notes']);
            $query->chunk(200, function ($contacts) use ($output, $request): void {
                foreach ($contacts as $contact) {
                    $properties = $contact->properties->merge($contact->ownedProperties)->unique('id')->map(fn ($property): string => trim($property->address.', '.$property->city.', '.$property->state, ', '))->implode(' | ');
                    $cases = $request->user()->canViewSurplusCases()
                        ? $contact->surplusCases->merge($contact->associatedSurplusCases)->unique('id')->pluck('case_number')->implode(' | ')
                        : '';
                    $tasks = $contact->tasks->pluck('title')->implode(' | ');
                    fputcsv($output, array_map($this->safeCell(...), [
                        $contact->first_name, $contact->last_name, $contact->company, $contact->email, $contact->phone,
                        $contact->type->label(), $contact->status->label(), $contact->mailing_address_line_1,
                        $contact->mailing_address_line_2, $contact->mailing_city, $contact->mailing_state_province,
                        $contact->mailing_postal_code, $contact->mailing_country, $contact->assignedUser?->name,
                        $contact->next_follow_up_at?->toIso8601String(), $contact->next_follow_up_purpose,
                        $properties, $cases, $tasks, $contact->notes,
                    ]));
                }
            });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function safeCell(mixed $value): string
    {
        $value = str_replace("\0", '', (string) $value);

        return preg_match('/^[=+\-@]/', ltrim($value)) === 1 ? "'".$value : $value;
    }
}
