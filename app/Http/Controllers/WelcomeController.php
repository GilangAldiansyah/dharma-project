<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WelcomeController extends Controller
{
    public function index()
    {
        return inertia('Welcome', [
            'systems' => [
                [
                    'id' => 'stock-control',
                    'title' => 'Control Stock System',
                    'description' => 'Kelola stok material, output product, dan monitoring inventory',
                    'icon' => 'Package',
                    'color' => 'blue',
                    'route' => '/dashboard',
                    'features' => [
                        'Dashboard Analytics',
                        'Output Product Management',
                        'Stock Control & Monitoring'
                    ]
                ],
                [
                    'id' => 'ng-system',
                    'title' => 'NG System',
                    'description' => 'Sistem pelaporan NG, PICA, dan manajemen supplier',
                    'icon' => 'AlertTriangle',
                    'color' => 'orange',
                    'route' => '/ng-reports/dashboard',
                    'features' => [
                        'NG Reports & Tracking',
                        'PICA Management',
                        'Master Suppliers & Parts'
                    ]
                ],
                [
                    'id' => 'die-shop',
                    'title' => 'Die Shop System',
                    'description' => 'Sistem manajemen perbaikan die dan maintenance',
                    'icon' => 'Wrench',
                    'color' => 'green',
                    'route' => '/die-shop-dashboard',
                    'features' => [
                        'Dashboard Die Shop',
                        'Laporan Perbaikan',
                        'Master Die Parts'
                    ]
                ],
                [
                    'id' => 'esp32-monitor',
                    'title' => 'ESP32 Monitor',
                    'description' => 'Sistem monitoring real-time untuk ESP32 counter devices',
                    'icon' => 'Bot',
                    'color' => 'black',
                    'route' => '/esp32/monitor',
                    'features' => [
                        'Real-time device monitoring',
                        'Relay status control',
                        'History logs & analytics'
                    ],
                ]
            ]
        ]);
    }
}
