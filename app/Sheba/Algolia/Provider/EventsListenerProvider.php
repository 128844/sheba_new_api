<?php namespace App\Sheba\Algolia\Provider;


use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use App\Sheba\Algolia\Events\PartnerPosServiceCreated as PartnerPosServiceCreatedEvent;
use App\Sheba\Algolia\Events\PartnerPosServiceSaved as PartnerPosServiceSavedEvent;
use App\Sheba\Algolia\Listeners\PartnerPosServiceCreated as PartnerPosServiceCreatedListener;
use App\Sheba\Algolia\Listeners\PartnerPosServiceSaved as PartnerPosServiceSavedListener;

class EventsListenerProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // TODO: Implement register() method.DalEventsListenerProvider
    }

    /**
     * @param Dispatcher $events
     */
    public function boot(Dispatcher $events)
    {
        parent::boot($events);
        $events->listen(PartnerPosServiceCreatedEvent::class, PartnerPosServiceCreatedListener::class);
        $events->listen(PartnerPosServiceSavedEvent::class, PartnerPosServiceSavedListener::class);
    }


    }