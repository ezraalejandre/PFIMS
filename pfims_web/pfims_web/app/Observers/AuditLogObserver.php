<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

// Group the audit log actions.
class AuditLogObserver
{
    // Set up the values this class needs.
    public function __construct(private readonly AuditLogService $audit) {}

    // Get the created.
    public function created(Model $model): void
    {
        $this->audit->record($model, 'CREATE', [], $model->getAttributes());
    }

    // Get the updated.
    public function updated(Model $model): void
    {
        $after = $model->getAttributes();
        $before = $after;
        // Process each .
        foreach ($model->getChanges() as $field => $_) {
            $before[$field] = $model->getOriginal($field);
        }
        $this->audit->record($model, 'UPDATE', $before, $after);
    }

    // Get the deleted.
    public function deleted(Model $model): void
    {
        $this->audit->record($model, 'DELETE', $model->getOriginal(), []);
    }
}
