<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Template: string implements HasLabel
{
    case Default = 'default';
    case Modern = 'modern';
    case Classic = 'classic';

    public const DEFAULT = 'default';

    public function getLabel(): ?string
    {
        return translate($this->name);
    }
}
