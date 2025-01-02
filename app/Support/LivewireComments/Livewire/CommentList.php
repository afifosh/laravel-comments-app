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

class CommentList extends Component
{
    use WithPagination;

    /** @var \Spatie\Comments\Models\Concerns\HasComments */
    public Model $model;

    public $comments;

    public $limit = 2; // Number of comments to load per scroll
    public $hasMoreTop = true; // Flag to check if there are more comments above
    public $hasMoreBottom = true; // Flag to check if there are more comments below

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
        'comment-added' => 'newCommentAdded',
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

        // $this->comments = collect(); // Initialize as empty collection
        $this->loadInitialComments((int) request()->comment);
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

    // #[On('comment-deleted-id')]
    public function handleCommentDeleted(int|string $commentId): void
    {
        if (!$commentId) {
            Log::error('No comment ID received in handleCommentDeleted.');
            return;
        }

        Log::info('Handling comment deletion.', ['commentId' => $commentId]);

        // Filter out the deleted comment
        $this->comments = $this->comments->filter(fn($comment) => $comment->id !== $commentId);

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
        // Delete all comments associated with the model
        $this->model->comments()->delete();

        $this->comments = collect(); // Reset the comments collection to an empty collection
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

        $this->totalComments = $this->commentsQuery()->count(); // Recalculate the total comments

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

    public function commentsQuery()
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
            ]);
            // ->when(
            //     $this->newestFirst,
            //     fn(Builder $builder) => $builder->latest(),
            //     fn(Builder $builder) => $builder->oldest(),
            // );
    }

    public function loadInitialComments($initialCommentId = null)
    {
        $query = $this->commentsQuery();

        if ($initialCommentId) {
            $pivotComment = $this->commentsQuery()->find($initialCommentId);
            if ($pivotComment) {
                $olderComments = $this->commentsQuery()->where('created_at', '<=', $pivotComment->created_at)
                    ->orderBy('created_at', 'desc')
                    ->take($this->limit)
                    ->get()
                    ->reverse();

                $newerComments = $this->commentsQuery()->where(function ($q) use ($pivotComment) {
                    $q->where('created_at', '>', $pivotComment->created_at);//->orWhere('id', $pivotComment->id);
                })
                    ->orderBy('created_at')
                    ->take($this->limit)
                    ->get();

                $this->comments = $olderComments->merge($newerComments);
                if ($olderComments->count() < $this->limit) {
                    $this->hasMoreTop = false;
                }

                if ($newerComments->count() < $this->limit) {
                    $this->hasMoreBottom = false;
                }
            }
        } else {

            // load first chunk of comments
            // $this->comments = $query->take($this->limit)->get();
            // if ($this->comments->count() < $this->limit) {
            //     $this->hasMoreBottom = false;
            // }
            // $this->hasMoreTop = false;


            // load Last chunk of comments
            $this->comments = $query->latest()->take($this->limit)->get()->reverse();

            if ($this->comments->count() < $this->limit) {
                $this->hasMoreTop = false;
            }

            $this->hasMoreBottom = false;
        }
    }

    public function loadMoreTop()
    {
        if ($this->hasMoreTop && $this->comments->isNotEmpty()) {
            $firstComment = $this->comments->first();

            $olderComments = $this->commentsQuery()->where('created_at', '<', $firstComment->created_at)
                ->orderBy('created_at', 'desc')
                ->take($this->limit)
                ->get()
                ->reverse();

            if ($olderComments->isEmpty()) {
                $this->hasMoreTop = false;
            }

            $this->comments = $olderComments->merge($this->comments);
        }
    }

    public function loadMoreBottom()
    {
        if ($this->hasMoreBottom && $this->comments->isNotEmpty()) {
            $lastComment = $this->comments->last();

            $newerComments = $this->commentsQuery()->where('created_at', '>', $lastComment->created_at)
                ->orderBy('created_at')
                ->take($this->limit)
                ->get();

            if ($newerComments->isEmpty()) {
                $this->hasMoreBottom = false;
            }

            $this->comments = $this->comments->merge($newerComments);
        }
    }

    public function newCommentAdded()
    {
        $this->hasMoreBottom = true;

        // method 1 - Render latest comment
        // $this->hasMoreTop = true;
        // $this->loadInitialComments($this->commentsQuery()->latest()->first()->id);

        // method 2 - Render next chunk
        $this->loadMoreBottom();

    }
}
