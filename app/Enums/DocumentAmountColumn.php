<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentAmountColumn: string implements HasLabel
{
    case Amount = 'amount';
    case Total = 'total';
    case Other = 'other';

    public const DEFAULT = self::Amount;

    public function getLabel(): ?string
    {
        return $this->name;
    }
}
