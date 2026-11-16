{{-- Assign equipment to a project for a period (spec §8.17). Expects $action and $projects. --}}
<form method="POST" action="{{ $action }}" class="p-3" novalidate>
    @csrf
    <div class="form-grid">
        <x-field name="project_id" :label="__('resources.equipment.project')" required>
            <select id="assign-project" name="project_id" class="form-select" data-select required>
                <option value=""></option>
                @foreach ($projects as $option)
                    <option value="{{ $option->id }}" @selected((int) old('project_id') === $option->id)>{{ $option->number }} · {{ $option->title }}</option>
                @endforeach
            </select>
        </x-field>
        <x-field name="date_from" :label="__('resources.equipment.date_from')" required>
            <input id="assign-from" name="date_from" value="{{ old('date_from') }}" @class(['form-control', 'is-invalid' => $errors->has('date_from')]) data-date dir="ltr" required>
        </x-field>
        <x-field name="date_to" :label="__('resources.equipment.date_to')" required>
            <input id="assign-to" name="date_to" value="{{ old('date_to') }}" @class(['form-control', 'is-invalid' => $errors->has('date_to')]) data-date dir="ltr" required>
        </x-field>
        <x-field name="daily_cost" :label="__('resources.equipment.daily_cost')" :hint="__('resources.equipment.daily_cost_hint')">
            <input id="assign-cost" name="daily_cost" value="{{ old('daily_cost') }}" @class(['form-control text-end', 'is-invalid' => $errors->has('daily_cost')]) dir="ltr" inputmode="decimal">
        </x-field>
    </div>
    <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary">{{ __('resources.equipment.assign') }}</button>
    </div>
</form>
