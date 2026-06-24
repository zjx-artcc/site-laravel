@unless(count($staff) == 0)
    <div class="divider my-3"></div>
    <h2 class="text-base font-semibold mb-2">{{ $positionTitle }}</h2>

    <div class="flex flex-col gap-1 pl-2">
        @foreach($staff as $staffMember)
            <a href="{{route('users.show', ['user' => $staffMember->user->id])}}"
            class="text-lg hover:underline whitespace-nowrap"
            >
                {{$staffMember->user->first_name.' '.$staffMember->user->last_name}} ({{$staffMember->user->rating->mapToString()}})
            </a>
        @endforeach
    </div>
@endunless
