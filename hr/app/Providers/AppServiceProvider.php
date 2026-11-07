<?php

namespace App\Providers;

use App\Hr\Roles;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Route;
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
        Route::bind('person', function (string $value) {
            $schoolId = auth()->user()?->school_id;
            abort_unless($schoolId, 403);

            return Person::query()->where('school_id', $schoolId)->findOrFail($value);
        });

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $place = null;
            if ($user instanceof User && Roles::isCasual($user) && $user->person_id) {
                $place = Person::query()->whereKey($user->person_id)->value('neighbourhood');
            }
            $view->with('placeName', filled($place) ? $place : null);
        });
    }
}
