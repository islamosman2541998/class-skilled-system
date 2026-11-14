@extends('layouts.app')

@php
    $editing = $client->exists;
    $title = $editing ? __('clients.edit_client') : __('clients.new_client');
    $contacts = old('contacts', $client->relationLoaded('contacts') ? $client->contacts->map->only(['name', 'position', 'phone', 'email'])->all() : []);
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('clients.clients') => route('clients.index')] + ($editing ? [$client->name => route('clients.show', $client)] : [])" />

    <form method="POST" action="{{ $editing ? route('clients.update', $client) : route('clients.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('clients.basic')">
            @include('clients.partials.fields', ['client' => $client])
        </x-panel>

        <x-panel :title="__('clients.contact_data')">
            <div class="form-grid">
                <x-field name="phone2" :label="__('clients.phone2')">
                    <input id="phone2" name="phone2" value="{{ old('phone2', $client->phone2) }}" @class(['form-control', 'is-invalid' => $errors->has('phone2')]) dir="ltr" inputmode="tel">
                </x-field>
                <x-field name="whatsapp" :label="__('clients.whatsapp')">
                    <input id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $client->whatsapp) }}" @class(['form-control', 'is-invalid' => $errors->has('whatsapp')]) dir="ltr" inputmode="tel">
                </x-field>
                <x-field name="email" :label="__('app.email')">
                    <input id="email" type="email" name="email" value="{{ old('email', $client->email) }}" @class(['form-control', 'is-invalid' => $errors->has('email')]) dir="ltr">
                </x-field>
                <x-field name="trn" :label="__('clients.trn')">
                    <input id="trn" name="trn" value="{{ old('trn', $client->trn) }}" @class(['form-control', 'is-invalid' => $errors->has('trn')]) dir="ltr" inputmode="numeric" maxlength="15">
                </x-field>
                <x-field name="source_id" :label="__('clients.source')">
                    <select id="source_id" name="source_id" class="form-select">
                        <option value=""></option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected((int) old('source_id', $client->source_id) === $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('clients.location')">
            <div class="form-grid">
                <x-field name="emirate_id" :label="__('clients.emirate')">
                    <select id="emirate_id" name="emirate_id" class="form-select" data-parent-select="area_id">
                        <option value=""></option>
                        @foreach ($emirates as $emirate)
                            <option value="{{ $emirate->id }}" @selected((int) old('emirate_id', $client->emirate_id) === $emirate->id)>{{ $emirate->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="area_id" :label="__('clients.area')">
                    <select id="area_id" name="area_id" class="form-select">
                        <option value=""></option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" data-parent="{{ $area->parent_id }}" @selected((int) old('area_id', $client->area_id) === $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
            <x-field name="address" :label="__('clients.address')">
                <textarea id="address" name="address" class="form-control" rows="2">{{ old('address', $client->address) }}</textarea>
            </x-field>
            <x-field name="notes" :label="__('clients.notes')">
                <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $client->notes) }}</textarea>
            </x-field>
        </x-panel>

        <x-panel :title="__('clients.contacts')" data-contacts>
            <input type="hidden" name="contacts_present" value="1">
            <div class="repeater" data-repeater>
                @foreach ($contacts as $i => $contact)
                    @include('clients.partials.contact-row', ['i' => $i, 'contact' => $contact])
                @endforeach
            </div>
            <template data-repeater-template>
                @include('clients.partials.contact-row', ['i' => '__INDEX__', 'contact' => []])
            </template>
            <button type="button" class="btn btn-outline btn-sm" data-repeater-add><x-icon name="plus" :size="16" /> {{ __('clients.add_contact') }}</button>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('clients.show', $client) : route('clients.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>

    @pushOnce('scripts')
        @vite('resources/js/clients.js')
    @endPushOnce
@endsection
