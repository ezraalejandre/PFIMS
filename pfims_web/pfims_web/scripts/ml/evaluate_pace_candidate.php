<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\ML\ClassificationFeaturePreparation as Features;
use App\Services\ML\BudgetOverrunClassifier as Classifier;
use App\Services\ProjectOverrunEvaluation as Metrics;

App\Services\ML\LocalImprovementWorkbench::assertLocal();
$directory = base_path('database/data/ml-additions/data-volume-v2');
$output = base_path('database/data/ml-additions/spending-pace');
if (! is_dir($output)) mkdir($output);
$phase = $argv[1] ?? 'select';
$source = json_decode(file_get_contents(storage_path('app/ml-improvement/source.json')),true,512,JSON_THROW_ON_ERROR);
$protocol = json_decode(file_get_contents(storage_path('app/ml-improvement/report.json')),true,512,JSON_THROW_ON_ERROR);
$data = json_decode(file_get_contents($directory.'/experiment-data.json'),true,512,JSON_THROW_ON_ERROR);
$original = array_values(array_filter($source['records'],fn($r)=>in_array($r['project_id'],$protocol['training_project_ids'])));
$validation = array_values(array_filter($data['training_records'],fn($r)=>in_array($r['project_id'],$data['validation_project_ids'])));
$fit = [...$original,...array_values(array_filter($data['training_records'],fn($r)=>!in_array($r['project_id'],$data['validation_project_ids'])))];
$labels = fn($rows)=>array_map(fn($r)=>round($r['actual_cost']+$r['fin_total_expense'],2)>round($r['budget'],2),$rows);
$score = function($model,$rows,$features,$legacy=false) {
    return array_map(function($r)use($model,$features,$legacy){
        if($legacy){$r['elapsed_days']=0;$r['remaining_planned_days']=0;}
        return $model->score(Features::values($r,$features));
    },$rows);
};
$metric = fn($actual,$scores,$threshold)=>app(Metrics::class)->evaluateClasses(array_map(fn($s)=>$s>=$threshold,$scores),$actual);
$rank = function($m){$v=array_map(fn($k)=>$m[$k]??0,['precision','recall','f1_score','classification_accuracy','balanced_accuracy']);return [min($v)>=90,min($v),$m['f1_score']??0];};
if($phase==='select'){
    if(is_file($output.'/frozen-candidate.json')) throw new RuntimeException('Selection is frozen. Use a new experiment version instead of retuning.');
    $base=App\Services\ML\PlanningSpendingFeatures::FEATURES;
    $variants=['legacy_16'=>[$base,true],'corrected_16'=>[$base,false],'pace_only'=>[['spending_pace_ratio'],false],
        'corrected_plus_pace'=>[[...$base,...Features::DERIVED],false]];
    $trials=[];$models=[];
    foreach($variants as $name=>[$features,$legacy]){
        $model=new Classifier;
        $samples=array_map(function($r)use($features,$legacy){if($legacy){$r['elapsed_days']=0;$r['remaining_planned_days']=0;}return Features::values($r,$features);},$fit);
        $model->train($samples,$labels($fit),array_column($fit,'project_id'));
        $models[$name]=$model;
        $scores=$score($model,$validation,$features,$legacy);
        $sorted=array_values(array_unique($scores));sort($sorted);
        $thresholds=[.5];for($i=1;$i<count($sorted);$i++)$thresholds[]=($sorted[$i-1]+$sorted[$i])/2;
        $options=[];foreach($thresholds as $t){$m=$metric($labels($validation),$scores,$t);$options[]=['threshold'=>$t,'metrics'=>$m];}
        usort($options,fn($a,$b)=>$rank($b['metrics'])<=>$rank($a['metrics']));
        $trials[]=['name'=>$name,'feature_names'=>$features,'legacy_mapping'=>$legacy]+$options[0];
    }
    usort($trials,fn($a,$b)=>($rank($b['metrics'])<=>$rank($a['metrics']))?:count($a['feature_names'])<=>count($b['feature_names']));
    $chosen=$trials[0];
    $payload=['selected_at'=>now()->toIso8601String(),'algorithm'=>'Existing PHP project-balanced logistic regression','selected'=>$chosen,
        'model'=>$models[$chosen['name']]->modelData(),'training_projects'=>count(array_unique(array_column($fit,'project_id'))),
        'training_observations'=>count($fit),'validation_projects'=>200,'validation_observations'=>count($validation),
        'fit_project_ids'=>array_values(array_unique(array_column($fit,'project_id'))),'validation_project_ids'=>$data['validation_project_ids'],
        'source_hash'=>hash_file('sha256',$directory.'/experiment-data.json'),'variants'=>$trials,
        'selection_rule'=>'Prefer all five validation metrics >=90%, then maximize weakest metric and F1; tie prefers fewer features.',
        'refit_after_validation'=>false];
    file_put_contents($output.'/frozen-candidate.json',json_encode($payload,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($trials,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);exit;
}
if($phase!=='test')throw new RuntimeException('Use select or test');
$frozen=json_decode(file_get_contents($output.'/frozen-candidate.json'),true,512,JSON_THROW_ON_ERROR);
$fresh=json_decode(file_get_contents($output.'/fresh-test.json'),true,512,JSON_THROW_ON_ERROR);
if(strtotime($fresh['generated_at'])<strtotime($frozen['selected_at']))throw new RuntimeException('Test must be generated after selection freeze');
if(array_intersect(array_column($fresh['records'],'project_id'),[...$frozen['fit_project_ids'],...$frozen['validation_project_ids']]))throw new RuntimeException('Project overlap');
$model=Classifier::fromModelData($frozen['model']);$features=$frozen['selected']['feature_names'];$threshold=$frozen['selected']['threshold'];
$evaluate=function($rows)use($model,$features,$threshold,$score,$metric,$labels){
    $scores=$score($model,$rows,$features);$actual=$labels($rows);$known=[];$predicted=[];$predictedEarly=[];$actualEarly=[];
    foreach($rows as $i=>$r){$confirmed=$r['fin_total_expense']>$r['budget'];$predicted[]=$confirmed?1:$scores[$i];if(!$confirmed){$predictedEarly[]=$scores[$i];$actualEarly[]=$actual[$i];}}
    return ['combined'=>$metric($actual,$predicted,$threshold),'before_budget_exceeded'=>$metric($actualEarly,$predictedEarly,$threshold)];
};
$historical=array_values(array_filter($source['records'],fn($r)=>in_array($r['project_id'],$protocol['development_test_project_ids'])));
$report=['generated_at'=>now()->toIso8601String(),'scope'=>'Frozen classifier on fresh simulated project draws; not operational validation.',
    'selected'=>$frozen['selected'],'variants'=>$frozen['variants'],'training_projects'=>$frozen['training_projects'],'training_observations'=>$frozen['training_observations'],
    'validation_projects'=>200,'validation_observations'=>600,'test_projects'=>150,'test_observations'=>450,'fresh_test'=>$evaluate($fresh['records']),
    'historical_test'=>$evaluate($historical),'active_model_changed'=>false,'operational_database_changed'=>false,
    'frozen_model_hash'=>hash_file('sha256',$output.'/frozen-candidate.json'),'test_source_hash'=>hash_file('sha256',$output.'/fresh-test.json')];
foreach(['early','middle','late']as$stage)$report['by_stage'][$stage]=$evaluate(array_values(array_filter($fresh['records'],fn($r)=>match($stage){'early'=>$r['elapsed_time_fraction']<=.33,'middle'=>$r['elapsed_time_fraction']>.33&&$r['elapsed_time_fraction']<=.66,'late'=>$r['elapsed_time_fraction']>.66})));
file_put_contents($output.'/results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
file_put_contents(storage_path('app/ml-pace-experiment.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
