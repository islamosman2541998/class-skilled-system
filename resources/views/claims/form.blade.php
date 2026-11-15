{{-- Draft claim (spec §8.14): period, then per line the current quantity or the cumulative percent. --}}
@extends('layouts.app')

@php
    $title = ($claim->number ?? __('site.claims.draft_title')).' · '.$claim->project->number;
    $qty = fn ($value) => rtrim(rtrim((string) $value, '0'), '.') ?: '0';
@endphp

@section('title', __('site.claims.edit'))

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('site.claims.claims') => route('claims.index'), $claim->project->number => route('projects.show', [$claim->project, 'tab' => 'claims'])]" />

    @if ($claim->return_note)
        <div class="alert alert-warning"><strong>{{ __('site.claims.returned_note') }}:</strong> {{ $claim->return_note }}</div>
    @endif

    <form method="POST" action="{{ route('claims.update', $claim) }}" data-dirty-check novalidate data-claim-form>
        @csrf
        @method('PUT')

        <x-panel>
            <div class="form-grid">
                <x-field name="period_from" :label="__('site.claims.period_from')" required>
                    <input id="period_from" name="period_from" type="date" value="{{ old('period_from', $claim->period_from->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('period_from')]) dir="ltr" required>
                </x-field>
                <x-field name="period_to" :label="__('site.claims.period_to')" required>
                    <input id="period_to" name="period_to" type="date" value="{{ old('period_to', $claim->period_to->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('period_to')]) dir="ltr" required>
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('site.claims.lines')" class="panel-flush">
            <p class="form-text px-3 pt-3 mb-2">{{ __('site.claims.lines_hint') }}</p>
            @error('lines')<div class="alert alert-danger mx-3">{{ $message }}</div>@enderror
            <div class="table-responsive">
                <table class="table q-view claim-lines mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('site.claims.description') }}</th>
                            <th scope="col" class="num">{{ __('site.claims.contract_quantity') }}</th>
                            <th scope="col" class="num">{{ __('site.claims.previous') }}</th>
                            <th scope="col">{{ __('site.claims.entry_mode') }}</th>
                            <th scope="col" class="num">{{ __('site.claims.current') }}</th>
                            <th scope="col" class="num">{{ __('site.claims.current_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($claim->lines as $line)
                            @php
                                $percentMode = filled(old("lines.{$line->id}.cumulative_percent"));
                                $error = $errors->first("lines.{$line->id}") ?: $errors->first("lines.{$line->id}.current_quantity") ?: $errors->first("lines.{$line->id}.cumulative_percent");
                            @endphp
                            <tr data-claim-line data-contract="{{ $line->contract_quantity }}" data-previous="{{ $line->previous_quantity }}">
                                <td>
                                    {{ $line->description() }}
                                    @if ($line->unit)<span class="text-muted small"> · {{ $line->unit->name }}</span>@endif
                                    @if ($error)<div class="invalid-feedback d-block">{{ $error }}</div>@endif
                                    <div class="invalid-feedback" data-line-warning></div>
                                </td>
                                <td class="num"><bdi dir="ltr">{{ $qty($line->contract_quantity) }}</bdi></td>
                                <td class="num"><bdi dir="ltr">{{ $qty($line->previous_quantity) }}</bdi></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('site.claims.entry_mode') }}">
                                        <input type="radio" class="btn-check" name="mode[{{ $line->id }}]" id="mode-q-{{ $line->id }}" value="quantity" data-mode @checked(! $percentMode)>
                                        <label class="btn btn-outline" for="mode-q-{{ $line->id }}">{{ __('site.claims.entry_quantity') }}</label>
                                        <input type="radio" class="btn-check" name="mode[{{ $line->id }}]" id="mode-p-{{ $line->id }}" value="percent" data-mode @checked($percentMode)>
                                        <label class="btn btn-outline" for="mode-p-{{ $line->id }}">{{ __('site.claims.entry_percent') }}</label>
                                    </div>
                                </td>
                                <td class="num claim-entry">
                                    <input name="lines[{{ $line->id }}][current_quantity]" value="{{ old("lines.{$line->id}.current_quantity", $qty($line->current_quantity)) }}" @class(['form-control form-control-sm text-end', 'is-invalid' => $error]) dir="ltr" inputmode="decimal" aria-label="{{ __('site.claims.current') }}" data-entry="quantity" @if ($percentMode) hidden disabled @endif>
                                    <div class="input-group input-group-sm" data-entry-wrap="percent" @unless ($percentMode) hidden @endunless>
                                        <input name="lines[{{ $line->id }}][cumulative_percent]" value="{{ old("lines.{$line->id}.cumulative_percent", $qty($line->cumulativePercent())) }}" @class(['form-control text-end', 'is-invalid' => $error]) dir="ltr" inputmode="decimal" aria-label="{{ __('site.claims.cumulative_percent') }}" data-entry="percent" @unless ($percentMode) disabled @endunless>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </td>
                                <td class="num"><bdi dir="ltr">{{ money($line->current_amount, false) }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>

        <div class="row g-3">
            <div class="col-lg-6">
                <x-panel>
                    <x-field name="notes" :label="__('site.claims.notes')">
                        <textarea id="notes" name="notes" rows="3" class="form-control" maxlength="2000">{{ old('notes', $claim->notes) }}</textarea>
                    </x-field>
                </x-panel>
            </div>
            <div class="col-lg-6">
                @include('claims.partials.totals', ['claim' => $claim, 'hint' => true])
            </div>
        </div>

        <x-sticky-actions>
            <a href="{{ route('claims.show', $claim) }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>

    @push('scripts')
        @vite('resources/js/claim-form.js')
    @endpush
@endsection
