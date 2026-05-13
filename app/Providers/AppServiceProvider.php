<?php

namespace App\Providers;

use App\Services\PdfProcessorService;
use App\Services\PdfMetadataService;
use Illuminate\Support\ServiceProvider;
use Smalot\PdfParser\Parser;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Parser::class, fn () => new Parser());

        $this->app->singleton(PdfProcessorService::class, fn ($app) => new PdfProcessorService(
            $app->make(PdfMetadataService::class),
            $app->make(Parser::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
