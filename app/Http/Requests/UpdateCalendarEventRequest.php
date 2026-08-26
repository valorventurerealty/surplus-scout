<?php

namespace App\Http\Requests;

class UpdateCalendarEventRequest extends CalendarEventRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }
}
