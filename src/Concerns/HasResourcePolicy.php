<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use BackedEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use Waguilar\FilamentGuardian\Support\PolicyAuthorizer;

trait HasResourcePolicy
{
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        if (static::shouldSkipAuthorization()) {
            return Response::allow();
        }

        $methodName = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        $arguments = $record !== null ? [static::class, $record] : [static::class];

        return PolicyAuthorizer::inspect(
            subject: static::class,
            action: $action,
            methodName: $methodName,
            arguments: $arguments,
            checkPolicyExistence: static::shouldCheckPolicyExistence(),
        );
    }
}
