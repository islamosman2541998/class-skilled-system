<?php

namespace App\Enums;

/**
 * The lists a user manages from Settings (spec §7). All of them live in the `lookups` table.
 */
enum LookupType: string
{
    case Service = 'service';
    case LeadStatus = 'lead_status';
    case LeadSource = 'lead_source';
    case RejectionReason = 'rejection_reason';
    case Unit = 'unit';
    case ExpenseCategory = 'expense_category';
    case AttachmentCategory = 'attachment_category';
    case Emirate = 'emirate';
    case Area = 'area';
    case PaymentMethod = 'payment_method';
    case WorkerTrade = 'worker_trade';
    case EquipmentType = 'equipment_type';

    public function label(): string
    {
        return __('settings.lists.'.$this->value);
    }

    /** The list a row of this list must belong to (an area belongs to an emirate). */
    public function parentType(): ?self
    {
        return $this === self::Area ? self::Emirate : null;
    }

    /** Whether rows carry a status colour. */
    public function hasColor(): bool
    {
        return $this === self::LeadStatus;
    }
}
