<?php

namespace App\Enums;

enum ClientType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return __('clients.types.'.$this->value);
    }
}
