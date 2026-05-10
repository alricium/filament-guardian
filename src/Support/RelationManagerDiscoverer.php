<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

final class RelationManagerDiscoverer
{
    /**
     * Flatten a Resource's getRelations() into a list of relation manager FQNs.
     * Closure-resolved managers inside RelationGroup are skipped — they need
     * a runtime owner record to resolve.
     *
     * @param  class-string  $resourceClass
     * @return list<class-string<RelationManager>>
     */
    public static function collectClasses(string $resourceClass): array
    {
        try {
            /** @var class-string<resource> $resourceClass */
            $relations = $resourceClass::getRelations();
        } catch (Throwable) {
            return [];
        }

        $classes = [];

        foreach ($relations as $relation) {
            if (is_string($relation)) {
                $classes[] = $relation;

                continue;
            }

            if ($relation instanceof RelationManagerConfiguration) {
                $classes[] = $relation->relationManager;

                continue;
            }

            // RelationGroup's $managers may be a Closure that resolves with an
            // owner record — none at static-discovery time. The TypeError from
            // iterating a Closure is caught and the group is silently skipped.
            try {
                foreach ($relation->getManagers() as $manager) {
                    if (is_string($manager)) {
                        $classes[] = $manager;
                    } elseif ($manager instanceof RelationManagerConfiguration) {
                        $classes[] = $manager->relationManager;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $classes;
    }

    /**
     * A relation manager is eligible for Guardian's discovery only when
     * Filament hasn't already routed its authorization elsewhere.
     *
     * @param  class-string<RelationManager>  $rmClass
     */
    public static function isEligible(string $rmClass): bool
    {
        try {
            /** @var class-string|null $relatedResource */
            $relatedResource = $rmClass::getRelatedResource();
        } catch (Throwable) {
            $relatedResource = null;
        }

        if ($relatedResource !== null) {
            return false;
        }

        try {
            if ($rmClass::shouldSkipAuthorization()) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the related model class for a relation manager without a DB
     * call. Mirrors Filament's own resolution path.
     *
     * @param  class-string<RelationManager>  $rmClass
     * @param  class-string  $resourceClass
     * @return class-string|null
     */
    public static function resolveRelatedModel(string $rmClass, string $resourceClass): ?string
    {
        try {
            $relationshipName = $rmClass::getRelationshipName();
        } catch (Throwable) {
            return null;
        }

        if ($relationshipName === '') {
            return null;
        }

        try {
            /** @var class-string<resource> $resourceClass */
            /** @var class-string $parentModelClass */
            $parentModelClass = $resourceClass::getModel();

            if (! class_exists($parentModelClass)) {
                return null;
            }

            $owner = new $parentModelClass;

            if (! method_exists($owner, $relationshipName)) {
                return null;
            }

            $relation = $owner->{$relationshipName}();

            if (! $relation instanceof Relation) {
                return null;
            }

            /** @var class-string $modelClass */
            $modelClass = $relation->getQuery()->getModel()::class;

            return $modelClass;
        } catch (Throwable) {
            return null;
        }
    }
}
