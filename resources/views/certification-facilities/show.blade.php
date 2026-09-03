@extends('layouts.admin')

@section('title', 'Certification Facility - ' . $facility->name)

@section('body')
    <div class='grid lg:grid-cols-2 md:grid-cols-1 grid-cols-1 gap-5'>
        <x-card-component title='Facility Information'>

            <div class='overflow-x-auto'>
            <table class='table table-compact w-full mt-2'>
                <tr>
                    <th>Name:</th>
                    <td>{{ $facility->name }}</td>
                </tr>
                <tr>
                    <th>Identifier:</th>
                    <td>{{ $facility->identifier }}</td>
                </tr>
            </table>
            </div>
            
            @livewire('certification-levels-table', ['facilityId' => $facility->id], key($facility->id))
        </x-card-component>
    </div>

@endsection