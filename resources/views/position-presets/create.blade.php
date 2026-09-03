@extends('layouts.admin')

@section('title', 'New Event Position Preset')

@section('body')
    <x-card-component>
        <form method="POST" action="{{ route('admin.events.position-presets.store') }}" class="flex flex-col gap-2 w-max max-w-full">
            @csrf
            <label for="positions" class="label">Preset Name</label>
            <input name="name" type="text" required placeholder="Eg. Generic Positions by Rating" class="input" />
            <br/>

            <x-list-editor
                name="positions"
                label="Positions"
                placeholder="Eg. MCO_GND"
                :items="array_values(array_filter(array_map('trim', explode(',', old('positions', ''))), 'strlen'))"
            />

            <button class="btn btn-primary" type="submit">Create Preset</button>
        </form>
    </x-card-component>
@endsection
