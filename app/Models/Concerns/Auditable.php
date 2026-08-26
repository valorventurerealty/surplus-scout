<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

trait Auditable
{
    public static function bootAuditable(): void
    {
        $events = ['created', 'updated', 'deleted'];

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $events[] = 'restored';
        }

        foreach ($events as $event) {
            static::$event(function (Model $model) use ($event): void {
                $request = app()->runningInConsole() ? null : request();
                $excluded = method_exists($model, 'auditExcludedAttributes')
                    ? $model->auditExcludedAttributes()
                    : [];
                AuditLog::query()->create([
                    'user_id' => auth()->id(),
                    'event' => $event,
                    'auditable_type' => $model->getMorphClass(),
                    'auditable_id' => $model->getKey(),
                    'old_values' => in_array($event, ['updated', 'deleted'], true)
                        ? Arr::except($model->getRawOriginal(), $excluded)
                        : null,
                    'new_values' => $event === 'deleted'
                        ? null
                        : Arr::except($model->getAttributes(), $excluded),
                    'ip_address' => $request?->ip(),
                    'user_agent' => $request?->userAgent(),
                ]);
            });
        }
    }
}
