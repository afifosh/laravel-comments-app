<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Spatie\Comments\Notifications\PendingCommentNotification;
use App\Support\LivewireComments\Livewire\CommentComponent as LivewireCommentComponent;
use App\Support\LivewireComments\Livewire\CommentsComponent as LivewireCommentsComponent;
use Spatie\LivewireComments\Livewire\CommentComponent;
use Spatie\LivewireComments\Livewire\CommentsComponent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $loader = AliasLoader::getInstance();
        $loader->alias(CommentComponent::class, LivewireCommentComponent::class);
        $loader->alias(CommentsComponent::class, LivewireCommentsComponent::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        PendingCommentNotification::sendTo(function () {
            return User::where('email', 'freek@spatie.be')->first();
        });
    }
}
