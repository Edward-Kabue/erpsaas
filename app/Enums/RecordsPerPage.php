<?php

namespace App\Enums;

use App\Enums\Concerns\Utilities;
use Filament\Support\Contracts\HasLabel;

enum RecordsPerPage: int implements HasLabel
{
    use Utilities;
    case Five = 5;
    case Ten = 10;
    case TwentyFive = 25;
    case Fifty = 50;
    case OneHundred = 100;

    public const DEFAULT = self::Ten;

    public const FIVE = self::Five;

    public const TEN = self::Ten;

    public const TWENTY_FIVE = self::TwentyFive;

    public const FIFTY = self::Fifty;

    public const ONE_HUNDRED = self::OneHundred;

    public function getLabel(): ?string
    {
        return (string) $this->value;
    }
}
