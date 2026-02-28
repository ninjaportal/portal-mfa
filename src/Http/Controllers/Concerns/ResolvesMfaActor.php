<?php

namespace NinjaPortal\Mfa\Http\Controllers\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use NinjaPortal\Api\Support\PortalApiContext;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

trait ResolvesMfaActor
{
    protected function authenticatedActor(Request $request, PortalApiContext $context, string $actorContext): Authenticatable
    {
        $guard = $context->guardForContext($actorContext);
        $actor = auth($guard)->user();

        if (! $actor instanceof Authenticatable) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        return $actor;
    }
}
