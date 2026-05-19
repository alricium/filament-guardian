<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->default()
            ->authGuard('web')
            ->plugin(FilamentGuardianPlugin::make());
    }
}
