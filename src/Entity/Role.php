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

    /**
     * The name of each role, with the value as key, as in the other projects
     * of ITK Dev. For example, the choices of a form field.
     *
     * @return array<string, string>
     */
    public static function asArray(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_column(self::cases(), 'name'),
        );
    }

    /**
     * @return list<string> the values of the roles, for example "ROLE_EMPLOYEE"
     */
    public static function values(): array
    {
        return array_keys(self::asArray());
    }
}
