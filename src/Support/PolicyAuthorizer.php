<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use LogicException;
use UnitEnum;

final class PolicyAuthorizer
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function inspect(
        string $subject,
        string | UnitEnum $action,
        string $methodName,
        array $arguments,
        bool $checkPolicyExistence,
    ): Response {
        $user = Filament::auth()->user();
        $policy = Gate::getPolicyFor($subject);

        if ((is_object($policy) || is_string($policy)) && method_exists($policy, $methodName)) {
            return Gate::forUser($user)->inspect($action, $arguments);
        }

        if ($checkPolicyExistence && Filament::isAuthorizationStrict()) {
            $policyClass = match (true) {
                is_string($policy) => $policy,
                is_object($policy) => $policy::class,
                default => null,
            };

            throw new LogicException(blank($policyClass)
                ? "Strict authorization mode is enabled, but no policy was found for [{$subject}]. Run 'php artisan guardian:policies' to generate it."
                : "Strict authorization mode is enabled, but no [{$methodName}()] method was found on [{$policyClass}].");
        }

        return Gate::forUser($user)->inspect($action, $arguments);
    }
}
