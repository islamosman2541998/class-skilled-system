@extends('layouts.app')

@section('title', __('settings.groups.activity'))

@section('content')
    <x-page-header :title="__('settings.groups.activity')" />

    <livewire:tables.activity-table />
@endsection
