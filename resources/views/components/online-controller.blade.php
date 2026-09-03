<div class='flex flex-row align-middle justify-left'>
    @unless(is_null($user))
        <img class='w-17 h-17 rounded-full place-self-center' src="{{ $user->profile_image_url }}" alt="">
    @else
        <img class='w-17 h-17 rounded-full place-self-center' src="{{ asset('images/default_profile.jpg') }}" alt="">
    @endunless
    <div class='ml-5 py-4 flex flex-col relative w-full'>
        <strong class='text-xl'>{{ $callsign }}</strong>

        @unless(is_null($user))
            <a href={{ route('users.show', $user->id) }} class='text-lg'>{{  $user->name.' - '.$user->id }}</a>
        @else
            <a class='text-lg'>Unknown User - {{ $userId }}</a>
        @endUnless
        <h2 class='absolute top-4 right-0 text-lg'>{{ (new DateTime())->diff($onlineSince)->format('%H:%I') }}</h2>
    </div>
</div>
