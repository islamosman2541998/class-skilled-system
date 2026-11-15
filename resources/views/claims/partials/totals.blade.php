{{-- Claim money in calculation order (spec §5.9). --}}
@php($rate = rtrim(rtrim((string) $claim->vat_rate, '0'), '.'))
<x-panel :title="__('site.claims.totals')" class="panel-flush">
    @if ($hint ?? false)
        <p class="form-text px-3 pt-3 mb-0">{{ __('site.claims.totals_hint') }}</p>
    @endif
    <table class="table q-view mb-0">
        <tbody>
            <tr><th scope="row">{{ __('site.claims.work_previous') }}</th><td class="num"><bdi dir="ltr">{{ money($claim->work_previous) }}</bdi></td></tr>
            <tr><th scope="row">{{ __('site.claims.work_cumulative') }}</th><td class="num"><bdi dir="ltr">{{ money($claim->work_cumulative) }}</bdi></td></tr>
            <tr><th scope="row">{{ __('site.claims.work_current') }}</th><td class="num"><bdi dir="ltr">{{ money($claim->work_current) }}</bdi></td></tr>
            @if (bccomp((string) $claim->discount_amount, '0', 2) !== 0)
                <tr><th scope="row">{{ __('site.claims.discount') }}</th><td class="num"><bdi dir="ltr">-{{ money($claim->discount_amount) }}</bdi></td></tr>
                <tr><th scope="row">{{ __('site.claims.gross') }}</th><td class="num"><bdi dir="ltr">{{ money($claim->gross_amount) }}</bdi></td></tr>
            @endif
            <tr><th scope="row">{{ __('site.claims.retention') }} <bdi dir="ltr">({{ rtrim(rtrim((string) $claim->retention_percent, '0'), '.') ?: '0' }}%)</bdi></th><td class="num"><bdi dir="ltr">-{{ money($claim->retention_amount) }}</bdi></td></tr>
            @if (bccomp((string) $claim->advance_recovery, '0', 2) !== 0)
                <tr><th scope="row">{{ __('site.claims.advance_recovery') }}</th><td class="num"><bdi dir="ltr">-{{ money($claim->advance_recovery) }}</bdi></td></tr>
            @endif
            <tr><th scope="row">{{ __('site.claims.net') }}</th><td class="num"><bdi dir="ltr">{{ money($claim->net_amount) }}</bdi></td></tr>
            <tr><th scope="row">{{ __('site.claims.vat') }} <bdi dir="ltr">({{ $rate }}%)</bdi></th><td class="num"><bdi dir="ltr">{{ money($claim->vat_amount) }}</bdi></td></tr>
        </tbody>
        <tfoot>
            <tr class="q-grand"><th scope="row">{{ __('site.claims.total') }}</th><td class="num"><bdi dir="ltr">{{ money($claim->total) }}</bdi></td></tr>
        </tfoot>
    </table>
</x-panel>
