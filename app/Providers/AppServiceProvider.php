<?php

namespace App\Providers;

use App\Interfaces\AuthInterface;
use App\Interfaces\CartInterface;
use App\Interfaces\ContactInterface;
use App\Interfaces\CourseInterface;
use App\Interfaces\DashboardInterface;
use App\Interfaces\EbookInterface;
use App\Interfaces\InstructorInterface;
use App\Interfaces\OrderInterface;
use App\Interfaces\SearchInterface;
use App\Interfaces\TemplateInterface;
use App\Interfaces\UserInterface;
use App\Interfaces\VideoInterface;
use App\Repositories\AuthRepository;
use App\Repositories\CartRepository;
use App\Repositories\ContactRepository;
use App\Repositories\CourseRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\EbookRepository;
use App\Repositories\InstructorRepository;
use App\Repositories\OrderRepository;
use App\Repositories\SearchRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoRepository;
use App\Support\ActivityBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthInterface::class, AuthRepository::class);
        $this->app->bind(CourseInterface::class, CourseRepository::class);
        $this->app->bind(VideoInterface::class, VideoRepository::class);
        $this->app->bind(EbookInterface::class, EbookRepository::class);
        $this->app->bind(TemplateInterface::class, TemplateRepository::class);
        $this->app->bind(CartInterface::class, CartRepository::class);
        $this->app->bind(SearchInterface::class, SearchRepository::class);
        $this->app->bind(ContactInterface::class, ContactRepository::class);
        $this->app->bind(InstructorInterface::class, InstructorRepository::class);
        $this->app->bind(DashboardInterface::class, DashboardRepository::class);
        $this->app->bind(UserInterface::class, UserRepository::class);
        $this->app->bind(OrderInterface::class, OrderRepository::class);
        $this->app->singleton(ActivityBuilder::class, function () {
            return new ActivityBuilder();
        });
    }

    public function boot(): void
    {
        //
    }
}