<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Buat Permissions ────────────────────────────────────────────────
        $permissions = [
            // Stock Control
            ['name' => 'dashboard.view',        'display_name' => 'Lihat Dashboard',            'group' => 'Stock Control'],
            ['name' => 'stock.view',             'display_name' => 'Lihat Stock',                'group' => 'Stock Control'],
            ['name' => 'stock.edit',             'display_name' => 'Edit Stock',                 'group' => 'Stock Control'],
            ['name' => 'stock.delete',           'display_name' => 'Hapus Stock',                'group' => 'Stock Control'],
            ['name' => 'output.view',            'display_name' => 'Lihat Output Product',       'group' => 'Stock Control'],
            ['name' => 'output.edit',            'display_name' => 'Edit Output Product',        'group' => 'Stock Control'],
            ['name' => 'forecast.view',          'display_name' => 'Lihat Forecast',             'group' => 'Stock Control'],
            ['name' => 'forecast.edit',          'display_name' => 'Edit Forecast',              'group' => 'Stock Control'],

            // NG System
            ['name' => 'ng.view',               'display_name' => 'Lihat NG Reports',           'group' => 'NG System'],
            ['name' => 'ng.create',             'display_name' => 'Buat NG Report',             'group' => 'NG System'],
            ['name' => 'ng.delete',             'display_name' => 'Hapus NG Report',            'group' => 'NG System'],
            ['name' => 'ng.approve',            'display_name' => 'Approve TA / PICA',          'group' => 'NG System'],
            ['name' => 'ng.close',              'display_name' => 'Close NG Report',            'group' => 'NG System'],
            ['name' => 'suppliers.view',        'display_name' => 'Lihat Supplier',             'group' => 'NG System'],
            ['name' => 'suppliers.edit',        'display_name' => 'Kelola Supplier',            'group' => 'NG System'],
            ['name' => 'parts.view',            'display_name' => 'Lihat Parts',                'group' => 'NG System'],
            ['name' => 'parts.edit',            'display_name' => 'Kelola Parts',               'group' => 'NG System'],

            // Die Shop
            ['name' => 'die-shop.view',         'display_name' => 'Lihat Die Shop Dashboard',   'group' => 'Die Shop'],
            ['name' => 'die-shop.create',       'display_name' => 'Buat Laporan Die Shop',      'group' => 'Die Shop'],
            ['name' => 'die-shop.edit',         'display_name' => 'Edit Laporan Die Shop',      'group' => 'Die Shop'],
            ['name' => 'die-shop.delete',       'display_name' => 'Hapus Laporan Die Shop',     'group' => 'Die Shop'],
            ['name' => 'die-parts.edit',        'display_name' => 'Kelola Die Parts',           'group' => 'Die Shop'],

            // Material Monitoring
            ['name' => 'materials.view',        'display_name' => 'Lihat Material',             'group' => 'Material Monitoring'],
            ['name' => 'materials.edit',        'display_name' => 'Kelola Material',            'group' => 'Material Monitoring'],
            ['name' => 'transaksi.view',        'display_name' => 'Lihat Transaksi',            'group' => 'Material Monitoring'],
            ['name' => 'transaksi.create',      'display_name' => 'Buat Transaksi',             'group' => 'Material Monitoring'],
            ['name' => 'transaksi.delete',      'display_name' => 'Hapus Transaksi',            'group' => 'Material Monitoring'],
            ['name' => 'transaksi.dashboard',   'display_name' => 'Dashboard Transaksi',        'group' => 'Material Monitoring'],

            // Robot / ESP32
            ['name' => 'esp32.view',            'display_name' => 'Lihat Robot Monitor',        'group' => 'Robot Information'],
            ['name' => 'esp32.edit',            'display_name' => 'Edit Settings Robot',        'group' => 'Robot Information'],

            // Line Stop / Maintenance
            ['name' => 'maintenance.view',      'display_name' => 'Lihat Maintenance',          'group' => 'Line Stop Monitoring'],
            ['name' => 'maintenance.create',    'display_name' => 'Buat Maintenance Report',    'group' => 'Line Stop Monitoring'],
            ['name' => 'maintenance.edit',      'display_name' => 'Edit Maintenance',           'group' => 'Line Stop Monitoring'],
            ['name' => 'maintenance.delete',    'display_name' => 'Hapus Maintenance',          'group' => 'Line Stop Monitoring'],
            ['name' => 'lines.view',            'display_name' => 'Lihat Lines',                'group' => 'Line Stop Monitoring'],
            ['name' => 'lines.edit',            'display_name' => 'Kelola Lines',               'group' => 'Line Stop Monitoring'],
            ['name' => 'machines.edit',         'display_name' => 'Kelola Mesin',               'group' => 'Line Stop Monitoring'],

            // OEE
            ['name' => 'oee.view',              'display_name' => 'Lihat OEE',                  'group' => 'OEE System'],
            ['name' => 'oee.calculate',         'display_name' => 'Hitung OEE',                 'group' => 'OEE System'],
            ['name' => 'oee.delete',            'display_name' => 'Hapus OEE Record',           'group' => 'OEE System'],
            ['name' => 'oee.export',            'display_name' => 'Export OEE Data',            'group' => 'OEE System'],

            // Settings (role management sendiri)
            ['name' => 'settings.roles',        'display_name' => 'Kelola Roles & Permissions', 'group' => 'Settings'],
            ['name' => 'settings.users',        'display_name' => 'Kelola User Roles',          'group' => 'Settings'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                array_merge($perm, ['description' => null])
            );
        }

        // ── 2. Buat Role Default ───────────────────────────────────────────────
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description'  => 'Akses penuh ke semua fitur',
                'color'        => 'red',
            ]
        );

        // Admin mendapat semua permissions
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));

        $viewerRole = Role::firstOrCreate(
            ['name' => 'viewer'],
            [
                'display_name' => 'Viewer',
                'description'  => 'Hanya dapat melihat data',
                'color'        => 'blue',
            ]
        );

        // Viewer hanya dapat permission *.view
        $viewerPermissions = Permission::where('name', 'like', '%.view')
            ->orWhere('name', 'like', '%.dashboard')
            ->pluck('id');

        $viewerRole->permissions()->sync($viewerPermissions);

        $operatorRole = Role::firstOrCreate(
            ['name' => 'operator'],
            [
                'display_name' => 'Operator',
                'description'  => 'Dapat membuat dan mengedit data operasional',
                'color'        => 'green',
            ]
        );

        // Operator: semua permission kecuali settings dan delete
        $operatorPermissions = Permission::where('name', 'not like', 'settings.%')
            ->where('name', 'not like', '%.delete')
            ->pluck('id');

        $operatorRole->permissions()->sync($operatorPermissions);

        $this->command->info('✅ Roles & Permissions seeded successfully!');
        $this->command->info('   - Admin: semua permissions');
        $this->command->info('   - Operator: semua kecuali delete & settings');
        $this->command->info('   - Viewer: hanya view permissions');
    }
}
