@extends('layouts.app')

@section('title', __('site.claims.claims'))

@section('content')
    <x-page-header :title="__('site.claims.claims')" />

    <livewire:tables.claims-table />
@endsection
