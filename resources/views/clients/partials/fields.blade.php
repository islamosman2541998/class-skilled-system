{{-- Client fields shared by the full form and the quick-add modal. $prefix keeps ids unique when both are on one page. --}}
@php
    $prefix = $prefix ?? '';
    $value = fn (string $field) => old($field, $client->{$field} instanceof \BackedEnum ? $client->{$field}->value : $client->{$field});
@endphp
<div class="form-grid">
    <x-field name="type" :label="__('clients.type')" :for="$prefix.'type'" required>
        <select id="{{ $prefix }}type" name="type" class="form-select" data-client-type>
            @foreach (\App\Enums\ClientType::cases() as $type)
                <option value="{{ $type->value }}" @selected($value('type') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field name="name_ar" :label="__('clients.name_ar')" :for="$prefix.'name_ar'" required>
        <input id="{{ $prefix }}name_ar" name="name_ar" value="{{ $value('name_ar') }}" @class(['form-control', 'is-invalid' => $errors->has('name_ar')]) required maxlength="190" dir="rtl">
    </x-field>
    <x-field name="name_en" :label="__('clients.name_en')" :for="$prefix.'name_en'" :hint="__('settings.name_en_hint')">
        <input id="{{ $prefix }}name_en" name="name_en" value="{{ $value('name_en') }}" class="form-control" maxlength="190" dir="ltr">
    </x-field>
    <x-field name="phone" :label="__('app.phone')" :for="$prefix.'phone'" required>
        <input id="{{ $prefix }}phone" name="phone" value="{{ $value('phone') }}" @class(['form-control', 'is-invalid' => $errors->has('phone')]) required dir="ltr" inputmode="tel"
            data-duplicate-check="{{ route('clients.duplicates') }}" data-ignore="{{ $client->id }}">
        <div class="form-text text-warning d-none" data-duplicate-box>{{ __('clients.duplicate_phone') }} <span data-duplicate-list></span></div>
    </x-field>
</div>
