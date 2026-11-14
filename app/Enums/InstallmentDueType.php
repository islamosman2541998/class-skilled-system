<?php

namespace App\Enums;

enum InstallmentDueType: string
{
    case Date = 'date';
    case Stage = 'stage';

    public function label(): string
    {
        return __('projects.installments.due_types.'.$this->value);
    }
}
