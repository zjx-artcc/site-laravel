@props(['user'])

<img src="{{ $user->profile_image_url }}" alt="{{ $user->name }}"
    {{ $attributes->merge(['class' => 'rounded-full object-cover border border-base-300 shrink-0']) }} />
