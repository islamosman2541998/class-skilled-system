<?php

namespace App\Enums;

/**
 * In-app notification types with their default timing and recipients (spec §5.5).
 * "Direct user" types go to the responsible user and have no default roles.
 */
enum NotificationType: string
{
    case LeadFromWebsite = 'lead_from_website';
    case Assigned = 'assigned';
    case VisitToday = 'visit_today';
    case QuotationNoResponse = 'quotation_no_response';
    case QuotationExpiring = 'quotation_expiring';
    case InstallmentDue = 'installment_due';
    case InstallmentOverdue = 'installment_overdue';
    case InvoiceOverdue = 'invoice_overdue';
    case StageLate = 'stage_late';
    case ProjectEnding = 'project_ending';
    case WarrantyEnding = 'warranty_ending';
    case BackupFailed = 'backup_failed';

    public function label(): string
    {
        return __('notifications.labels.'.$this->value);
    }

    public function usesDays(): bool
    {
        return $this->defaultDays() !== null;
    }

    public function defaultDays(): ?int
    {
        return match ($this) {
            self::QuotationNoResponse, self::QuotationExpiring, self::InstallmentDue => 3,
            self::ProjectEnding => 7,
            self::WarrantyEnding => 30,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function defaultRoles(): array
    {
        return match ($this) {
            self::LeadFromWebsite => ['sales'],
            self::InstallmentDue, self::InstallmentOverdue, self::InvoiceOverdue => ['accountant', 'management'],
            self::StageLate, self::WarrantyEnding => ['management'],
            self::BackupFailed => ['super-admin'],
            default => [],
        };
    }
}
