<?php

namespace App\Listeners;

use App\Enums\{Font, MaxContentWidth, ModalWidth, PrimaryColor, RecordsPerPage, TableSortDirection};
use App\Models\Company;
use Filament\Actions\MountableAction;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentColor;
use Filament\Tables\Table;

class ConfigureCompanyDefault
{
    /**
     * Handle the event.
     */
    public function handle(TenantSet $event): void
    {
        /** @var Company $company */
        $company = $event->getTenant();
        $paginationPageOptions = RecordsPerPage::caseValues();
        $defaultPaginationPageOption = $company->appearance->records_per_page->value ?? RecordsPerPage::DEFAULT->value;
        $defaultSort = $company->appearance->table_sort_direction->value ?? TableSortDirection::DEFAULT->value;
        $stripedTables = $company->appearance->is_table_striped ?? false;
        $defaultPrimaryColor = $company->appearance->primary_color ?? PrimaryColor::from(PrimaryColor::DEFAULT->value);
        $modalWidth = $company->appearance->modal_width->value ?? ModalWidth::DEFAULT->value;
        $maxContentWidth = $company->appearance->max_content_width->value ?? MaxContentWidth::DEFAULT->value;
        $defaultFont = $company->appearance->font->value ?? Font::DEFAULT->value;
        $hasTopNavigation = $company->appearance->has_top_navigation ?? false;

        Table::configureUsing(static function (Table $table) use ($paginationPageOptions, $defaultSort, $stripedTables, $defaultPaginationPageOption): void {
            $table
                ->paginationPageOptions($paginationPageOptions)
                ->defaultSort(column: 'id', direction: $defaultSort)
                ->striped($stripedTables)
                ->defaultPaginationPageOption($defaultPaginationPageOption);
        }, isImportant: true);

        MountableAction::configureUsing(static function (MountableAction $action) use ($modalWidth): void {
            $action->modalWidth($modalWidth);
        }, isImportant: true);

        $defaultColor = FilamentColor::register([
            'primary' => $defaultPrimaryColor->getColor(),
        ]);

        FilamentColor::swap($defaultColor);

        Filament::getPanel('company')
            ->font($defaultFont)
            ->brandName($company->name)
            ->topNavigation($hasTopNavigation)
            ->sidebarCollapsibleOnDesktop(! $hasTopNavigation)
            ->maxContentWidth($maxContentWidth);
    }
}
