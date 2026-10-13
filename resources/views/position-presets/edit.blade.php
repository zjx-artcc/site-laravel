@extends('layouts.admin')

@section('title', 'Edit Preset')

@section('body')
    <x-card-component>
        <form method="POST" action="{{ route('admin.events.position-presets.update', ['position_preset' => $position->id]) }}"
            class="flex flex-col">
            @csrf
            @method('PUT')

            <label for="positions" class="label">Preset Name</label>
            <input name="name" value="{{ old('name', $position->name) }}" type="text" required placeholder="Eg. Generic Positions by Rating" class="input" />
            <br />

            @php
                $presetPositionItems = old('positions') !== null
                    ? array_values(array_filter(array_map('trim', explode(',', old('positions'))), 'strlen'))
                    : ($position->positions ?? []);
            @endphp

            <x-list-editor
                name="positions"
                label="Positions"
                placeholder="Eg. MCO_GND"
                :items="$presetPositionItems"
            />

            <button class="btn" type="submit">Update Preset</button>
        </form>
    </x-card-component>


@endsection
