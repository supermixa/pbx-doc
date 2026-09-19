<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ExtensionNumber;
use App\Models\RouteEntity;
use App\Models\Scenario;
use App\Models\Trunk;

class DashboardController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }

        View::render('dashboard', [
            'title' => 'Главная',
            'counts' => [
                'trunks' => Trunk::count(),
                'routes' => RouteEntity::count(),
                'scenarios' => Scenario::count(),
                'extensions' => ExtensionNumber::count(),
            ],
            'recent' => AuditLog::recent(15),
        ]);
    }
}
