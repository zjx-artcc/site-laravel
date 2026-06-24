<div class="card border-2 border-base-300 p-5 {{}}">
    @unless(is_null($title ?? null))
        <h1 class="card-title text-2xl font-light">{{ $title }}</h1>
    @endunless

    <div @class('card-body p-0')>
        {{  $slot }}
    </div>
</div>
