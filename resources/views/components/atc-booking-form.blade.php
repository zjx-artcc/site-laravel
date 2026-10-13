@haspermission('create atc booking')
    <dialog id="booking_modal" class="modal">
        <div class="modal-box">
        <h3 class="text-xl font-bold mb-4">Book a Session</h3>

        <form method="POST" action="{{ route('bookings.store') }}" class="flex flex-col gap-y-3"
                x-data="{
                    start: '{{ old('start') }}',
                    end: '{{ old('end') }}',
                    get exceeds() {
                        return this.start && this.end
                            && (new Date(this.end) - new Date(this.start)) > 5 * 60 * 60 * 1000;
                    }
                }">
            @csrf

            <label class="flex flex-col">
                <span class="label-text font-semibold mb-1">Controller</span>
                <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60"
                        value="{{ auth()->user()->name }} - {{ auth()->user()->id }}" disabled>
            </label>

            <label class="flex flex-col">
                <span class="label-text font-semibold mb-1">Position</span>
                <input type="text" name="position" class="input input-bordered w-full"
                        placeholder="e.g. JAX_CTR" value="{{ old('position') }}" required>
                @error('position') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="flex flex-col">
                    <span class="label-text font-semibold mb-1">Start (zulu)</span>
                    <input type="datetime-local" name="start" class="input input-bordered w-full"
                            x-model="start" required>
                    @error('start') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col">
                    <span class="label-text font-semibold mb-1">End (zulu)</span>
                    <input type="datetime-local" name="end" class="input input-bordered w-full"
                            x-model="end" required>
                    @error('end') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>
            </div>

            <p class="text-sm"
                :class="exceeds ? 'text-error font-semibold' : 'opacity-70'"
                x-text="exceeds ? 'Booking exceeds five hours' : 'Bookings must not exceed five hours'"></p>

            <label class="flex flex-col">
                <span class="label-text font-semibold mb-1">Description (optional)</span>
                <textarea name="description" rows="3" class="textarea textarea-bordered w-full resize-none">{{ old('description') }}</textarea>
                @error('description') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </label>

            <div class="modal-action">
                <button type="submit" class="btn btn-primary" :disabled="exceeds">Book</button>
                <button type="button" class="btn btn-ghost" onclick="booking_modal.close()">Cancel</button>
            </div>
        </form>
        </div>

        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

@if ($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => booking_modal.showModal());
    </script>
@endif
@endhaspermission
