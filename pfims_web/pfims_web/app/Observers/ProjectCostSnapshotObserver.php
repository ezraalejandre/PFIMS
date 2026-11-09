<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\AutomaticModelRetraining;

// Group the project cost snapshot actions.
class ProjectCostSnapshotObserver
{
    // Set up the values this class needs.
    public function __construct(private AutomaticModelRetraining $modelRetraining) {}

    // Get the created.
    public function created(Project $project): void
    {
        $this->modelRetraining->afterDataChange((int) $project->project_id);
    }

    // Get the updated.
    public function updated(Project $project): void
    {
        $this->modelRetraining->afterDataChange((int) $project->project_id);
    }
}
