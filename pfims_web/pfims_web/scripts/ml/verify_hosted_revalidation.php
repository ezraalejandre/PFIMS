<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Services\ML\LocalImprovementWorkbench::assertLocal();
class HostedRevalidationProbe extends App\Services\MLService {public function runCheck(){return $this->revalidateUnchangedGradientBoosting();}}
$paths=[storage_path('app/ml_model.phpml'),storage_path('app/ml_model.phpml.meta.json'),storage_path('app/ml-improvement/source.json'),storage_path('app/ml-r-squared-audit.json')];
$before=array_map(fn($path)=>hash_file('sha256',$path),$paths);
$service=new HostedRevalidationProbe;$result=$service->runCheck();
if($result['candidate_activated']!==false||$result['training_performed']!==false||!isset($result['metrics']['r_squared_audit']['chronological_validation']))throw new RuntimeException('Incorrect revalidation response');
$real=app(App\Services\ML\PlanningSpendingCohort::class)->database();
$fake=new class($real) extends App\Services\ML\PlanningSpendingCohort {
 public function __construct(private array $cohort){}
 public function database():array{$copy=$this->cohort;$copy['records']=$copy['records']->map(fn($row)=>clone $row);$copy['records'][0]->budget+=1;return $copy;}
};
app()->instance(App\Services\ML\PlanningSpendingCohort::class,$fake);
try{$service->runCheck();throw new RuntimeException('Changed inputs were accepted');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'checkpoints have changed'))throw $e;}
app()->forgetInstance(App\Services\ML\PlanningSpendingCohort::class);
if($before!==array_map(fn($path)=>hash_file('sha256',$path),$paths))throw new RuntimeException('Revalidation modified active evidence');
echo "Unchanged checkpoints reproduce saved errors; changed checkpoints rejected; model and evidence hashes preserved.\n";
