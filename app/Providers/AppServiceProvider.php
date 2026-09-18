<?php

namespace App\Providers;

use App\Http\Middleware\ExpirePanelSession;
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
use App\Support\QueueHealth;
use Illuminate\Auth\Events\Login;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
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

        // Laravel tidak mencatat pekerja antreannya di mana pun, sehingga
        // panel tidak punya cara tahu apakah pekerjaan yang dititipkan benar-
        // benar akan dikerjakan. Peristiwa Looping berbunyi tiap kali pekerja
        // menengok antrean, dan itulah denyut yang dipakai QueueHealth.
        Event::listen(Looping::class, fn () => QueueHealth::recordWorkerHeartbeat());

        // Login mempertahankan isi sesi sebelumnya, jadi batas keras panel
        // harus dihitung ulang dari sini agar tidak mewarisi jam lama.
        Event::listen(Login::class, function (): void {
            $now = Carbon::now()->toIso8601String();

            Session::put(ExpirePanelSession::LOGGED_IN_AT, $now);
            Session::put(ExpirePanelSession::LAST_SEEN_AT, $now);
        });
    }
}
