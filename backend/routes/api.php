<?php

use Illuminate\Support\Facades\Route;

/*
| Every module owns a routes.php. They are grouped by audience:
|   /api/v1/public/*   no login (invite previews, payment link pages, invoices)
|   /api/v1/app/*      student + teacher (Flutter)
|   /api/v1/admin/*    admin, staff, teacher (React panel) – permission checked per route
|   /api/v1/webhooks/* payment gateways
|   /api/v1/internal/* Node realtime → Laravel (shared key)
*/
foreach (glob(app_path('Modules/*/routes.php')) as $file) {
    require $file;
}
