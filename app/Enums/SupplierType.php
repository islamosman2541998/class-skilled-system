<?php

namespace App\Enums;

/** A supplier of materials or a subcontractor (spec §8.15). */
enum SupplierType: string
{
    case Supplier = 'supplier';
    case Subcontractor = 'subcontractor';

    public function label(): string
    {
        return __('resources.suppliers.types.'.$this->value);
    }

    public function color(): string
    {
        return $this === self::Supplier ? 'info' : 'warning';
    }
}
