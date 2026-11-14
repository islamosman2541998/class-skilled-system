<?php

namespace App\Enums;

enum DiscountType: string
{
    case Amount = 'amount';
    case Percent = 'percent';

    public function label(): string
    {
        return __('quotations.discount_types.'.$this->value);
    }
}
