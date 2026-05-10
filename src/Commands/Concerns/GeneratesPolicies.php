<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands\Concerns;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use RuntimeException;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder;
use Waguilar\FilamentGuardian\Support\RelationManagerPolicyDetector;
use Waguilar\FilamentGuardian\Support\ResourcePolicyDetector;

trait GeneratesPolicies
{
    use ReadsResourceConfig;

    /** @var array<string, string> */
    protected array $stubCache = [];

    protected function generatePolicy(string $resourceClass): ?string
    {
        if (! is_subclass_of($resourceClass, Resource::class)) {
            return null;
        }

        $modelClass = $resourceClass::getModel();
        $policyInfo = $this->getPolicyInfo($resourceClass);

        $stubVariables = $this->buildStubVariablesFor(
            modelClass: $modelClass,
            methods: $this->getResourceMethods($resourceClass),
            subject: $this->getPermissionSubject($resourceClass, $modelClass),
            authorizationContextClass: $resourceClass,
            policyInfo: $policyInfo,
        );

        return $this->writePolicyFile($policyInfo, $stubVariables, $modelClass);
    }

    /**
     * @param  class-string<RelationManager>  $rmClass
     * @param  class-string  $modelClass
     */
    protected function generatePolicyForRelationManager(string $rmClass, string $modelClass): ?string
    {
        if (! class_exists($modelClass)) {
            throw new RuntimeException("Model class not found: {$modelClass}");
        }

        $policyInfo = $this->getRelationManagerPolicyInfo($rmClass, $modelClass);

        $stubVariables = $this->buildStubVariablesFor(
            modelClass: $modelClass,
            methods: $this->getRelationManagerMethods($rmClass),
            subject: $this->getRelationManagerPermissionSubject($rmClass, $modelClass),
            authorizationContextClass: $rmClass,
            policyInfo: $policyInfo,
        );

        return $this->writePolicyFile($policyInfo, $stubVariables, $modelClass);
    }

    protected function getPolicyPath(string $resourceClass): string
    {
        return $this->getPolicyInfo($resourceClass)['path'];
    }

    /**
     * @param  class-string<RelationManager>  $rmClass
     * @param  class-string  $modelClass
     */
    protected function getRelationManagerPolicyPath(string $rmClass, string $modelClass): string
    {
        return $this->getRelationManagerPolicyInfo($rmClass, $modelClass)['path'];
    }

    /**
     * @return array{path: string, namespace: string, policyClassName: string}
     */
    protected function getPolicyInfo(string $resourceClass): array
    {
        /** @var class-string<resource> $resourceClass */
        $modelClass = $resourceClass::getModel();

        if (! class_exists($modelClass)) {
            throw new RuntimeException("Model class not found: {$modelClass}");
        }

        $basename = ResourcePolicyDetector::usesResourcePolicy($resourceClass)
            ? ResourcePolicyDetector::getPolicyClassBasename($resourceClass)
            : class_basename($modelClass) . 'Policy';

        return $this->buildPolicyInfo($basename);
    }

    /**
     * @param  class-string<RelationManager>  $rmClass
     * @param  class-string  $modelClass
     * @return array{path: string, namespace: string, policyClassName: string}
     */
    protected function getRelationManagerPolicyInfo(string $rmClass, string $modelClass): array
    {
        $basename = RelationManagerPolicyDetector::usesRelationManagerPolicy($rmClass)
            ? RelationManagerPolicyDetector::getPolicyClassBasename($rmClass)
            : class_basename($modelClass) . 'Policy';

        return $this->buildPolicyInfo($basename);
    }

    /**
     * @param  array<int, string>  $methods
     */
    protected function generateMethodsContent(
        array $methods,
        string $modelClass,
        string $modelName,
        string $modelVariable,
        string $authModelName,
        string $authModelVariable,
        PermissionKeyBuilder $permissionBuilder,
        string $subject,
        string $resourceClass,
    ): string {
        /** @var array<string> $singleParamMethods */
        $singleParamMethods = config('filament-guardian.policies.single_parameter_methods', []);
        $isAuthenticatable = is_subclass_of($modelClass, Authenticatable::class);

        $methodsContent = '';

        foreach ($methods as $method) {
            $isSingleParam = in_array($method, $singleParamMethods, true) || $isAuthenticatable;
            $stubName = $isSingleParam ? 'SingleParamMethod' : 'MultiParamMethod';

            $methodsContent .= strtr($this->getStub($stubName), [
                '{{ methodName }}' => $method,
                '{{ authModelName }}' => $authModelName,
                '{{ authModelVariable }}' => $authModelVariable,
                '{{ modelName }}' => $modelName,
                '{{ modelVariable }}' => $modelVariable,
                '{{ permission }}' => $permissionBuilder->build($method, $subject, $resourceClass),
            ]);
        }

        return $methodsContent;
    }

    /**
     * @param  class-string<RelationManager>  $rmClass
     * @param  class-string  $modelClass
     */
    protected function getRelationManagerPermissionSubject(string $rmClass, string $modelClass): string
    {
        if (RelationManagerPolicyDetector::usesRelationManagerPolicy($rmClass)) {
            return RelationManagerPolicyDetector::getRelationManagerSubject($rmClass);
        }

        $managed = $this->getManagedRelationManagerConfig($rmClass);

        if (isset($managed['subject']) && is_string($managed['subject'])) {
            return $managed['subject'];
        }

        /** @var string $subjectType */
        $subjectType = config('filament-guardian.relation_managers.subject', 'model');

        return $subjectType === 'class'
            ? RelationManagerPolicyDetector::getRelationManagerSubject($rmClass)
            : class_basename($modelClass);
    }

    protected function getPermissionSubject(string $resourceClass, string $modelClass): string
    {
        if (ResourcePolicyDetector::usesResourcePolicy($resourceClass)) {
            return ResourcePolicyDetector::getResourceSubject($resourceClass);
        }

        $resourceConfig = $this->getManagedResourceConfig($resourceClass);

        if (isset($resourceConfig['subject']) && is_string($resourceConfig['subject'])) {
            return $resourceConfig['subject'];
        }

        /** @var string $subjectType */
        $subjectType = config('filament-guardian.resources.subject', 'model');

        return $subjectType === 'class'
            ? class_basename($resourceClass)
            : class_basename($modelClass);
    }

    /**
     * @return array{fqcn: string, name: string, variable: string}
     */
    protected function getAuthModelInfo(): array
    {
        return [
            'fqcn' => 'Illuminate\\Foundation\\Auth\\User as AuthUser',
            'name' => 'AuthUser',
            'variable' => 'authUser',
        ];
    }

    protected function getStubForModel(string $modelClass): string
    {
        $stubName = is_subclass_of($modelClass, Authenticatable::class)
            ? 'AuthenticatablePolicy'
            : 'DefaultPolicy';

        return $this->getStub($stubName);
    }

    protected function getStub(string $name): string
    {
        if (isset($this->stubCache[$name])) {
            return $this->stubCache[$name];
        }

        $customPath = base_path("stubs/filament-guardian/{$name}.stub");

        if (file_exists($customPath)) {
            return $this->stubCache[$name] = (string) file_get_contents($customPath);
        }

        $packagePath = dirname(__DIR__, 3) . "/stubs/{$name}.stub";

        if (file_exists($packagePath)) {
            return $this->stubCache[$name] = (string) file_get_contents($packagePath);
        }

        throw new RuntimeException("Stub file not found: {$name}.stub");
    }

    /**
     * @param  array<string, string>  $variables
     */
    protected function replaceStubVariables(string $stub, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements["{{ {$key} }}"] = $value;
        }

        return strtr($stub, $replacements);
    }

    /**
     * @return array{path: string, namespace: string, policyClassName: string}
     */
    private function buildPolicyInfo(string $policyClassName): array
    {
        /** @var string $basePath */
        $basePath = config('filament-guardian.policies.path', app_path('Policies'));

        return [
            'path' => $basePath . DIRECTORY_SEPARATOR . $policyClassName . '.php',
            'namespace' => ResourcePolicyDetector::getPolicyNamespace(),
            'policyClassName' => $policyClassName,
        ];
    }

    /**
     * @param  array<int, string>  $methods
     * @param  array{path: string, namespace: string, policyClassName: string}  $policyInfo
     * @return array<string, string>
     */
    private function buildStubVariablesFor(
        string $modelClass,
        array $methods,
        string $subject,
        string $authorizationContextClass,
        array $policyInfo,
    ): array {
        $modelName = class_basename($modelClass);
        $modelVariable = Str::camel($modelName);
        $authModelInfo = $this->getAuthModelInfo();

        $methodsContent = $this->generateMethodsContent(
            methods: $methods,
            modelClass: $modelClass,
            modelName: $modelName,
            modelVariable: $modelVariable,
            authModelName: $authModelInfo['name'],
            authModelVariable: $authModelInfo['variable'],
            permissionBuilder: app(PermissionKeyBuilder::class),
            subject: $subject,
            resourceClass: $authorizationContextClass,
        );

        return [
            'namespace' => $policyInfo['namespace'],
            'authModelFqcn' => $authModelInfo['fqcn'],
            'authModelName' => $authModelInfo['name'],
            'authModelVariable' => $authModelInfo['variable'],
            'modelFqcn' => $modelClass,
            'modelName' => $modelName,
            'modelVariable' => $modelVariable,
            'modelPolicy' => $policyInfo['policyClassName'],
            'methods' => $methodsContent,
        ];
    }

    /**
     * @param  array{path: string, namespace: string, policyClassName: string}  $policyInfo
     * @param  array<string, string>  $stubVariables
     */
    private function writePolicyFile(array $policyInfo, array $stubVariables, string $modelClass): string
    {
        $stub = $this->getStubForModel($modelClass);
        $content = $this->replaceStubVariables($stub, $stubVariables);

        $directory = dirname($policyInfo['path']);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_put_contents($policyInfo['path'], $content) === false) {
            throw new RuntimeException("Failed to write policy file: {$policyInfo['path']}");
        }

        return $policyInfo['path'];
    }
}
