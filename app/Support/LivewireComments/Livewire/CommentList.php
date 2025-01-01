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
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

use Illuminate\Support\Facades\Log;

class CommentList extends Component
{
    use WithPagination;

    /** @var \Spatie\Comments\Models\Concerns\HasComments */
    public Model $model;

    public int $page = 1;

    public Collection $comments;

    public string $text = '';

    public int $totalComments = 0;

    public bool $isLoadMore = false;

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
       'comment-deleted-id' => 'handleCommentDeleted',
        'test-event' => 'testMethod',
        'comment-added' => 'render',
    ];

//     protected $listeners = [
//    'delete' => '$refresh',
//         'reply-created' => 'saveNotificationSubscription',
//         'comment-added' => 'render',
//         'comment' => 'render',
//         'comments-deleted' => 'render',
//        // 'comment-deleted' => 'adjustPagination',
//        'comment-deleted-id' => 'handleCommentDeleted',
//       //  'comment-deleted-id' => '$refresh',

//     ];

    public function mount(
        bool  $readOnly = false,
        ?bool $hideAvatars = null,
        bool  $hideNotificationOptions = false,
        bool $newestFirst = false,
        bool $noReplies = false,
        bool $noReactions = false,
        bool $loadMore = false, // New parameter to enable Load More
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

        $this->isLoadMore = $loadMore;

        // Calculate total comments
        $this->totalComments = $this->model->comments()->count();

        $this->comments = collect(); // Initialize as empty collection

        Log::info('Comments Mount initialized:', $this->comments->toArray());

        if ($this->isLoadMore) {
            $this->loadMore(); // Load the first batch for Load More
        }
        Log::info('Comments Mount After LoadMore:', $this->comments->toArray());
        // $this->comments = $this->comments->filter(fn($comment) => $comment['id'] !== 192);
      //  $this->handleCommentDeleted(192);
    }

    #[Computed]
    public function paginator()
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
            ->paginate(Config::paginationCount(), ['*'], 'page', $this->page);
    }


    public function loadMore(): void
    {
        if (!$this->isLoadMore) {
            return;
        }


        // Append the comments from the current page
        $this->comments->push(
            ...$this->paginator->getCollection()
        );

        // Update the page number for the next batch
        $this->page++;

        // Check if all comments are loaded
        if ($this->comments->count() >= $this->totalComments) {
            $this->dispatch('$refresh'); // Optionally refresh the component
        }
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

    #[On('comment-deleted-id')]
    public function handleCommentDeleted(int|string $commentId): void
    {
        if (!$commentId) {
            Log::error('No comment ID received in handleCommentDeleted.');
            return;
        }

        logger('Event received:', ['data' => $commentId]);

        Log::info('Handling comment deletion.', ['commentId' => $commentId]);

        // Filter out the deleted comment
        $this->comments = $this->comments->filter(fn($comment) => $comment->id !== $commentId);

        // Recalculate the total comments count
        $this->totalComments = $this->comments->count();

        Log::info('Comments collection after filtering:', $this->comments->toArray());

        // Refresh the frontend
        // $this->dispatch('$refresh');
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

    // #[Computed]
    // public function comments(): LengthAwarePaginator|Collection
    // {
    //     Log::error('comments computed');
    //     if ($this->isLoadMore) {

    //         return $this->comments; // Return the loaded comments for Load More

    //     }

    //     return $this->model
    //         ->comments()
    //         ->with([
    //             'commentator',
    //             'nestedComments' => function (HasMany $builder) {
    //                 if ($this->newestFirst) {
    //                     $builder->latest();
    //                 }
    //             },
    //             'nestedComments.commentator',
    //             'reactions.commentator',
    //             'nestedComments.reactions.commentator',
    //         ])
    //         ->when(
    //             $this->newestFirst,
    //             fn(Builder $builder) => $builder->latest(),
    //             fn(Builder $builder) => $builder->oldest(),
    //         )
    //         ->paginate(Config::paginationCount(), ['*'], Config::paginationPageName());
    // }


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

        // Reset total comments and clear the collection

        $this->comments = collect(); // Reset the comments collection to an empty collection


        // Emit an event to refresh the comments list
       // $this->dispatch('comments-deleted');

    }

    public function testMethod(): void
    {
        Log::info('Test event triggered successfully.');
        dd('Method executed');
    }

    public function deleteComment($commentId)
    {
        $comment = $this->comments->firstWhere('id', $commentId);

        $this->authorize('delete', $comment);

        $comment->delete();

        $this->handleCommentDeleted($commentId);
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
