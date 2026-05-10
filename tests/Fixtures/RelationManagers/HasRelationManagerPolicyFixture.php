<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Waguilar\FilamentGuardian\Concerns\HasRelationManagerPolicy;

/**
 * Fixture so PHPStan analyses HasRelationManagerPolicy through a real `use` site.
 * Not instantiated, not exercised — exists purely for static analysis coverage.
 */
class HasRelationManagerPolicyFixture extends RelationManager
{
    use HasRelationManagerPolicy;

    protected static string $relationship = 'fixtures';
}
