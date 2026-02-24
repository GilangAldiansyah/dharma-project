<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WelcomeController extends Controller
{
    private array $systems = [
        [
            'id'          => 'stock-control',
            'title'       => 'Control Stock System',
            'description' => 'Kelola stok material, output product, dan monitoring inventory',
            'icon'        => 'Package',
            'color'       => 'blue',
            'route'       => '/dashboard',
            'permission'  => 'dashboard.view',
            'features'    => [
                'Dashboard Analytics',
                'Output Product Management',
                'Stock Control & Monitoring',
            ],
        ],
        [
            'id'          => 'ng-system',
            'title'       => 'NG System',
            'description' => 'Sistem pelaporan NG, PICA, dan manajemen supplier',
            'icon'        => 'AlertTriangle',
            'color'       => 'red',
            'route'       => '/ng-reports/dashboard',
            'permission'  => 'ng.view',
            'features'    => [
                'NG Reports & Tracking',
                'PICA Management',
                'Master Suppliers & Parts',
            ],
        ],
        [
            'id'          => 'die-shop',
            'title'       => 'Die Shop System',
            'description' => 'Sistem manajemen perbaikan die dan maintenance',
            'icon'        => 'Wrench',
            'color'       => 'green',
            'route'       => '/die-shop-dashboard',
            'permission'  => 'die-shop.view',
            'features'    => [
                'Dashboard Die Shop',
                'Laporan Perbaikan',
                'Master Die Parts',
            ],
        ],
        [
            'id'          => 'material-monitoring',
            'title'       => 'Material Monitoring',
            'description' => 'Sistem monitoring pengambilan material dan part',
            'icon'        => 'ClipboardList',
            'color'       => 'violet',
            'route'       => '/transaksi',
            'permission'  => 'transaksi.view',
            'features'    => [
                'Transaksi Pengambilan Material',
                'Master Material & Part',
                'History & Analytics',
            ],
        ],
        [
            'id'          => 'esp32-monitor',
            'title'       => 'Robot Information',
            'description' => 'Sistem monitoring real-time counter devices robot',
            'icon'        => 'Bot',
            'color'       => 'black',
            'route'       => '/esp32/monitor',
            'permission'  => 'esp32.view',
            'features'    => [
                'Real-time robot monitoring',
                'Relay status control',
                'History logs & analytics',
            ],
        ],
        [
            'id'          => 'maintenance-monitoring',
            'title'       => 'Line Stop Monitoring',
            'description' => 'Sistem Monitoring Line Stop',
            'icon'        => 'Activity',
            'color'       => 'orange',
            'route'       => '/maintenance/lines',
            'permission'  => 'lines.view',
            'features'    => [
                'Laporan Maintenance Mesin',
                'Barcode Scanning Support',
                'MTTR & MTBF Calculation',
            ],
        ],
        [
            'id'          => 'oee-system',
            'title'       => 'OEE System',
            'description' => 'Sistem monitoring OEE (Overall Equipment Effectiveness)',
            'icon'        => 'TrendingUp',
            'color'       => 'violet',
            'route'       => '/oee',
            'permission'  => 'oee.view',
            'features'    => [
                'OEE Dashboard & Analytics',
                'Availability, Performance, Quality Metrics',
                'Line Comparison & Export',
            ],
        ],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $user->load('roles.permissions');

        $allowedSystems = collect($this->systems)
            ->filter(fn($system) => $user->hasPermission($system['permission']))
            ->map(fn($system) => collect($system)->except('permission')->toArray())
            ->values()
            ->toArray();

        return inertia('Welcome', [
            'systems' => $allowedSystems,
        ]);
    }
}
