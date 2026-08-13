<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class PasswordReminderNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications_tbl');
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->string('status')->default('Active');
            $table->boolean('first_login_verification_required')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('notifications_tbl', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('user_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->string('kind')->default('info');
            $table->string('filter')->default('alerts');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });

        $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncSystemAlerts')->zeroOrMoreTimes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('notifications_tbl');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_private_reminder_persists_until_the_new_user_changes_their_password(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@gmail.com', 'password' => Hash::make('admin'),
            'role' => 'admin', 'status' => 'Active',
        ]);

        $this->actingAs($admin)->postJson('/users', [
            'name' => 'New User',
            'email' => 'newperson@gmail.com',
            'role' => 'operations',
            'status' => 'Active',
        ])->assertOk();

        $newUser = User::where('email', 'newperson@gmail.com')->firstOrFail();
        $reminder = AppNotification::where('type', 'password_change_reminder')->firstOrFail();

        $this->assertSame($newUser->id, $reminder->user_id);
        $this->assertTrue($reminder->requires_acknowledgement);

        $this->actingAs($admin)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonMissing(['notification_id' => $reminder->notification_id]);

        $this->actingAs($newUser)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'notification_id' => $reminder->notification_id,
                'action_url' => url('/osettings?change_password=1'),
            ]);

        $this->post('/logout');
        $this->assertDatabaseHas('notifications_tbl', ['notification_id' => $reminder->notification_id]);

        $this->actingAs($newUser)->postJson('/change-password', [
            'current_password' => 'newperson',
            'new_password' => 'newperson',
            'new_password_confirmation' => 'newperson',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('new_password')
            ->assertJsonPath('errors.new_password.0', 'The new password must be different from your current password.');

        $this->assertTrue(Hash::check('newperson', $newUser->fresh()->password));
        $this->assertDatabaseHas('notifications_tbl', ['notification_id' => $reminder->notification_id]);

        $this->actingAs($newUser)->postJson('/change-password', [
            'current_password' => 'newperson',
            'new_password' => 'SecurePass123!',
            'new_password_confirmation' => 'SecurePass123!',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('notifications_tbl', ['notification_id' => $reminder->notification_id]);
        $this->assertTrue(Hash::check('SecurePass123!', $newUser->fresh()->password));
    }
}
