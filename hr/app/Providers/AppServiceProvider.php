<?php

namespace App\Providers;

use App\Models\Person;
use Illuminate\Support\Facades\Route;
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
        Route::bind('person', function (string $value) {
            $schoolId = auth()->user()?->school_id;
            abort_unless($schoolId, 403);

            return Person::query()->where('school_id', $schoolId)->findOrFail($value);
        });
    }
}
