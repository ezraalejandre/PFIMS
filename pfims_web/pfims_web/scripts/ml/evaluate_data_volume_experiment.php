<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
class DataVolumeExperiment extends App\Services\MLService {
    public function run(): array {
        $path = base_path('database/data/ml-additions/data-volume-v2');
        $data = json_decode(file_get_contents($path.'/experiment-data.json'), true, 512, JSON_THROW_ON_ERROR);
        $source = json_decode(file_get_contents(storage_path('app/ml-improvement/source.json')), true, 512, JSON_THROW_ON_ERROR);
        $protocol = json_decode(file_get_contents(storage_path('app/ml-improvement/report.json')), true, 512, JSON_THROW_ON_ERROR);
        $real = collect($source['records'])->map(fn ($r)=>(object)$r);
        $originalTrain = $real->whereIn('project_id',$protocol['training_project_ids'])->values();
        $originalTest = $real->whereIn('project_id',$protocol['development_test_project_ids'])->values();
        $extra = collect($data['training_records'])->map(fn ($r)=>(object)$r);
        $validation=$extra->whereIn('project_id',$data['validation_project_ids'])->values();
        $fit=$originalTrain->concat($extra->whereNotIn('project_id',$data['validation_project_ids']))->values();
        $test=collect($data['test_records'])->map(fn ($r)=>(object)$r);
        if ($fit->max('completed_at') >= $validation->min('completed_at')) throw new RuntimeException('Completion-date validation order violated');
        $features=App\Services\ML\PlanningSpendingFeatures::FEATURES;
        $model=$this->fitOverrunClassifier($fit,$features);
        $trials=[];
        foreach ([.2,.3,.4,.5,.6,.7,.8] as $threshold) {
            $o=$this->classifierOutcomes($model,$validation,$features,$threshold);
            $metrics=app(App\Services\ProjectOverrunEvaluation::class)->evaluateClasses($o['predictions'],$o['actuals']);
            $trials[]=['threshold'=>$threshold,'metrics'=>$metrics];
        }
        usort($trials,fn($a,$b)=>[$b['metrics']['f1_score'],$b['metrics']['balanced_accuracy'],$b['metrics']['recall']]<=>[$a['metrics']['f1_score'],$a['metrics']['balanced_accuracy'],$a['metrics']['recall']]);
        $threshold=$trials[0]['threshold'];
        $baselineModel=$this->fitOverrunClassifier($originalTrain,$features);
        $baselineTrials=[];
        foreach ([.2,.3,.4,.5,.6,.7,.8] as $t) {
            $o=$this->classifierOutcomes($baselineModel,$validation,$features,$t);
            $baselineTrials[]=['threshold'=>$t,'metrics'=>app(App\Services\ProjectOverrunEvaluation::class)->evaluateClasses($o['predictions'],$o['actuals'])];
        }
        usort($baselineTrials,fn($a,$b)=>[$b['metrics']['f1_score'],$b['metrics']['balanced_accuracy'],$b['metrics']['recall']]<=>[$a['metrics']['f1_score'],$a['metrics']['balanced_accuracy'],$a['metrics']['recall']]);
        $model=$this->fitOverrunClassifier($originalTrain->concat($extra)->values(),$features);
        $score=function($rows,$scoringModel=null,$scoringThreshold=null)use($model,$features,$threshold){
            $o=$this->classifierOutcomes($scoringModel??$model,$rows,$features,$scoringThreshold??$threshold);
            $metrics=app(App\Services\ProjectOverrunEvaluation::class)->evaluateClasses($o['predictions'],$o['actuals']);
            $p=$a=[];
            foreach($rows->values() as $i=>$row)if($row->fin_total_expense<=$row->budget){$p[]=$o['predictions'][$i];$a[]=$o['actuals'][$i];}
            return ['combined'=>$metrics,'before_budget_exceeded'=>app(App\Services\ProjectOverrunEvaluation::class)->evaluateClasses($p,$a)];
        };
        $report=['generated_at'=>now()->toIso8601String(),'scope'=>$data['scope'],'assumptions'=>$data['assumptions'],
            'database_scope'=>$data['database_scope'],'training_projects'=>1067,'training_observations'=>3201,
            'added_training_overruns'=>500,'added_training_controls'=>500,'heldout_projects'=>150,'heldout_overruns'=>50,'heldout_controls'=>100,
            'validation_projects'=>200,'selected_threshold'=>$threshold,'validation_trials'=>$trials,
            'baseline_threshold'=>$baselineTrials[0]['threshold'],'baseline_validation_trials'=>$baselineTrials,
            'baseline_simulation_test'=>$score($test,$baselineModel,$baselineTrials[0]['threshold']),
            'baseline_historical_test'=>$score($originalTest,$baselineModel,$baselineTrials[0]['threshold']),
            'matched_threshold_baseline_simulation_test'=>$score($test,$baselineModel,$threshold),
            'matched_threshold_baseline_historical_test'=>$score($originalTest,$baselineModel,$threshold),
            'algorithm'=>'Existing PHP project-balanced logistic classifier; original 16 declared inputs and current feature preparation',
            'simulation_test'=>$score($test),'historical_development_test'=>$score($originalTest),
            'active_model_changed'=>false,'operational_records_added'=>0,'test_source_sha256'=>hash_file('sha256',$path.'/experiment-data.json')];
        foreach(['early','middle','late']as$stage)$report['by_stage'][$stage]=$score($test->filter(fn($r)=>match($stage){'early'=>$r->elapsed_time_fraction<=.33,'middle'=>$r->elapsed_time_fraction>.33&&$r->elapsed_time_fraction<=.66,'late'=>$r->elapsed_time_fraction>.66})->values());
        file_put_contents($path.'/results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        file_put_contents(storage_path('app/ml-data-volume-experiment.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        return $report;
    }
}
$report=(new DataVolumeExperiment(loadModel:false))->run();
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
