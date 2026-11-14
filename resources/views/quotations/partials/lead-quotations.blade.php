@can('quotations.view')
    @php
        $quotations = $lead->quotations()->visibleTo(auth()->user())->latest('id')->get();
    @endphp
    @if ($quotations->isNotEmpty())
        <x-panel :title="__('quotations.quotations')">
            <ul class="contact-list">
                @foreach ($quotations as $row)
                    <li>
                        <div>
                            <a href="{{ route('quotations.show', $row) }}" class="fw-semibold"><bdi dir="ltr">{{ $row->number }}</bdi></a>
                            <div class="text-muted small">{{ $row->title }} · <bdi dir="ltr">{{ money($row->total) }}</bdi></div>
                        </div>
                        @include('quotations.partials.status', ['row' => $row])
                    </li>
                @endforeach
            </ul>
        </x-panel>
    @endif
@endcan
