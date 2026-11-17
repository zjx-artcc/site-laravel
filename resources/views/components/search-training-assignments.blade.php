<form class="flex flex-col w-max">
    <div class='flex flex-col'>
        <label
            for="search"
            class="label">Search</label>

        <input
            type="text"
            name="search"
            class="input input-xs max-w-50"
            value="{{old('search', request()->input('search'))}}">
    </div>

    <div class='flex flex-col mt-2'>
        <label for="trainingType" class='label'>Training Type</label>
        <select name="trainingType" id="" class='select  max-w-50 select-xs'>
            <option value="">None</option>
            @foreach (App\Enums\TrainingType::cases() as $trainingType)
                <option 
                value='{{ $trainingType->value }}'
                @selected(request()->input('trainingType') == $trainingType->value)
                >{{ $trainingType->name }}</option>            
            @endforeach
        </select>
    </div>

    <div class='flex flex-row items-center gap-x-2 mt-2'>
        {{-- Submits on change so it applies immediately, and sits inside this
             form so pressing Search carries it too. --}}
        <input
            id="showClosed"
            name="showClosed"
            type="checkbox"
            class="checkbox checkbox-sm"
            onchange="this.form.submit()"
            @checked(request()->boolean('showClosed'))
        />
        <label for="showClosed" class="label cursor-pointer">Show completed / forfeited</label>
    </div>

    <button type="submit" class="btn btn-primary btn-sm mt-2 w-max">Search</button>
</form>
