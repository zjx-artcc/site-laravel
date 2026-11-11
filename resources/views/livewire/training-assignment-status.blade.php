{{--
    The badge is the whole control: one click opens the status menu.

    The menu is positioned with `fixed` + measured viewport coordinates because
    the table sits inside an `overflow-x-auto` wrapper, which would otherwise
    clip (and scroll away) a normally-positioned dropdown. A fixed element
    escapes that clipping, since no ancestor of <main> establishes a containing
    block for fixed positioning. Note it is NOT teleported to <body> the way the
    Actions menu in this table is: a teleported element lands outside this
    component's root, where wire: bindings go dead.
--}}
<div
    x-data="{
        open: false,
        top: 0,
        left: 0,
        openMenu() {
            // getBoundingClientRect() is already viewport-relative, which is
            // the coordinate space `fixed` uses — no scroll offset to add.
            const rect = $refs.trigger.getBoundingClientRect();
            this.top = rect.bottom + 4;
            this.left = rect.left;
            this.open = true;

            // x-show toggles display:none, so the menu has no measurable size
            // until it is actually shown — hence the $nextTick before nudging it
            // back inside the viewport.
            this.$nextTick(() => {
                const menu = $refs.menu.getBoundingClientRect();
                const gap = 8;

                // Runs off the right edge: slide left so it ends at the edge,
                // never past the left edge on a very narrow screen.
                if (this.left + menu.width > window.innerWidth - gap) {
                    this.left = Math.max(gap, window.innerWidth - menu.width - gap);
                }

                // Runs off the bottom: flip above the badge, and if there is no
                // room there either, sit against the bottom edge.
                if (this.top + menu.height > window.innerHeight - gap) {
                    const above = rect.top - menu.height - 4;

                    this.top = above >= gap
                        ? above
                        : Math.max(gap, window.innerHeight - menu.height - gap);
                }
            });
        },
    }"
    @keydown.escape="open = false"
    {{-- Only fired when the row changes section (see setStatus); the four open
         statuses update in place without a round trip. --}}
    @training-assignment-moved.window="window.location.reload()"
    class="inline-block"
>
    <button
        x-ref="trigger"
        type="button"
        @click="open ? (open = false) : openMenu()"
        :aria-expanded="open"
        aria-haspopup="listbox"
        class="badge {{ $assignment->status->badgeClass() }} whitespace-nowrap cursor-pointer"
        title="Click to change status"
    >
        {{ $assignment->status->label() }}
    </button>

    {{-- Closed on scroll/resize rather than re-measured: the coordinates are a
         snapshot, so a moving page would leave the menu stranded. --}}
    <ul
        x-ref="menu"
        x-show="open"
        x-cloak
        @click.outside="open = false"
        @scroll.window="open = false"
        @resize.window="open = false"
        :style="`top: ${top}px; left: ${left}px;`"
        role="listbox"
        class="fixed menu bg-base-100 text-base-content rounded-box z-50 p-2 shadow-sm border border-base-300 min-w-max max-h-[80vh] overflow-y-auto flex-nowrap"
    >
        @foreach(\App\Enums\TrainingStatus::cases() as $case)
            <li wire:key="status-{{ $assignment->id }}-{{ $case->value }}">
                <button
                    type="button"
                    @click="open = false"
                    wire:click="setStatus({{ $case->value }})"
                    role="option"
                    aria-selected="{{ $case === $assignment->status ? 'true' : 'false' }}"
                    class="flex flex-row items-center gap-2 {{ $case === $assignment->status ? 'font-semibold' : '' }}"
                >
                    <span class="badge badge-xs {{ $case->badgeClass() }}"></span>
                    {{ $case->label() }}
                </button>
            </li>
        @endforeach
    </ul>

    @error('status') <span class="text-error text-xs block">{{ $message }}</span> @enderror
</div>
