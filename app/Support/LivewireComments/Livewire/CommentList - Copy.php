<?php

namespace App\Support\LivewireComments\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Comments\Enums\NotificationSubscriptionType;
use Spatie\LivewireComments\Support\Config;

use Illuminate\Support\Facades\Log;

class CommentListOLD extends Component
{
    use WithPagination;

    /** @var \Spatie\Comments\Models\Concerns\HasComments */
    public Model $model;

    public string $text = '';

    public int $totalComments = 0;

    public bool $writable;
    public bool $showAvatars;
    public bool $showNotificationOptions;
    public bool $newestFirst;
    public string $selectedNotificationSubscriptionType = '';
    public ?string $noCommentsText = null;
    public bool $showReplies;
    public bool $showReactions;

    // protected $queryString = [
    //     'page' => ['except' => 1], // Ensure page is included in the query string
    // ];
    

    protected $listeners = [
   // 'delete' => '$refresh',
        'reply-created' => 'saveNotificationSubscription',
        'comment-added' => 'render',
        'comments-deleted' => 'render',
        'comment-deleted' => 'adjustPagination',

    ];

    public function adjustPagination(): void
    {
        // Recalculate the total number of comments
        $totalComments = $this->model->comments()->count();
    
        if ($totalComments === 0) {
            // Reset to the first page if no comments remain
            $this->resetPage();
        } elseif ($this->comments()->isEmpty() && $this->getPage() > 1) {
            // Navigate to the previous page if the current page is empty
            $this->previousPage();
        }
    }
    
    public function adjustPaginatssion(): void
    {
        // Recalculate the total number of comments
        $this->totalComments = $this->model->comments()->count();
    
        // Handle scenarios with no comments
        if ($this->totalComments == 0) {
            // Reset to the first page
            $this->resetPage(Config::paginationPageName());
            $this->dispatch('$refresh'); // Refresh the component
            return;
        }
    
        // Handle empty page scenarios
        if ($this->comments()->isEmpty() && $this->page > 1) {
            // Navigate to the previous page if not the first
            $this->resetPage(Config::paginationPageName());
        } else {
            // Refresh the view if the current page is valid
            $this->resetPage(Config::paginationPageName());
            $this->dispatch('$refresh');
        }
    }


    public function mount(
        bool  $readOnly = false,
        ?bool $hideAvatars = null,
        bool  $hideNotificationOptions = false,
        bool $newestFirst = false,
        bool $noReplies = false,
        bool $noReactions = false,
    ): void {
        $this->writable = ! $readOnly;
        $this->showReplies = ! $noReplies;
        $this->showReactions = ! $noReactions;
        $this->newestFirst = $newestFirst;
        $this->showNotificationOptions = ! $hideNotificationOptions;

        $showAvatars = is_null($hideAvatars)
            ? null
            : ! $hideAvatars;

        $this->showAvatars = $showAvatars ?? Config::showAvatars();

        $this->selectedNotificationSubscriptionType = auth()->user()
            ?->notificationSubscriptionType($this->model)?->value ?? NotificationSubscriptionType::Participating->value;

        // Calculate total comments
        $this->totalComments = $this->model->comments()->count();

    }


    public function comment(): void
    {
        $this->validate(['text' => 'required']);

        $this->model->comment($this->text);

        $this->text = '';

        $this->newestFirst
            ? $this->resetPage(Config::paginationPageName())
            : $this->gotoPage($this->comments->lastPage(), Config::paginationPageName());

        $this->saveNotificationSubscription();

        $this->dispatch('comment');
    }

    public function updateSelectedNotificationSubscriptionType($type): void
    {
        $this->selectedNotificationSubscriptionType = $type;

        $this->saveNotificationSubscription();
    }

    public function saveNotificationSubscription(): void
    {
        if (! $this->showNotificationOptions) {
            return;
        }

        /** @var \Spatie\Comments\Models\Concerns\Interfaces\CanComment $currentUser */
        $currentUser = auth()->user();

        if (! $currentUser) {
            return;
        }

        $type = NotificationSubscriptionType::from($this->selectedNotificationSubscriptionType);

        if ($type === NotificationSubscriptionType::None) {
            $currentUser->unsubscribeFromCommentNotifications($this->model);

            return;
        }

        $currentUser->subscribeToCommentNotifications(
            $this->model,
            NotificationSubscriptionType::from($this->selectedNotificationSubscriptionType)
        );
    }

    #[Computed]
    public function comments(): LengthAwarePaginator
    {
        return $this->model
            ->comments()
            ->with([
                'commentator',
                'nestedComments' => function (HasMany $builder) {
                    if ($this->newestFirst) {
                        $builder->latest();
                    }
                },
                'nestedComments.commentator',
                'reactions.commentator',
                'nestedComments.reactions.commentator',
            ])
            ->when(
                $this->newestFirst,
                fn(Builder $builder) => $builder->latest(),
                fn(Builder $builder) => $builder->oldest(),
            )
            ->paginate(Config::paginationCount(), ['*'], Config::paginationPageName());
    }


    public function deleteAllComments(): void
{
    // // Ensure the user has permission to delete comments
    // if (!auth()->user() || !auth()->user()->can('delete', $this->model)) {
    //     abort(403, 'Unauthorized action.');
    // }

    // Delete all comments associated with the model
    $this->model->comments()->delete();

    // Recalculate total comments
    $this->totalComments = $this->model->comments()->count();

    // Emit an event to refresh the comments list
    $this->dispatch('comments-deleted');

    session()->flash('message', 'All comments have been deleted successfully.');
}

    public function render(): View
    {
        //return view('comments::livewire.comments');

        $this->totalComments = $this->model->comments()->count(); // Recalculate the total comments

        return view('livewire.comments.comment-list');
    }

    public function paginationView(): string
    {
        if (view()->exists(Config::paginationTheme())) {
            return Config::paginationTheme();
        }

        if (view()->exists('livewire::' . Config::paginationTheme())) {
            return 'livewire::' . Config::paginationTheme();
        }

        return 'livewire::tailwind';
    }
}
