<?php

namespace App\Providers;

use App\Contracts\ImageGeneratorContract;
use App\Services\LaravelAiImageGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap providers by changing config/image.php (or the IMAGE_* env vars)
        // — nothing else in the app references a concrete generator.
        $this->app->bind(ImageGeneratorContract::class, function () {
            return new LaravelAiImageGenerator(
                provider: config('image.provider'),
                model: config('image.model'),
                quality: config('image.quality'),
                timeout: config('image.timeout'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
