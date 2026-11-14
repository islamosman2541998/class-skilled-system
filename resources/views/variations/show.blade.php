@extends('layouts.app')

@section('title', $variation->number)

@section('content')
    <x-page-header :title="$variation->number" :breadcrumbs="[__('projects.projects') => route('projects.index'), $project->number => route('projects.show', [$project, 'tab' => 'variations'])]">
        <x-slot:actions>
            @unless ($variation->isDecided())
                @can('projects.approve')
                    <form method="POST" action="{{ route('projects.variations.approve', [$project, $variation]) }}" data-confirm="{{ __('projects.variations.approve_confirm') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary"><x-icon name="check" :size="16" /> {{ __('projects.variations.approve') }}</button>
                    </form>
                    <button type="button" class="btn btn-outline text-danger" data-bs-toggle="modal" data-bs-target="#variation-reject">{{ __('projects.variations.reject') }}</button>
                @endcan
                @can('projects.update')
                    <a href="{{ route('projects.variations.edit', [$project, $variation]) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('projects.variations.edit') }}</a>
                @endcan
            @endunless
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-panel :title="__('projects.variations.items')" class="panel-flush">
                <div class="p-3 pb-0">
                    <x-status-badge :color="$variation->status->color()">{{ $variation->status->label() }}</x-status-badge>
                    <p class="text-pre mt-2">{{ $variation->description }}</p>
                </div>
                <div class="table-responsive">
                    <table class="table q-view mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('quotations.description') }}</th>
                                <th scope="col">{{ __('quotations.unit') }}</th>
                                <th scope="col" class="num">{{ __('quotations.quantity') }}</th>
                                <th scope="col" class="num">{{ __('quotations.unit_price') }}</th>
                                <th scope="col" class="num">{{ __('quotations.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($variation->items as $item)
                                <tr>
                                    <td>{{ app()->getLocale() === 'en' && $item->description_en ? $item->description_en : $item->description_ar }}</td>
                                    <td>{{ $item->unit?->name }}</td>
                                    <td class="num"><bdi dir="ltr">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ money($item->unit_price, false) }}</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ money($item->line_total, false) }}</bdi></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="4" class="text-end">{{ __('projects.variations.subtotal') }}</th><td class="num"><bdi dir="ltr">{{ money($variation->subtotal) }}</bdi></td></tr>
                            <tr><th colspan="4" class="text-end">{{ __('projects.variations.vat') }} <bdi dir="ltr">({{ rtrim(rtrim($project->vat_rate, '0'), '.') }}%)</bdi></th><td class="num"><bdi dir="ltr">{{ money($variation->vat_amount) }}</bdi></td></tr>
                            <tr class="q-grand"><th colspan="4" class="text-end">{{ __('projects.variations.total') }}</th><td class="num"><bdi dir="ltr">{{ money($variation->total) }}</bdi></td></tr>
                        </tfoot>
                    </table>
                </div>
            </x-panel>
            @if ($variation->isDecided())
                <x-panel>
                    <dl class="detail-list">
                        <div><dt>{{ __('projects.variations.decided_by') }}</dt><dd>{{ $variation->decider?->name }} · <bdi dir="ltr">{{ $variation->decided_at ? fmt_datetime($variation->decided_at) : '' }}</bdi></dd></div>
                        @if ($variation->rejection_note)
                            <div><dt>{{ __('projects.variations.rejection_note') }}</dt><dd>{{ $variation->rejection_note }}</dd></div>
                        @endif
                    </dl>
                </x-panel>
            @endif
        </div>
        <div class="col-xl-4">
            <x-attachments :model="$variation" :action="route('projects.variations.attachments.store', [$project, $variation])" />
            <x-activity-tab :subject="$variation" />
        </div>
    </div>

    @unless ($variation->isDecided())
        @can('projects.approve')
            @push('modals')
                <div class="modal fade" id="variation-reject" tabindex="-1" aria-labelledby="variation-reject-title" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('projects.variations.reject', [$project, $variation]) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title" id="variation-reject-title">{{ __('projects.variations.reject') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                            </div>
                            <div class="modal-body">
                                <x-field name="rejection_note" :label="__('projects.variations.rejection_note')" for="v-reject-note">
                                    <textarea id="v-reject-note" name="rejection_note" class="form-control" rows="2" maxlength="2000"></textarea>
                                </x-field>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                                <button type="submit" class="btn btn-danger">{{ __('projects.variations.reject') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endpush
        @endcan
    @endunless
@endsection
