<div class="repeater-row" data-repeater-row>
    <span class="btn-icon sort-handle" aria-hidden="true"><x-icon name="grip-vertical" :size="16" /></span>
    <div class="form-grid form-grid-4">
        <input name="stages[{{ $i }}][name_ar]" value="{{ $stage['name_ar'] ?? '' }}" @class(['form-control', 'is-invalid' => $errors->has('stages.'.$i.'.name_ar')]) placeholder="{{ __('projects.templates.name_ar') }}" aria-label="{{ __('projects.templates.name_ar') }}" maxlength="190" required>
        <input name="stages[{{ $i }}][name_en]" value="{{ $stage['name_en'] ?? '' }}" class="form-control" placeholder="{{ __('projects.templates.name_en') }}" aria-label="{{ __('projects.templates.name_en') }}" maxlength="190" dir="ltr">
        <input name="stages[{{ $i }}][weight]" value="{{ $stage['weight'] ?? '' }}" @class(['form-control text-end', 'is-invalid' => $errors->has('stages.'.$i.'.weight')]) placeholder="{{ __('projects.templates.weight') }}" aria-label="{{ __('projects.templates.weight') }}" dir="ltr" inputmode="decimal" required data-weight>
    </div>
    <button type="button" class="btn-icon btn-icon-danger" data-row-remove aria-label="{{ __('settings.templates.remove_line') }}"><x-icon name="x" /></button>
</div>
