<div>
    @unless (sizeof($events) == 0)
        <div class='overflow-x-auto'>
        <table class='table table-zebra table-md w-max border-2 border-base-300'>
            <thead>
                <tr class='text-xl font-bold'>
                    <th colspan='4'>Events</th>
                    <th colspan='8'>
                    </th>
                </tr>
                <tr colspan='4'>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Start (GMT)</th>
                    <th>End (GMT)</th>
                    <th>Visibility</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($events as $event)
                    <tr>
                        <td class='border-r-1 border-base-300'>
                            <a href='{{ route('events.show', ['event' => $event->id]) }}'
                                class='text-base-content no-underline'>{{ $event->title }}</a>
                        </td>
                        <td class='border-r-1 border-base-300'>{{ $event->type }}</td>
                        <td class='border-r-1 border-base-300'>{{ $event->start }}</td>
                        <td class='border-r-1 border-base-300'>{{ $event->end }}</td>
                        <td class='border-r-1 border-base-300'>
                            <form action="{{ route('admin.event.visibility', ['event' => $event->id]) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                    class="btn btn-sm {{ $event->hidden ? 'btn-neutral' : 'btn-success' }}"
                                    @disabled($event->archived)>
                                    {{ $event->hidden ? 'Hidden' : 'Visible' }}
                                </button>
                            </form>
                        </td>
                        <td class='border-r-1 border-base-300'>
                            <a href="{{ route('admin.events.manage', ['event' => $event->id]) }}" class="btn btn-primary">
                                Manage
                            </a>
                            <form action="{{ route('admin.events.destroy', ['event' => $event->id]) }}" method="POST"
                                class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-error"
                                    onclick="return confirm('Are you sure you want to delete this event?')">
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
        <h1>There are no created events.</h1>
    @endunless
</div>
