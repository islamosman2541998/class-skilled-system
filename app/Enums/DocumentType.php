<?php

namespace App\Enums;

/**
 * Every numbered document in the system. Each has its own sequence (spec §5.9).
 */
enum DocumentType: string
{
    case Lead = 'lead';
    case SiteVisit = 'site_visit';
    case Quotation = 'quotation';
    case Project = 'project';
    case Variation = 'variation';
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case Expense = 'expense';
    case Claim = 'claim';
    case Purchase = 'purchase';
    case PaymentVoucher = 'payment_voucher';
    case Maintenance = 'maintenance';

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Lead => 'LD',
            self::SiteVisit => 'SV',
            self::Quotation => 'QT',
            self::Project => 'PRJ',
            self::Variation => 'VO',
            self::Invoice => 'INV',
            self::Receipt => 'RV',
            self::Expense => 'EXP',
            self::Claim => 'PC',
            self::Purchase => 'PB',
            self::PaymentVoucher => 'PV',
            self::Maintenance => 'MR',
        };
    }

    public function label(): string
    {
        return __('settings.documents.'.$this->value);
    }
}
