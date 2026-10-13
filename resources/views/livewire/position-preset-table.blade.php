<div>
    @unless (sizeof($positions) == 0)
        <div class='overflow-x-auto'>
        <table x-data='$positions' class='table table-zebra table-md w-max border-2 border-base-300'>
            <thead>
                <tr class='text-xl font-bold'>
                    <th colspan='4'>Position Presets</th>
                    <th colspan='8'>
                    </th>
                </tr>
                <tr colspan='4'>
                    <th>Name</th>
                    <th>Positions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($positions as $position)
                    <tr>
                        <td class='border-r-1 border-base-300'>{{ $position->name }}</td>
                        <td class='border-r-1 border-base-300'>{{ implode(', ', $position->positions ?? []) }}</td>
                        <td>
                            <a href="{{ route('admin.events.position-presets.edit', ['position_preset' => $position->id]) }}"
                                class="btn btn-accent">
                                Edit
                            </a>

                            <form action="{{ route('admin.events.position-presets.destroy', ['position_preset' => $position->id]) }}" method="POST"
                                class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-error"
                                    onclick="return confirm('Are you sure you want to delete this preset?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @else
        <h1>There are no existing presets.</h1>
    @endunless
</div>
