<?php

namespace App\Http\Controllers;

use App\Services\ProjectRemainingCostReview;
use Illuminate\Http\Request;

class ProjectRemainingCostReviewController extends Controller
{
    public function show(int $project, ProjectRemainingCostReview $reviews)
    {
        abort_unless(\App\Models\Project::where('project_id', $project)->exists(), 404);
        return response()->json(['success' => true, 'review' => $reviews->latest($project)]);
    }

    public function store(Request $request, int $project, ProjectRemainingCostReview $reviews)
    {
        return response()->json(['success' => true, 'review' => $reviews->save($project, $request->all(), $request->user()->id)], 201);
    }
}
