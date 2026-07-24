<?php

namespace Tests\Unit\Services;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_can_be_instantiated(): void
    {
        $service = new NotificationService();
        $this->assertInstanceOf(NotificationService::class, $service);
    }

    public function test_check_and_notify_deadline_returns_collection(): void
    {
        $service = new NotificationService();
        $result = $service->checkAndNotifyDeadline(7);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_check_and_notify_opening_returns_collection(): void
    {
        $service = new NotificationService();
        $result = $service->checkAndNotifyOpening(7);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_no_notifications_sent_when_no_festivals_match(): void
    {
        $subscriber = Subscriber::factory()->create();
        $festival = Festival::factory()->create([
            'deadline' => Carbon::parse('+30 days'),
        ]);
        Subscription::factory()->create([
            'subscriber_id' => $subscriber->id,
            'festival_id' => $festival->id,
            'notification_type' => 'deadline',
        ]);

        $service = new NotificationService();
        $result = $service->checkAndNotifyDeadline(7);

        $this->assertEquals(0, $result->count());
    }
}
