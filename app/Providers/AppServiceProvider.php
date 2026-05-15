<?php

namespace App\Providers;

use App\Models\Catalogue;
use Illuminate\Support\Facades\View;
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
        View::composer('layouts.app', function ($view) {
            $sidebarCatalogues = Catalogue::orderByDesc('id')->get();
            $latestId = $sidebarCatalogues->first()?->id;

            if ($latestId && ! session()->has('active_catalogue_id')) {
                session(['active_catalogue_id' => $latestId]);
            }

            $activeCatalogueId = session('active_catalogue_id');
            $activeCatalogue   = $sidebarCatalogues->find($activeCatalogueId);

            $view->with([
                'sidebarCatalogues' => $sidebarCatalogues,
                'activeCatalogueId' => $activeCatalogueId,
                'activeCatalogue'   => $activeCatalogue,
            ]);
        });
    }
}
