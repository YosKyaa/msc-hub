<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\ContentRequest;
use App\Models\InventoryBooking;
use App\Models\Project;
use App\Models\RoomBooking;
use App\Models\Tag;
use App\Observers\ContentRequestObserver;
use App\Observers\InventoryBookingObserver;
use App\Observers\RoomBookingObserver;
use App\Policies\AssetPolicy;
use App\Policies\ContentRequestPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TagPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(ContentRequest::class, ContentRequestPolicy::class);

        // Observers
        RoomBooking::observe(RoomBookingObserver::class);
        InventoryBooking::observe(InventoryBookingObserver::class);
        ContentRequest::observe(ContentRequestObserver::class);
    }
}
