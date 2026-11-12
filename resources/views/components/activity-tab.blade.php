@props(['subject'])
{{-- History of one record, for the "Activity" tab of its page. --}}
@can('activity.view')
    <livewire:tables.activity-table :subject-type="$subject->getMorphClass()" :subject-id="$subject->getKey()" :key="'activity-'.$subject->getMorphClass().'-'.$subject->getKey()" />
@endcan
