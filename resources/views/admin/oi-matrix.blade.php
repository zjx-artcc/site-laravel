@extends('layouts.admin')

@section('title', 'OI Matrix')

@section('body')
    <x-card-component title="Operating Initials Matrix">
        @livewire('oi-matrix')
    </x-card-component>
@endsection
