@php
    use Spatie\Comments\Enums\NotificationSubscriptionType;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Gate;
@endphp

<section class="comments {{ $newestFirst ? 'comments-newest-first' : '' }}">
    <header class="comments-header">
        <p>
            <span>Loaded {{$comments->count()}} of {{ $totalComments }}</span> Comments
        </p>
        @if($writable && $showNotificationOptions && Auth::check())
            <div x-data="{ subscriptionsOpen: false}" class="comments-subscription">
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

        @if($writable && $comments->count() > 0)
            <button wire:click="deleteAllComments" class="comments-button is-small is-danger ms-2">
                Delete All Comments
            </button>
        @endif
    </header>

    <div
        class="comments-list"
        style="max-height: 500px; overflow-y: auto; overflow-x: hidden;"

        x-data="{ previousScrollHeight: 0 }"
        x-init="$watch('previousScrollHeight', value => {
            if (value > 0) {
                const newScrollHeight = $el.scrollHeight;
                $el.scrollTop += newScrollHeight - value;
            }
        })"
        x-on:livewire-loaded-more-top.window="previousScrollHeight = $el.scrollHeight"
    >

    @if ($hasMoreTop)
        <div
            class="w-full flex flex-col justify-center items-center text-center gap-2"
        >
            <button
                type="button"
                wire:loading.remove
                x-intersect.full="$wire.loadMoreTop"
                class="bg-slate-100 disabled:bg-slate-50 px-3 py-2 text-slate-500 border-slate-100 rounded hover:cursor-pointer w-fit hover:bg-slate-200"
            >
                Load more
            </button>

            <div wire:loading wire:target="loadMoreTop" class="w-full flex flex-col justify-center items-center text-center gap-2">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
        </div>
    @endif

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


        @if ($hasMoreBottom)
            <div class="w-full flex flex-col justify-center items-center text-center gap-2">
                <button type="button" x-intersect="$wire.loadMoreBottom" wire:loading.remove wire:target="loadMoreBottom"
                        class="bg-slate-100 disabled:bg-slate-50 px-3 py-2 text-slate-500 border-slate-100 rounded hover:cursor-pointer w-fit hover:bg-slate-200">
                    Load more
                </button>

                <div wire:loading wire:target="loadMoreBottom" class="w-full flex flex-col justify-center items-center text-center gap-2">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
        @endif

    </div>
</section>
