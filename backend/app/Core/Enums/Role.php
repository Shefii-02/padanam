<?php

namespace App\Core\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Staff = 'staff';
    case Teacher = 'teacher';
    case Student = 'student';

    /** Roles that use the admin web panel. */
    public static function panelRoles(): array
    {
        return [self::SuperAdmin->value, self::Admin->value, self::Staff->value, self::Teacher->value];
    }
}
