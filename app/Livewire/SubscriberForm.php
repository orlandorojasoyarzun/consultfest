<?php

namespace App\Livewire;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SubscriberForm extends Component
{
    public ?string $name = null;
    public ?string $email = null;
    public ?string $phone = null;
    public bool $notificationsEnabled = true;
    public array $selectedFestivals = [];
    public ?Subscriber $subscriber = null;
    public bool $isRegistered = false;
    public $subscriptions = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('subscribers', 'email')->ignore($this->subscriber?->id),
            ],
            'phone' => 'nullable|string|max:50',
        ];
    }

    public function mount()
    {
        if (session()->has('subscriber_id')) {
            $this->subscriber = Subscriber::find(session('subscriber_id'));
            if ($this->subscriber) {
                $this->name = $this->subscriber->name;
                $this->email = $this->subscriber->email;
                $this->phone = $this->subscriber->phone;
                $this->notificationsEnabled = $this->subscriber->notifications_enabled;
                $this->isRegistered = true;
                $this->loadSubscriptions();
            }
        }
    }

    public function register()
    {
        $this->validate();

        $wasNew = $this->subscriber === null;

        if ($this->subscriber) {
            $this->subscriber->update([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'notifications_enabled' => $this->notificationsEnabled,
            ]);
        } else {
            $this->subscriber = Subscriber::create([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'notifications_enabled' => $this->notificationsEnabled,
            ]);
            session(['subscriber_id' => $this->subscriber->id]);
        }

        $this->isRegistered = true;
        $this->loadSubscriptions();

        session()->flash(
            'subscriber-flash',
            $wasNew ? '¡Listo! Te has registrado correctamente.' : 'Datos actualizados.'
        );

        $this->dispatch('subscriber-registered', subscriberId: $this->subscriber->id);
    }

    public function subscribeToFestival(int $festivalApiId, string $notificationType = 'both')
    {
        if (!$this->subscriber) {
            return;
        }

        $festival = Festival::where('api_id', $festivalApiId)->first();

        if (!$festival) {
            $this->addError('selectedFestivals', 'Festival not found. Sync the catalogue first.');
            return;
        }

        Subscription::updateOrCreate(
            [
                'subscriber_id' => $this->subscriber->id,
                'festival_id' => $festival->id,
            ],
            [
                'notification_type' => $notificationType,
            ]
        );

        $this->selectedFestivals[] = $festival->id;
        $this->loadSubscriptions();
    }

    public function unsubscribeFromFestival(int $festivalApiId)
    {
        if (!$this->subscriber) {
            return;
        }

        $festival = Festival::where('api_id', $festivalApiId)->first();

        if ($festival) {
            Subscription::where('subscriber_id', $this->subscriber->id)
                ->where('festival_id', $festival->id)
                ->delete();

            $this->selectedFestivals = array_filter(
                $this->selectedFestivals,
                fn($id) => $id !== $festival->id
            );
        }
    }

    public function loadSubscriptions()
    {
        if (!$this->subscriber) {
            $this->subscriptions = collect();
            return;
        }

        $this->subscriptions = $this->subscriber->subscriptions()->with('festival')->get();
    }

    public function getSubscriptionsProperty()
    {
        if ($this->subscriptions === null) {
            $this->loadSubscriptions();
        }
        return $this->subscriptions;
    }

    public function logout()
    {
        session()->forget('subscriber_id');
        $this->subscriber = null;
        $this->isRegistered = false;
        $this->name = null;
        $this->email = null;
        $this->phone = null;
        $this->selectedFestivals = [];
        $this->subscriptions = collect();
    }

    public function render()
    {
        return view('livewire.subscriber-form');
    }
}
