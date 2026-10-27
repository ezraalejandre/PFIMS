<?php
namespace Tests\Feature;

use App\Services\AutomaticModelRetraining;
use App\Services\MLService;
use App\Services\ProjectCostSnapshotService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class WeeklyRetrainingPolicyTest extends TestCase
{
    public function test_record_changes_only_capture_observations_without_training_or_queueing(): void
    {
        Queue::fake();
        $this->mock(MLService::class, fn ($mock) => $mock->shouldNotReceive('retrain'));
        $this->mock(ProjectCostSnapshotService::class, function ($mock) {
            $mock->shouldReceive('captureAfterCommit')->once()->with(12, 'data_change');
            $mock->shouldReceive('captureAfterCommit')->once()->with(34, 'data_change');
        });
        app(AutomaticModelRetraining::class)->afterDataChange([12,12,34,null,0]);
        Queue::assertNothingPushed();
    }

    public function test_automatic_retraining_is_registered_once_for_monday_at_two_am(): void
    {
        $events=collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command ?? '', 'ml:retrain'));
        $this->assertCount(1,$events);
        $event=$events->first();
        $this->assertSame('0 2 * * 1',$event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
