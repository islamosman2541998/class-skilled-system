<?php

namespace App\Enums;

/** How a project is invoiced (spec §8.14): by its payment schedule or by progress claims (Stage 4). */
enum BillingMethod: string
{
    case Installments = 'installments';
    case Claims = 'claims';

    public function label(): string
    {
        return __('projects.billing_methods.'.$this->value);
    }
}
