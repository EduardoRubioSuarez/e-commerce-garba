@props(['title', 'subtitle' => null])
<header class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-3xl font-semibold tracking-tight">{{ $title }}</h1>
        @if($subtitle)<p class="mt-2 max-w-2xl leading-7 text-slate-600">{{ $subtitle }}</p>@endif
    </div>
    @if(! $slot->isEmpty())<div class="flex flex-wrap items-center gap-3">{{ $slot }}</div>@endif
</header>
