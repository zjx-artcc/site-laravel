<div class='text-center flex-1 min-w-0'>
    <p class='text-xl sm:text-3xl font-normal'>
        {{ sprintf('%02d:%02d',
            $timeInterval->h + ($timeInterval->d * 24) + ($timeInterval->m * 30 * 24) + ($timeInterval->y * 365 * 24),
            $timeInterval->i,
        ) }}
    </p>
    <h3 class='text-sm sm:text-xl mb-2'>{{ $label }}</h3>
</div>