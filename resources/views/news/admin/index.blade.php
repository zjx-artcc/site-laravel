@extends('layouts.admin')

@section('title', 'News Management')

@section('body')
    <a href="{{ route('admin.news.create') }}" class="btn btn-primary mb-5">+ New Announcement</a>

    @if ($news->isEmpty())
        <h1>There are no announcements.</h1>
    @else
        <div class="overflow-x-auto">
        <table class="table table-zebra table-md w-full border-2 border-base-300">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Published</th>
                    <th>Content</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($news as $n)
                    <tr>
                        <td class="py-3 border-r-1 border-base-300 font-medium">{{ $n->title }}</td>
                        <td class="py-3 border-r-1 border-base-300 whitespace-nowrap">
                            {{ $n->published_at?->format('m/d/Y H:i') }}z
                        </td>
                        <td class="py-3 border-r-1 border-base-300">{{ Str::limit(strip_tags($n->content), 120) }}</td>
                        <td class="py-3 text-right">
                            <a href="{{ route('admin.news.edit', $n) }}" class="btn btn-sm btn-outline">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="mt-5">
            {{ $news->links() }}
        </div>
    @endif
@endsection
