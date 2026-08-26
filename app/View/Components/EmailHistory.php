<?php

namespace App\View\Components;

use App\Models\Contact;
use App\Models\OutboundEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

class EmailHistory extends Component
{
    public function __construct(public Model $record) {}

    public function render(): View
    {
        $user = auth()->user();
        $query = $this->record instanceof Contact
            ? OutboundEmail::query()->where(fn ($query) => $query->where('primary_contact_id', $this->record->id)->orWhere(fn ($query) => $query->where('related_type', $this->record->getMorphClass())->where('related_id', $this->record->id)))
            : $this->record->outboundEmails();
        $emails = $query
            ->when(! $user->canViewAllOutboundEmails(), fn ($query) => $query->where('user_id', $user->id))
            ->with(['user:id,name', 'primaryContact'])->latest()->limit(25)->get()
            ->filter(fn ($email): bool => $user->can('view', $email))->take(10);
        return view('components.email-history', ['emails' => $emails]);
    }
}
