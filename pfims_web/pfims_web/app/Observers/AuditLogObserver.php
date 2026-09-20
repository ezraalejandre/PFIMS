<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditLogObserver
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function created(Model $model): void
    {
        $this->audit->record($model, 'CREATE', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $after = $model->getAttributes();
        $before = $after;
        foreach ($model->getChanges() as $field => $_) {
            $before[$field] = $model->getOriginal($field);
        }
        $this->audit->record($model, 'UPDATE', $before, $after);
    }

    public function deleted(Model $model): void
    {
        $this->audit->record($model, 'DELETE', $model->getOriginal(), []);
    }
}
