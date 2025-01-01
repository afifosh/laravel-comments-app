<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test app</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    @vite(['resources/css/app.css'])

    @livewireStyles

    @laravelCommentsLivewireStyles

    @laravelCommentsLivewireScripts
</head>
<body>
<div class="flex m-4 justify-evenly">
    <div>
        Login as:
        <a class="underline" href="/login/freek">Admin</a>
        <a class="underline" href="/login/sebastian">User 1</a>
        <a class="underline" href="/login/willem">User 2</a>
    </div>
    <div>
        @auth
            Logged in as {{ auth()->user()->name }} <a class="text-sm underline" href="/logout">Logout</a>
        @elseauth
            Not logged in
        @endauth
    </div>
</div>
<div class="max-w-2xl mx-auto p-8">
    <h1 class="text-lg font-bold mb-4">{{ ucfirst($post->title) }}</h1>
    <div class="pb-12 border-b mb-12">
        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
            tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
            quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
            consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
            cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
            proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
    </div>

    @auth
        {{-- <livewire:comments :model="$post"/> --}}

        {{-- <livewire:comment-list 
        :model="$post"
        no-replies
        :no-reactions="true"
        :use-metronic="true"
        /> --}}

        <livewire:comment-list 
        :loadMore="true"
        :model="$post"
        no-replies
        :no-reactions="true"
        :use-metronic="true"
        />

        <livewire:comment-form :model="$post" />
        
    @endauth

    @guest
        <livewire:comments read-only :model="$post"/>

        <p class="comments-no-comment-yet">
            Log in to make a comment...
        </p>
    @endguest
</div>

@livewireScripts
</body>
</html>
