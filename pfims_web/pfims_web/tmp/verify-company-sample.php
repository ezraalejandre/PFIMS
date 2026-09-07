<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
$dataset=json_decode(file_get_contents(__DIR__.'/../database/data/evc-sample/dataset.json'),true);
$result=['counts'=>[],'mismatches'=>[],'predictions'=>[],'preserved'=>[]];
foreach($dataset['tables'] as $table=>$expected){
    $actual=DB::table($table)->get()->map(fn($r)=>(array)$r)->all();
    $result['counts'][$table]=count($actual);
    if(count($actual)!==count($expected))throw new RuntimeException('Count mismatch '.$table);
    foreach($expected as $i=>$row){
        $key=array_key_last($row);
        $found=collect($actual)->first(fn($r)=>(string)$r[$key]===(string)$row[$key]);
        foreach($row as $field=>$value){
            if(!array_key_exists($field,$found)||$found[$field]!=$value)$result['mismatches'][]=$table.'.'.$field;
        }
    }
}
foreach(['users','user_tbl','reports'] as $t)$result['preserved'][$t]=DB::table($t)->count();
$ml=app(App\Services\MLService::class);
$metrics=$ml->getModelMetrics();
$result['model']=array_intersect_key($metrics,array_flip(['model_source','samples_trained','real_samples_available','sample_samples_available','test_samples','evaluation_method','mean_absolute_error','mean_absolute_percentage_error','metric_scope']));
Auth::setUser(App\Models\User::where('role','admin')->firstOrFail());
$controller=app(App\Http\Controllers\MLController::class);
foreach($ml->getPredictionProjects() as $project){
    $response=$controller->predictProjectCost(Request::create('/api/ml/predict/cost','POST',['project_id'=>$project['project_id']]));
    $data=$response->getData(true);
    if($response->getStatusCode()!==200||!$data['success']||$data['predicted_cost']<=0)throw new RuntimeException('Prediction failed');
    $result['predictions'][]=['project'=>$project['project_name'],'budget'=>$project['budget'],'predicted_cost'=>$data['predicted_cost'],'source'=>$data['prediction_source']];
}
$result['material_forecast_count']=$ml->predictMaterialDemand()->count();
$result['budget_variance_count']=$ml->analyzeBudgetVariance()->count();
if($result['mismatches'])throw new RuntimeException(json_encode($result['mismatches']));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
