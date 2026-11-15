<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Services\ML\LocalImprovementWorkbench::assertLocal();
$root=storage_path('app/ml-improvement');
$data=json_decode(file_get_contents($root.'/portable-cost.json'),true,512,JSON_THROW_ON_ERROR);
$fixtures=$data['fixtures'];unset($data['fixtures']);
$model=new App\Services\ML\PortableGradientBoosting($data);
foreach($fixtures as $fixture) {
 if(abs($model->predict($fixture['features'])-$fixture['expected'])>1e-10) throw new RuntimeException('Python/PHP forecast parity failed.');
}
$report=json_decode(file_get_contents($root.'/report.json'),true,512,JSON_THROW_ON_ERROR);
if(!hash_equals($report['source_sha256'],hash_file('sha256',$root.'/source.json'))) throw new RuntimeException('Source changed.');
$path=storage_path('app/ml_model.phpml');
$store=new App\Services\ML\CostModelStore($path,10);
$pair=$store->load();$metadata=$pair['metadata'];
$backup=$root.'/before-activation-'.date('Ymd-His');mkdir($backup);
foreach(['','.meta.json','.detector.json'] as $suffix) if(is_file($path.$suffix)) copy($path.$suffix,$backup.'/'.basename($path.$suffix));
$evaluation=function($e){return ['mean_absolute_error'=>$e['mae'],'mean_absolute_percentage_error'=>$e['mape'],'r_squared'=>$e['r_squared'],'evaluation_projects'=>$e['projects'],'evaluation_observations'=>$e['observations'],'target_median'=>$e['median_final_cost'],'mae_target'=>['reference_pesos'=>$e['median_final_cost'],'actual_percent'=>$e['mae_percent_of_median']]];};
$metadata['evaluation']=$evaluation($report['evaluation_all_checkpoints']);
$metadata['evaluation']['latest_observation_per_project']=$evaluation($report['evaluation_latest_checkpoint']);
$metadata['transformer']=['feature_names'=>$data['features'],'selected_feature_indexes'=>range(0,count($data['features'])-1),'ranges'=>array_fill(0,count($data['features']),['min'=>0,'max'=>1]),'target_scaling'=>'remaining_cost_fraction_of_budget'];
$metadata['feature_set']=['feature_names'=>$data['features'],'selected_feature_names'=>$data['features']];
$metadata['prediction_target']='remaining_cost_then_add_recorded_spend';
$metadata['prediction_strategy']='planning_spending_model';
$metadata['model_source']='local_development_gradient_boosting';
$metadata['trained_at']=$report['generated_at'];
$metadata['evaluation_training_project_ids']=$report['training_project_ids'];
$metadata['evaluation_holdout_project_ids']=$report['development_test_project_ids'];
$metadata['training_projects']=count($report['training_project_ids']);
$metadata['test_samples']=$report['evaluation_all_checkpoints']['observations'];
$metadata['explicit_local_activation']=['authorized_by'=>'User request to replace active forecast model','activated_at'=>now()->toIso8601String(),'scope'=>$report['scope'],'automatic_policy_bypassed'=>true,'backup'=>$backup,'parity_observations'=>count($fixtures)];
// Old-model comparisons and validation cannot describe the newly activated estimator.
foreach(['model_comparison','budget_baseline_comparison','cross_validation','presentation_publication'] as $key) unset($metadata[$key]);
$stored=$store->save($model,$metadata);
$loaded=$store->load();
foreach($fixtures as $f) if(abs($loaded['model']->predict($f['features'])-$f['expected'])>1e-10) throw new RuntimeException('Reload parity failed.');
echo json_encode(['activated'=>$stored['model_type'],'parity_observations'=>count($fixtures),'backup'=>$backup],JSON_PRETTY_PRINT).PHP_EOL;
