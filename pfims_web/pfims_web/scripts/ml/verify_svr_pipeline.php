<?php
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\ML\CostModelStore;use App\Services\ML\PortableRbfSvr;use App\Services\ML\ModelPromotionService;
$baseline=storage_path('app/ml-improvement/before-activation-20261115-220218/ml_model.phpml');
$rejected=glob(storage_path('app/rejected-models/*/ml_model.phpml'))[0]??null;
if(!$rejected)throw new RuntimeException('Preserved replacement fixture unavailable');
$root=sys_get_temp_dir().'/pfims-svr-recovery-'.bin2hex(random_bytes(6));mkdir($root,0700,true);
$active=$root.'/storage/app/ml_model.phpml';$backup=$root.'/storage/app/release-backups/pre-release/storage/app/ml_model.phpml';mkdir(dirname($active),0700,true);mkdir(dirname($backup),0700,true);
foreach(['','.meta.json']as$suffix){copy($rejected.$suffix,$active.$suffix);copy($baseline.$suffix,$backup.$suffix);}
$store=new CostModelStore($active,10);$pair=$store->load();
if(!$pair||!$pair['model']instanceof PortableRbfSvr||!hash_equals(hash_file('sha256',$baseline),hash_file('sha256',$active)))throw new RuntimeException('Pre-release SVR was not restored');
if($store->load()['metadata']['model_type']!=='support_vector_regression_rbf')throw new RuntimeException('Restoration did not persist');
$rejection=app(ModelPromotionService::class)->promoteCost($root.'/must-not-exist',new Phpml\Regression\LeastSquares,[],[],true);
if($rejection['activated']!==false||!in_array('established_svr_pipeline',$rejection['policy']['failed_requirements'])||is_file($root.'/must-not-exist'))throw new RuntimeException('Another algorithm was allowed');
$bad=$root.'/invalid/storage/app/ml_model.phpml';mkdir(dirname($bad),0700,true);foreach(['','.meta.json']as$suffix)copy($rejected.$suffix,$bad.$suffix);
if((new CostModelStore($bad,10))->load()!==null)throw new RuntimeException('Unsupported replacement accepted without valid backup');
echo "Verified SVR recovery from pre-release backup, exact estimator hash, persisted restoration, rejection of other algorithms, and fail-closed behavior.\n";
