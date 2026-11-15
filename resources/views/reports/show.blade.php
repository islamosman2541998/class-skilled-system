@extends('layouts.app')

@section('title', __($report['label']))

@section('content')
    <x-page-header :title="__($report['label'])" :breadcrumbs="[__('reports.reports') => route('reports.index')]" />

    <p class="text-muted">{{ __($report['hint']) }}</p>

    @livewire($report['component'], [], key('report-'.$key))
@endsection
