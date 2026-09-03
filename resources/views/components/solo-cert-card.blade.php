<li class="text-lg mb-2">
    <div class='flex flex-row items-center gap-4 py-3'>
        <img class='w-17 h-17 rounded-full shrink-0' src="{{ $soloCert->user->profile_image_url }}" alt="">

        <div class='flex flex-col min-w-0 flex-1 gap-1'>
            <div class='flex items-center justify-between gap-2'>
                <a class='font-bold text-xl truncate' href={{ route('users.show', $soloCert->user->id) }}>{{ $soloCert->user->name }}</a>
                <span class='badge badge-lg border-0 shrink-0'>{{ $soloCert->position }}</span>
            </div>

            <h2 class='text-lg'>Expires on {{ $soloCert->expires->format('Y-m-d') }}</h2>
        </div>
    </div>
</li>
