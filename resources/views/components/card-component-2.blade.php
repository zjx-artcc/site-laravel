<div class="card bg-base-100 rounded-xl shadow-xl p-6 w-full max-w-2xl border border-base-300 {{}}">
    @unless(is_null($title ?? null))
        <div class="flex justify-between text-2xl">
            <h1 class="font-bold">{{ $title }}</h1>
        </div>
    @endunless

    <div @class('card-body p-0')>
        {{  $slot }}
    </div>
</div>
