@if($time)
    <time datetime="{{ $time->toIso8601ZuluString() }}" {{ $attributes }}>{{ $time->format($format) }}Z</time>
@endif
