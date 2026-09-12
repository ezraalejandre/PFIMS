<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = json_decode(file_get_contents(__DIR__.'/evc-schema.json'), true);
foreach (array_keys($schema['tables']) as $table) {
    echo $table, '\n', json_encode(Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM `'.$table.'`')), PHP_EOL;
}
echo 'NOTIFICATIONS ', json_encode(Illuminate\Support\Facades\DB::table('notifications_tbl')->select('reference_type')->distinct()->get()), PHP_EOL;
