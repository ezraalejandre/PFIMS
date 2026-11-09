<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\NotificationService;

// Group the project actions.
class ProjectObserver
{
    // Set up the values this class needs.
    public function __construct(protected NotificationService $notifications)
    {
    }

    /**
     * Fires on every UPDATE to project_tbl. We only care about the
     * "status" column actually changing value.
     */
    public function updated(Project $project): void
    {
        // Run this step if the is dirty check matches.
        if (!$project->isDirty('status')) {
            return;
        }

        $newStatus = strtolower((string) $project->status);
        $oldStatus = strtolower((string) $project->getOriginal('status'));

        // Run this step if the new status equals old status.
        if ($newStatus === $oldStatus) {
            return;
        }

        // Run this step if the new status equals delayed.
        if ($newStatus === 'delayed') {
            $this->fire($project, 'project_delayed', 'overdue',
                'Project Delayed',
                "\"{$project->project_name}\" has been marked as delayed."
            );
        } elseif ($newStatus === 'at risk') {
            $this->fire($project, 'project_at_risk', 'warning',
                'Project At Risk',
                "\"{$project->project_name}\" is now flagged as at risk."
            );
        }
    }

    /**
     * Also worth catching on CREATE in case a project is entered directly
     * with status Delayed/At Risk (e.g. filled in later data).
     */
    public function created(Project $project): void
    {
        $status = strtolower((string) $project->status);

        // Run this step if the status equals delayed.
        if ($status === 'delayed') {
            $this->fire($project, 'project_delayed', 'overdue',
                'Project Delayed',
                "\"{$project->project_name}\" was created with a delayed status."
            );
        } elseif ($status === 'at risk') {
            $this->fire($project, 'project_at_risk', 'warning',
                'Project At Risk',
                "\"{$project->project_name}\" was created flagged as at risk."
            );
        }
    }

    // Get the fire.
    protected function fire(Project $project, string $type, string $kind, string $title, string $message): void
    {
        // Avoid re-spamming while it's still unread and still in that state.
        if ($this->notifications->alreadyNotified($type, 'project', $project->project_id)) {
            return;
        }

        $this->notifications->notify(
            title: $title,
            message: $message,
            type: $type,
            kind: $kind,
            filter: 'alerts',
            referenceType: 'project',
            referenceId: $project->project_id,
        );
    }
}