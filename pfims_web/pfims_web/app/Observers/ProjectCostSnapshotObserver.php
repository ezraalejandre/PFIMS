<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\AutomaticModelRetraining;

class ProjectCostSnapshotObserver
{
    public function __construct(private AutomaticModelRetraining $modelRetraining) {}

    public function created(Project $project): void
    {
        $this->modelRetraining->afterDataChange((int) $project->project_id);
    }

    public function updated(Project $project): void
    {
        $this->modelRetraining->afterDataChange((int) $project->project_id);
    }
}
