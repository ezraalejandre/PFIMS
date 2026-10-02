<?php

namespace App\Http\Controllers;

use App\Services\MLService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class MLCandidateEvaluationController extends Controller
{
    public function store(Request $request)
    {
        $input = $request->validate([
            'cohort' => ['required', Rule::in(['auto', 'planning', 'presentation_progress'])],
        ]);
        try {
            // Evaluating a candidate must never trigger constructor-time training.
            $service = new MLService(loadModel: false);
            $report = $service->evaluateCandidate($input['cohort']);
            $service->saveCandidateEvaluationReport($report);

            return response()->json(['success' => true, 'active_model_changed' => false, 'report' => $report]);
        } catch (Throwable $exception) {
            Log::error('Candidate evaluation failed.', ['message' => $exception->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Evaluation could not be completed. The current forecast model was retained.'], 500);
        }
    }
}
