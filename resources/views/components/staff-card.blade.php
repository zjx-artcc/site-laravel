<x-card-component :title="$position" class="h-full">
    <div class="flex flex-col h-full content-stretch">
        @unless(is_null($staff))
            <a href="{{route('users.show', ['user' => $staff->user->id])}}" class="text-2xl mb-1">{{ $staff->user->first_name.' '.$staff->user->last_name }} ({{$staff->user->rating->mapToString()}})</a>
            <a href="mailto:zjx-{{ strtolower($staff->title_short) }}@vatusa.net" class="text-base text-base-content/80 hover:underline mb-5">zjx-{{ strtolower($staff->title_short) }}@vatusa.net</a>
        @else
            <h2 class="text-2xl mb-5">Vacant</h2>
        @endunless

        <p class="text-lg">{{$description}}</p>

        {{$slot}}

        <p class="text-lg mt-auto pt-5"><strong>Reports to:</strong> {{$reportsTo}}</p>
    </div>
</x-card-component>
