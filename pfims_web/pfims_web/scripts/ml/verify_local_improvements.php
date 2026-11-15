<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\ProjectRemainingCostReview;
use App\Services\ML\IndependentProjectHoldout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

App\Services\ML\LocalImprovementWorkbench::assertLocal();
$count = 0;
function verify($condition, $message): void { global $count; if (! $condition) throw new RuntimeException($message); $count++; }
function rejects(callable $action): void { try { $action(); } catch (ValidationException) { verify(true, ''); return; } throw new RuntimeException('Invalid review accepted'); }
$service = new ProjectRemainingCostReview;
$input = ['excludes_recorded_costs' => true, 'items' => [
    ['description' => 'Remaining concrete', 'kind' => 'committed', 'resource' => 'material', 'remaining_quantity' => 2.5, 'unit_cost' => 100, 'reference' => 'TEST-PO'],
    ['description' => 'Remaining labor', 'kind' => 'uncommitted', 'resource' => 'labor', 'remaining_quantity' => 3, 'unit_cost' => 200, 'variation_reference' => 'TEST-VARIATION'],
]];
$normalized = $service->normalize($input);
verify($normalized['committed_remaining'] === 250.0 && $normalized['uncommitted_remaining'] === 600.0, 'Remaining cost arithmetic failed');
rejects(fn () => $service->normalize(array_replace($input, ['excludes_recorded_costs' => false])));
$bad = $input; $bad['items'][0]['reference'] = ''; rejects(fn () => $service->normalize($bad));
$bad = $input; $bad['items'][0]['remaining_quantity'] = 0; rejects(fn () => $service->normalize($bad));
$bad = $input; $bad['items'][] = $bad['items'][0]; rejects(fn () => $service->normalize($bad));
$before = DB::table('project_remaining_cost_reviews')->count();
DB::beginTransaction();
try {
    $id = DB::table('project_tbl')->where('status', '<>', 'Completed')->whereNull('actual_end_date')->orderBy('project_id')->value('project_id');
    $saved = $service->save((int) $id, $input, null);
    verify(! $saved['needs_refresh'], 'New review incorrectly stale');
    verify(abs($saved['review_final_cost'] - $saved['recorded_spend'] - 850) < .01, 'Recorded costs double counted');
    DB::table('project_remaining_cost_reviews')->where('review_id', $saved['review_id'])->update(['recorded_spend' => $saved['recorded_spend'] + 1]);
    verify($service->latest((int) $id)['review_final_cost'] === null, 'Changed spending did not suppress stale estimate');
    DB::table('project_remaining_cost_reviews')->where('review_id', $saved['review_id'])->update(['recorded_spend' => $saved['recorded_spend'], 'recorded_at' => now()->subDays(15)]);
    verify($service->latest((int) $id)['needs_refresh'], 'Old estimate accepted');
    $next = $service->save((int) $id, $input, null);
    verify($next['review_id'] !== $saved['review_id'] && DB::table('project_remaining_cost_reviews')->count() === $before + 2, 'Review history overwritten');
    $completed = DB::table('project_tbl')->where('status', 'Completed')->value('project_id');
    rejects(fn () => $service->save((int) $completed, $input, null));
    try { app(IndependentProjectHoldout::class)->reserve((int) $completed); throw new RuntimeException('Completed project accepted as fresh'); }
    catch (RuntimeException $e) { verify(str_contains($e->getMessage(), 'incomplete'), 'Incorrect reservation rejection'); }
    $archive = json_decode(gzdecode(file_get_contents(storage_path('app/ml-datasets/live-20261009/planning-spending-source-104016.json.gz'))), true, 512, JSON_THROW_ON_ERROR);
    $builder = app(App\Services\ML\PlanningSpendingCohort::class);
    $full = $builder->build($archive, true);
    $fixtureProject = (int) $full['records']->first()->project_id;
    DB::table('ml_validation_reservations')->insert(['project_id' => $fixtureProject, 'reserved_at' => now(), 'purpose' => 'Transactional exclusion test fixture']);
    verify(! $builder->build($archive)['records']->contains('project_id', $fixtureProject), 'Reserved completed project entered archived-source training');
    verify($builder->build($archive, true)['records']->contains('project_id', $fixtureProject), 'Explicit independent-evaluation access missing');
} finally { DB::rollBack(); }
verify(DB::table('project_remaining_cost_reviews')->count() === $before, 'Review fixtures were not rolled back');
$source = json_decode(file_get_contents(storage_path('app/ml-improvement/source.json')), true, 512, JSON_THROW_ON_ERROR);
verify(array_intersect(array_column($source['records'], 'project_id'), IndependentProjectHoldout::ids()) === [], 'Reserved project leaked into training source');
$routes = app('router')->getRoutes();
foreach (['GET', 'POST'] as $method) {
    $route = $routes->match(Illuminate\Http\Request::create('/api/ml/remaining-work/1', $method));
    verify(in_array('auth', $route->gatherMiddleware(), true) && in_array('role.portal:admin', $route->gatherMiddleware(), true), 'Review route lacks authentication/role restriction');
}
$request = Illuminate\Http\Request::create('/api/ml/remaining-work/1', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$request->setUserResolver(fn () => new App\Models\User(['role' => 'operations']));
$response = (new App\Http\Middleware\EnsureRoleAccess)->handle($request, fn () => response('unexpected'), 'admin');
verify($response->getStatusCode() === 403, 'Unauthorized role can access remaining-work reviews');
echo "$count focused checks passed; database fixtures rolled back.\n";
