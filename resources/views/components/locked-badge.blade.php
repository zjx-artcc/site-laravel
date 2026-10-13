@props([
    'tip' => 'You do not have permission to edit this.',
])

<span {{ $attributes->merge(['class' => 'tooltip']) }} data-tip="{{ $tip }}">
    <i class="fa-solid fa-lock text-warning" aria-hidden="true"></i>
    <span class="sr-only">{{ $tip }}</span>
</span>
