<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Illuminate\Support\Str;
use Waguilar\FilamentGuardian\Concerns\HasRelationManagerPolicy;

final class RelationManagerPolicyDetector
{
    public static function usesRelationManagerPolicy(string $relationManagerClass): bool
    {
        return in_array(
            HasRelationManagerPolicy::class,
            class_uses_recursive($relationManagerClass),
            true,
        );
    }

    public static function getPolicyClass(string $relationManagerClass): string
    {
        return ResourcePolicyDetector::getPolicyNamespace() . '\\' . self::getPolicyClassBasename($relationManagerClass);
    }

    public static function getPolicyClassBasename(string $relationManagerClass): string
    {
        return self::getRelationManagerSubject($relationManagerClass) . 'Policy';
    }

    public static function getRelationManagerSubject(string $relationManagerClass): string
    {
        $basename = class_basename($relationManagerClass);

        return Str::endsWith($basename, 'RelationManager')
            ? Str::beforeLast($basename, 'RelationManager')
            : $basename;
    }
}
