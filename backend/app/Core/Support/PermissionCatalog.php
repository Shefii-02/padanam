<?php

namespace App\Core\Support;

/**
 * Single source of truth for permissions ("module.action").
 * Seeder syncs it; the admin permission matrix reads it (GET /admin/permissions).
 */
final class PermissionCatalog
{
    public const MODULES = [
        'dashboard' => ['view'],
        'users' => ['view', 'create', 'edit', 'block', 'export', 'impersonate'],
        'roles' => ['view', 'manage'],
        'categories' => ['view', 'manage'],
        'courses' => ['view', 'create', 'edit', 'delete', 'publish', 'all_scope'],
        'batches' => ['view', 'create', 'edit', 'delete'],
        'content' => ['view', 'create', 'edit', 'delete'],
        'live_classes' => ['view', 'schedule', 'go_live', 'delete'],
        'question_bank' => ['view', 'create', 'edit', 'delete', 'import'],
        'tests' => ['view', 'create', 'edit', 'delete', 'publish', 'evaluate'],
        'omr' => ['upload', 'evaluate'],
        'articles' => ['view', 'manage'],
        'daily_quiz' => ['manage'],
        'study_plans' => ['manage'],
        'doubts' => ['view', 'answer'],
        'enrollments' => ['view', 'add_manual', 'remove', 'extend', 'swap'],
        'payments' => ['view', 'create_link', 'refund', 'export'],
        'invoices' => ['view', 'send'],
        'coupons' => ['view', 'manage'],
        'notifications' => ['send', 'send_all', 'manage_templates'],
        'chat' => ['use', 'create_group', 'manage_groups', 'moderate', 'manage_permissions'],
        'leads' => ['view', 'manage', 'export'],
        'reports' => ['view', 'export'],
        'revenue_share' => ['view', 'manage'],
        'settings' => ['manage'],
        'app_versions' => ['manage'],
        'whatsapp' => ['view', 'send', 'manage'],
        'marketing' => ['export'],
        'security' => ['view'],
    ];

    /** Default grants. super_admin bypasses checks (Gate::before). admin gets everything. */
    public const ROLE_DEFAULTS = [
        'admin' => ['*'],
        'staff' => [
            'dashboard.*', 'users.view', 'users.create', 'users.edit', 'users.block', 'users.export',
            'categories.*', 'courses.view', 'courses.create', 'courses.edit', 'courses.publish', 'courses.all_scope',
            'batches.view', 'batches.create', 'batches.edit', 'content.view', 'content.create', 'content.edit',
            'live_classes.view', 'live_classes.schedule', 'live_classes.go_live', 'question_bank.view', 'question_bank.create',
            'question_bank.edit', 'question_bank.import', 'tests.view', 'tests.create', 'tests.edit', 'tests.publish', 'tests.evaluate',
            'omr.*', 'articles.*', 'daily_quiz.*', 'study_plans.*', 'doubts.*', 'enrollments.view', 'enrollments.add_manual',
            'enrollments.extend', 'payments.view', 'payments.create_link', 'payments.export', 'invoices.*', 'coupons.*',
            'notifications.send', 'notifications.send_all', 'chat.use', 'chat.create_group', 'chat.manage_groups', 'chat.moderate',
            'leads.*', 'reports.view', 'reports.export', 'enrollments.swap', 'whatsapp.view', 'whatsapp.send', 'marketing.export',
        ],
        'teacher' => [
            'dashboard.view', 'courses.view', 'batches.view', 'content.view', 'content.create', 'content.edit',
            'live_classes.view', 'live_classes.schedule', 'live_classes.go_live', 'question_bank.view', 'question_bank.create',
            'question_bank.edit', 'question_bank.import', 'tests.view', 'tests.create', 'tests.edit', 'tests.evaluate', 'omr.evaluate',
            'doubts.*', 'enrollments.view', 'notifications.send', 'chat.use', 'chat.create_group', 'chat.moderate',
        ],
        'student' => ['chat.use'],
    ];

    public static function all(): array
    {
        $out = [];
        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $a) {
                $out[] = $module.'.'.$a;
            }
        }

        return $out;
    }

    public static function forRole(string $role): array
    {
        $patterns = self::ROLE_DEFAULTS[$role] ?? [];
        if ($patterns === ['*']) {
            return self::all();
        }

        return array_values(array_filter(self::all(), function ($p) use ($patterns) {
            foreach ($patterns as $pat) {
                if ($pat === $p || (str_ends_with($pat, '.*') && str_starts_with($p, substr($pat, 0, -1)))) {
                    return true;
                }
            }

            return false;
        }));
    }
}
