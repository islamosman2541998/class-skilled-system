@php
    $followUps = $lead->followUps()->with('user')->get();
@endphp
<x-panel :title="__('leads.follow_ups')">
    @can('leads.update')
        <form method="POST" action="{{ route('leads.follow-ups.store', $lead) }}" class="follow-up-form">
            @csrf
            <div class="btn-group" role="group" aria-label="{{ __('leads.follow_ups') }}">
                @foreach (\App\Enums\FollowUpType::cases() as $type)
                    <input type="radio" class="btn-check" name="type" id="fu-{{ $type->value }}" value="{{ $type->value }}" @checked($loop->first)>
                    <label class="btn btn-outline btn-sm" for="fu-{{ $type->value }}"><x-icon :name="$type->icon()" :size="16" /> {{ $type->label() }}</label>
                @endforeach
            </div>
            <x-field name="body" :label="__('leads.follow_up_body')" for="fu-body" class="mb-2">
                <textarea id="fu-body" name="body" class="form-control" rows="2" required maxlength="5000"></textarea>
            </x-field>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('leads.add_follow_up') }}</button>
        </form>
    @endcan

    @if ($followUps->isEmpty())
        <x-empty-state :message="__('leads.no_follow_ups')" icon="message" />
    @else
        <ol class="timeline">
            @foreach ($followUps as $entry)
                <li>
                    <span class="timeline-icon"><x-icon :name="$entry->type->icon()" :size="16" /></span>
                    <div class="timeline-body">
                        <div class="timeline-meta">
                            <span class="fw-semibold">{{ $entry->type->label() }}</span>
                            <span>{{ $entry->user?->name }}</span>
                            <bdi dir="ltr">{{ fmt_datetime($entry->happened_at) }}</bdi>
                        </div>
                        <p class="mb-0 text-pre">{{ $entry->body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-panel>
