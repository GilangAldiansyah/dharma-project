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
                    'color' => 'red',
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
                    'id' => 'material-monitoring',
                    'title' => 'Material Monitoring',
                    'description' => 'Sistem monitoring pengambilan material dan part',
                    'icon' => 'ClipboardList',
                    'color' => 'violet',
                    'route' => '/transaksi',
                    'features' => [
                        'Transaksi Pengambilan Material',
                        'Master Material & Part',
                        'History & Analytics'
                    ]
                ],
                [
                    'id' => 'esp32-monitor',
                    'title' => 'Robot Information',
                    'description' => 'Sistem monitoring real-time counter devices robot',
                    'icon' => 'Bot',
                    'color' => 'black',
                    'route' => '/esp32/monitor',
                    'features' => [
                        'Real-time robot monitoring',
                        'Relay status control',
                        'History logs & analytics'
                    ]
                ],
                [
                    'id' => 'maintenance-monitoring',
                    'title' => 'Line Stop Monitoring',
                    'description' => 'Sistem Monitoring Line Stop',
                    'icon' => 'Activity',
                    'color' => 'orange',
                    'route' => '/maintenance/lines',
                    'features' => [
                        'Laporan Maintenance Mesin',
                        'Barcode Scanning Support',
                        'MTTR & MTBF Calculation'
                    ]
                ],
                [
                    'id' => 'oee-system',
                    'title' => 'OEE System',
                    'description' => 'Sistem monitoring OEE (Overall Equipment Effectiveness)',
                    'icon' => 'TrendingUp',
                    'color' => 'violet',
                    'route' => '/oee',
                    'features' => [
                        'OEE Dashboard & Analytics',
                        'Availability, Performance, Quality Metrics',
                        'Line Comparison & Export'
                    ]
                ],
                // [
                //     'id' => 'kanban-system',
                //     'title' => 'Kanban Production System',
                //     'description' => 'Sistem kanban produksi dengan RFID untuk tracking stock real-time',
                //     'icon' => 'Package',
                //     'color' => 'blue',
                //     'route' => '/products',
                //     'features' => [
                //         'RFID Scan IN/OUT Management',
                //         'Real-time Stock Tracking',
                //         'Product & Line Integration'
                //     ]
                // ]
            ]
        ]);
    }
}
