@php
    use Spatie\Comments\Enums\NotificationSubscriptionType;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Gate;
@endphp

<section class="comments {{ $newestFirst ? 'comments-newest-first' : '' }}">
    <header class="comments-header">
        @if($writable && $showNotificationOptions && Auth::check())
            <div x-data="{ subscriptionsOpen: false}" class="comments-subscription">

                <p class="mb-4">
                    <span>{{ $totalComments }}</span> Comments
                </p>

                <button wire:click="deleteAllComments" class="btn btn-danger">
                    Delete All Comments
                </button>
                
                <button @click.prevent="subscriptionsOpen = true" class="comments-subscription-trigger">
                    {{ NotificationSubscriptionType::from($selectedNotificationSubscriptionType)->longDescription() }}
                </button>
                <x-comments::modal
                    bottom
                    compact
                    x-show="subscriptionsOpen"
                    @click.outside="subscriptionsOpen = false"
                >
                    @foreach(NotificationSubscriptionType::cases() as $case)
                        <button class="comments-subscription-item" @click="subscriptionsOpen = false" wire:click="updateSelectedNotificationSubscriptionType('{{ $case->value }}')">
                            {{ $case->description() }}
                        </button>
                    @endforeach
                </x-comments::modal>
            </div>
        @endif
    </header>

    @forelse($this->comments as $comment)
        @continue(! Gate::check('see', $comment))
        <livewire:comments-comment
            :key="$comment->id"
            :comment="$comment"
            :show-avatar="$showAvatars"
            :newest-first="$newestFirst"
            :writable="$writable"
            :show-replies="$showReplies"
            :show-reactions="$showReactions"
        />
    @empty
        <p class="comments-no-comment-yet">{{ $noCommentsText ?? __('comments::comments.no_comments_yet') }}</p>
    @endforelse



    @if ($isLoadMore)
        @if ($comments->count() < $totalComments)
        <div class="w-full flex flex-col justify-center items-center text-center gap-2">
                {{-- @if(!$disableLoadMore) --}}
                    <button type="button" wire:click="loadMore"
                            class="bg-slate-100 disabled:bg-slate-50 px-3 py-2 text-slate-500 border-slate-100 rounded hover:cursor-pointer w-fit hover:bg-slate-200">
                        Load more
                    </button>
                {{-- @endif --}}
            </div>
        @endif
    @else
    
        @if ($this->comments->hasPages())
        {{ $this->comments->links() }}
        @endif

    @endif


</section>
