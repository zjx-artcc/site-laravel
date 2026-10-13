@extends('layouts.admin')

@section('title', 'Statistics Prefixes')

@section('body')
    <x-card-component title="Manage Statistics Prefixes">

        <div class="grid grid-cols-2 sm:grid-cols-5 lg:grid-cols-10">
            @foreach($prefixes as $prefix)
                <div class="p-1 px-2 flex flex-row justify-between items-center border-1 border-gray-300">
                    <h2 class='text-md'>{{ $prefix->name }}</h2>

                    <form action={{ route('statistics-prefixes.destroy', ['statistics_prefix' => $prefix->id]) }} method="post">
                        @csrf
                        @method('delete')

                        <button type="submit" class='btn btn-xs btn-error'>Delete {{ $prefix->name }}</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-10 flex flex-col w-40">
            <form action="" method='POST'>
                @csrf
                <label lass='label' for='name'>Add Prefix</label>
                <input required type="text" name='name' class='input input-sm'>
                <button type="submit" class='btn btn-md btn-accent mt-2'>Submit</button>
            </form>
        </div>
    </x-card-component>
@endsection