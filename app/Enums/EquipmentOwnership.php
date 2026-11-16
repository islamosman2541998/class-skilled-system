<?php

namespace App\Enums;

/** Equipment owned by the company or rented (spec §8.17). */
enum EquipmentOwnership: string
{
    case Owned = 'owned';
    case Rented = 'rented';

    public function label(): string
    {
        return __('resources.equipment.ownerships.'.$this->value);
    }
}
