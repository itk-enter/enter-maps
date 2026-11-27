<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * The roles of a user. Use the cases, not the strings, so that a typing
 * error in the name of a role gives an error. config/packages/security.yaml
 * uses them with "!php/enum".
 */
enum Role: string
{
    /** Each user who is logged in. */
    case USER = 'ROLE_USER';

    /** A user in the group of employees, who can use the builder. */
    case EMPLOYEE = 'ROLE_EMPLOYEE';
}
