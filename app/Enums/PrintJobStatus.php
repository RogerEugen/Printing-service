<?php

namespace App\Enums;

enum PrintJobStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Printing = 'printing';
    case Printed = 'printed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Claimed => 'Claimed',
            self::Printing => 'Printing',
            self::Printed => 'Printed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Claimed => 'bg-blue-100 text-blue-800',
            self::Printing => 'bg-indigo-100 text-indigo-800',
            self::Printed => 'bg-emerald-100 text-emerald-800',
            self::Failed => 'bg-red-100 text-red-800',
            self::Cancelled => 'bg-slate-200 text-slate-700',
        };
    }
}
