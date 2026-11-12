@extends('layouts.app')

@section('title', __('settings.groups.numbering'))

@section('content')
    <x-page-header :title="__('settings.groups.numbering')" :breadcrumbs="[__('settings.settings') => route('settings.index')]" />

    <form method="POST" action="{{ route('settings.numbering.update') }}" data-dirty-check novalidate>
        @csrf
        @method('PUT')

        <x-panel class="panel-flush">
            <div class="table-responsive">
                <table class="table numbering-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('settings.numbering.document') }}</th>
                            <th scope="col">{{ __('settings.numbering.prefix') }}</th>
                            <th scope="col">{{ __('settings.numbering.padding') }}</th>
                            <th scope="col">{{ __('settings.numbering.next_number') }}</th>
                            <th scope="col">{{ __('settings.numbering.include_year') }}</th>
                            <th scope="col">{{ __('settings.numbering.reset_yearly') }}</th>
                            <th scope="col">{{ __('settings.numbering.preview') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $type)
                            @php
                                $sequence = $sequences[$type->value];
                                $name = "sequences[{$type->value}]";
                                $key = "sequences.{$type->value}";
                            @endphp
                            <tr>
                                <th scope="row">{{ $type->label() }}</th>
                                <td>
                                    <input name="{{ $name }}[prefix]" value="{{ old($key.'.prefix', $sequence->prefix) }}" @class(['form-control form-control-sm', 'is-invalid' => $errors->has($key.'.prefix')]) maxlength="10" dir="ltr" required aria-label="{{ __('settings.numbering.prefix') }} {{ $type->label() }}">
                                    @error($key.'.prefix')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" name="{{ $name }}[padding]" value="{{ old($key.'.padding', $sequence->padding) }}" @class(['form-control form-control-sm', 'is-invalid' => $errors->has($key.'.padding')]) min="3" max="8" dir="ltr" required aria-label="{{ __('settings.numbering.padding') }} {{ $type->label() }}">
                                    @error($key.'.padding')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" name="{{ $name }}[next_number]" value="{{ old($key.'.next_number', $sequence->next_number) }}" @class(['form-control form-control-sm', 'is-invalid' => $errors->has($key.'.next_number')]) min="{{ $sequence->next_number }}" dir="ltr" required aria-label="{{ __('settings.numbering.next_number') }} {{ $type->label() }}">
                                    @error($key.'.next_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="hidden" name="{{ $name }}[include_year]" value="0">
                                    <input type="checkbox" class="form-check-input" name="{{ $name }}[include_year]" value="1" @checked(old($key.'.include_year', $sequence->include_year)) aria-label="{{ __('settings.numbering.include_year') }} {{ $type->label() }}">
                                </td>
                                <td>
                                    <input type="hidden" name="{{ $name }}[reset_yearly]" value="0">
                                    <input type="checkbox" class="form-check-input" name="{{ $name }}[reset_yearly]" value="1" @checked(old($key.'.reset_yearly', $sequence->reset_yearly)) aria-label="{{ __('settings.numbering.reset_yearly') }} {{ $type->label() }}">
                                </td>
                                <td><bdi dir="ltr" class="text-muted">{{ $previews[$type->value] }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>

        <p class="form-text mt-2">{{ __('settings.numbering.hint') }}</p>

        <div class="sticky-actions">
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ route('settings.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
