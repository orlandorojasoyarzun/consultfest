<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SubscriberForm;
use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriberFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_with_registration_form(): void
    {
        Livewire::test(SubscriberForm::class)
            ->assertSet('isRegistered', false)
            ->assertSee('Nombre')
            ->assertSee('Email');
    }

    public function test_user_can_register_with_valid_data(): void
    {
        Livewire::test(SubscriberForm::class)
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('phone', '+1 555 0100')
            ->call('register')
            ->assertSet('isRegistered', true)
            ->assertSet('name', 'Jane Doe')
            ->assertSet('email', 'jane@example.com');

        $this->assertDatabaseHas('subscribers', [
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);

        // The session stores the new subscriber id so subsequent requests identify them.
        $subscriber = Subscriber::where('email', 'jane@example.com')->first();
        $this->assertNotNull(session('subscriber_id'));
        $this->assertEquals($subscriber->id, session('subscriber_id'));
    }

    public function test_registration_requires_name_email_and_valid_email_format(): void
    {
        Livewire::test(SubscriberForm::class)
            ->set('name', '')
            ->set('email', 'not-an-email')
            ->call('register')
            ->assertHasErrors(['name' => 'required', 'email' => 'email'])
            ->assertSet('isRegistered', false);

        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        Subscriber::factory()->create(['email' => 'taken@example.com']);

        Livewire::test(SubscriberForm::class)
            ->set('name', 'New Person')
            ->set('email', 'taken@example.com')
            ->call('register')
            ->assertHasErrors(['email' => 'unique'])
            ->assertSet('isRegistered', false);

        $this->assertDatabaseCount('subscribers', 1);
    }

    public function test_existing_subscriber_can_update_their_profile(): void
    {
        $subscriber = Subscriber::factory()->create([
            'name' => 'Old Name',
            'email' => 'same@example.com',
        ]);
        session(['subscriber_id' => $subscriber->id]);

        Livewire::test(SubscriberForm::class)
            ->assertSet('name', 'Old Name')
            ->set('name', 'New Name')
            ->call('register')
            ->assertSet('isRegistered', true);

        $this->assertDatabaseHas('subscribers', [
            'id' => $subscriber->id,
            'name' => 'New Name',
            'email' => 'same@example.com',
        ]);

        // Updating with the same email must not trip the unique validator.
        $this->assertDatabaseCount('subscribers', 1);
    }

    public function test_user_can_subscribe_to_an_existing_festival(): void
    {
        $subscriber = Subscriber::factory()->create();
        $festival = Festival::factory()->create(['api_id' => 12345]);

        session(['subscriber_id' => $subscriber->id]);

        Livewire::test(SubscriberForm::class)
            ->call('subscribeToFestival', 12345, 'opening')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subscriptions', [
            'subscriber_id' => $subscriber->id,
            'festival_id' => $festival->id,
            'notification_type' => 'opening',
            'notified_opening' => 0,
        ]);
    }

    public function test_subscribing_to_unknown_festival_does_not_create_it(): void
    {
        // Hardening: unknown api_id must NOT auto-create a Festival row.
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        Livewire::test(SubscriberForm::class)
            ->call('subscribeToFestival', 99999999)
            ->assertHasErrors(['selectedFestivals']);

        $this->assertDatabaseCount('festivals', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_user_can_unsubscribe_from_a_festival(): void
    {
        $subscriber = Subscriber::factory()->create();
        $festival = Festival::factory()->create(['api_id' => 12345]);
        Subscription::create([
            'subscriber_id' => $subscriber->id,
            'festival_id' => $festival->id,
            'notification_type' => 'both',
        ]);
        session(['subscriber_id' => $subscriber->id]);

        Livewire::test(SubscriberForm::class)
            ->call('unsubscribeFromFestival', 12345)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_logout_clears_session_and_resets_state(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        Livewire::test(SubscriberForm::class)
            ->assertSet('isRegistered', true)
            ->call('logout')
            ->assertSet('isRegistered', false)
            ->assertSet('subscriber', null)
            ->assertSet('name', null)
            ->assertSet('email', null);

        $this->assertNull(session('subscriber_id'));
    }

    public function test_subscriptions_property_is_loaded_after_registration(): void
    {
        $subscriber = Subscriber::factory()->create();
        $festival = Festival::factory()->create(['api_id' => 555]);
        session(['subscriber_id' => $subscriber->id]);

        Livewire::test(SubscriberForm::class)
            ->call('subscribeToFestival', 555, 'both')
            ->assertSet('subscriptions', function ($subscriptions) use ($festival) {
                return $subscriptions->count() === 1
                    && $subscriptions->first()->festival_id === $festival->id;
            });
    }
}