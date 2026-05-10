<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands\Concerns;

trait ReadsResourceConfig
{
    /**
     * @return array<int, string>
     */
    protected function getResourceMethods(string $resourceClass): array
    {
        return $this->resolveMethods($this->getManagedResourceConfig($resourceClass));
    }

    /**
     * @param  class-string  $rmClass
     * @return array<int, string>
     */
    protected function getRelationManagerMethods(string $rmClass): array
    {
        return $this->resolveMethods($this->getManagedRelationManagerConfig($rmClass));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getManagedResourceConfig(string $resourceClass): ?array
    {
        /** @var array<class-string, array<string, mixed>> $managed */
        $managed = config('filament-guardian.resources.manage', []);

        return $managed[$resourceClass] ?? null;
    }

    /**
     * @param  class-string  $rmClass
     * @return array<string, mixed>|null
     */
    protected function getManagedRelationManagerConfig(string $rmClass): ?array
    {
        /** @var array<class-string, array<string, mixed>> $managed */
        $managed = config('filament-guardian.relation_managers.manage', []);

        return $managed[$rmClass] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $managed
     * @return array<int, string>
     */
    private function resolveMethods(?array $managed): array
    {
        /** @var array<int, string> $defaults */
        $defaults = config('filament-guardian.policies.methods', []);

        /** @var array<int, string>|null $methods */
        $methods = $managed['methods'] ?? null;

        if ($methods === null) {
            return array_values($defaults);
        }

        /** @var bool $merge */
        $merge = config('filament-guardian.policies.merge', true);

        return $merge
            ? array_values(array_unique(array_merge($defaults, $methods)))
            : array_values($methods);
    }
}
