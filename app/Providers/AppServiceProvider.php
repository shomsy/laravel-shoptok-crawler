<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Shoptok\ShoptokSeleniumService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

/**
 * 🧩 **App Service Provider**
 *
 * The "wiring room" of your Laravel app.
 *
 * 🧠 Think of it like a fuse box — this is where you register or configure
 * global services, singletons, and boot-time settings for the entire project.
 *
 * In this project, it serves **two main purposes**:
 * 1. Registers {@see ShoptokSeleniumService} as a **singleton** (so Selenium isn’t booted multiple times).
 * 2. Configures pagination views to use **Bootstrap 5**.
 *
 * **Why it exists:**
 * - Keeps all app-wide bindings centralized.
 * - Ensures performance and consistency across the app.
 * - Allows smooth dependency injection of core services.
 *
 * 📁 File: `app/Providers/AppServiceProvider.php`
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * 🧱 **Register Services**
     *
     * This method runs *before* the app starts handling requests.
     * You use it to bind interfaces or classes into Laravel's
     * service container so they can be auto-injected later.
     *
     * Here, we make {@see ShoptokSeleniumService} a **singleton**, which means:
     * - It’s created once per request lifecycle.
     * - Every class that asks for it gets the *same instance*.
     * - Prevents multiple ChromeDriver sessions from spawning unnecessarily.
     *
     * @return void
     */
    public function register() : void
    {
        $this->app->singleton(abstract: ShoptokSeleniumService::class);
    }

    /**
     * 🚀 **Bootstrap Services**
     *
     * This method runs *after* all services have been registered.
     * Use it for app-wide configuration or runtime setup.
     *
     * In this case, we tell Laravel's paginator to use
     * Bootstrap 5 styles instead of the default Tailwind layout.
     *
     * This ensures that pagination links look consistent
     * with the rest of your Bootstrap-based frontend.
     *
     * @return void
     */
    public function boot() : void
    {
        Paginator::useBootstrapFive();
    }
}
