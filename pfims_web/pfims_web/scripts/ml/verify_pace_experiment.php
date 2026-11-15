<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\ML\ClassificationFeaturePreparation as Features;
use App\Services\ML\BudgetOverrunClassifier as Classifier;
$path=base_path('database/data/ml-additions/spending-pace');
$frozen=json_decode(file_get_contents($path.'/frozen-candidate.json'),true,512,JSON_THROW_ON_ERROR);
$test=json_decode(file_get_contents($path.'/fresh-test.json'),true,512,JSON_THROW_ON_ERROR);
$report=json_decode(file_get_contents($path.'/results.json'),true,512,JSON_THROW_ON_ERROR);
if(hash_file('sha256',$path.'/frozen-candidate.json')!==$report['frozen_model_hash'])throw new RuntimeException('Frozen model changed after testing');
if(array_intersect($frozen['fit_project_ids'],$frozen['validation_project_ids'])||array_intersect(array_column($test['records'],'project_id'),[...$frozen['fit_project_ids'],...$frozen['validation_project_ids']]))throw new RuntimeException('Project overlap');
if(strtotime($test['generated_at'])<strtotime($frozen['selected_at']))throw new RuntimeException('Test generated before freeze');
$source=json_decode(file_get_contents(storage_path('app/ml-improvement/source.json')),true,512,JSON_THROW_ON_ERROR);$n=0;
foreach($source['records'] as $row){$values=Features::values($row,App\Services\ML\PlanningSpendingFeatures::FEATURES);foreach(App\Services\ML\PlanningSpendingFeatures::FEATURES as $i=>$name){if(abs($values[$i]-(float)$row[$name])>.000001)throw new RuntimeException('Input mapping mismatch');$n++;}}
try {Features::values(['budget'=>1,'elapsed_time_fraction'=>0],['spending_pace_ratio']);throw new RuntimeException('Invalid elapsed time accepted');}catch(InvalidArgumentException){}
$row=$test['records'][0];$changed=$row;$changed['actual_cost']=999999999;$changed['project_id']=999999999;
if(Features::values($row,$frozen['selected']['feature_names'])!==Features::values($changed,$frozen['selected']['feature_names']))throw new RuntimeException('Outcome or identity leaked into predictors');
$model=Classifier::fromModelData($frozen['model']);$predictions=[];$actuals=[];
foreach($test['records']as$r){$predictions[]=$model->score(Features::values($r,$frozen['selected']['feature_names']))>=$frozen['selected']['threshold'];$actuals[]=round($r['actual_cost']+$r['fin_total_expense'],2)>round($r['budget'],2);}
$metrics=app(App\Services\ProjectOverrunEvaluation::class)->evaluateClasses($predictions,$actuals);
if($metrics['classification_counts']!==$report['fresh_test']['combined']['classification_counts'])throw new RuntimeException('Restored model did not reproduce decisions');
echo "$n original input mappings verified. Project separation, selection-before-test generation, immutable model hash, invalid-input rejection and restored decisions passed.\n";
