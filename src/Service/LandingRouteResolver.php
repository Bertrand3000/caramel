<?php

declare(strict_types=1);

namespace App\Service;

final class LandingRouteResolver
{
    /**
     * @param list<string> $roles
     */
    public function resolveRoute(array $roles): string
    {
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return 'admin_dashboard';
        }

        if (in_array('ROLE_DMAX', $roles, true) || in_array('ROLE_AGENT_RECUPERATION', $roles, true)) {
            return 'dmax_dashboard';
        }

        return 'shop_dashboard';
    }
}

