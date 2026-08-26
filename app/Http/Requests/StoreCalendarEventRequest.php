<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;

class StoreCalendarEventRequest extends CalendarEventRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CalendarEvent::class) ?? false;
    }
}
