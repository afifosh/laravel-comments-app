@php
    use Spatie\Comments\Enums\NotificationSubscriptionType;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Gate;
@endphp

<section class="comments {{ $newestFirst ? 'comments-newest-first' : '' }}">

    @if ($newestFirst)
        @include('comments::livewire.partials.newComment')
    @endif

    @if (! $newestFirst)
        @include('comments::livewire.partials.newComment')
    @endif
</section>
