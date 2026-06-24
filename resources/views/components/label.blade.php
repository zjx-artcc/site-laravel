<div class='flex flex-col mb-5 w-full'>
    <label class='text-md'>{{ $label }}</label>

    @if (is_null($value) || strlen($value) == 0)
        <input class='input input-md w-full' disabled value="Unassigned" />
    @else
        <input class='input input-md w-full' disabled value="{{ $value }}" />
    @endif
</div>
