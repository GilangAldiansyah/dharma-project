<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Part;
use App\Models\ProductionPlan;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Sample data dari Excel
        $parts = [
            [
                'material_number' => '55351-BZ190',
                'material_description' => 'BRACE INST. PANEL TO FLOOR',
                'part_name' => 'A4ADMD55MBRPE24SEM',
                'line_type' => 'BIG PRESS',
                'frequency_type' => 'DAILY',
            ],
            [
                'material_number' => '67136-BZ050',
                'material_description' => 'EXTENSION, RR DOOR OUTSIDE REINFORCE, LH',
                'part_name' => 'A4ADMD30GPTTP21FIN',
                'line_type' => 'BIG PRESS',
                'frequency_type' => 'DAILY',
            ],
            [
                'material_number' => '17167-0Y020',
                'material_description' => 'INSULATOR EXH MANIFOLD HEAT NO,1 889F',
                'part_name' => 'A4TMI889F01PF00FIN',
                'line_type' => 'INSULATOR',
                'frequency_type' => 'DAILY',
            ],
            [
                'material_number' => '17168-0Y020',
                'material_description' => 'INSULATOR EXH MANIFOLD HEAT NO,2 889F',
                'part_name' => 'A4TMI889F02PE00FIN',
                'line_type' => 'INSULATOR',
                'frequency_type' => 'DAILY',
            ],
            [
                'material_number' => '57817-BZ030',
                'material_description' => 'REINF, BELT ANCHOR, NO.2 RH-BZ030-TRIM',
                'part_name' => 'A4ADMD26GPTTP33SEM',
                'line_type' => 'SEYI',
                'frequency_type' => 'DAILY',
            ],
            [
                'material_number' => '17168-OY100',
                'material_description' => 'INSULATOR EXH MANIFOLD HEAT NO2 535B-CAL',
                'part_name' => 'A4TMI926F01CA00FIN',
                'line_type' => 'CAULKING',
                'frequency_type' => 'DAILY',
            ],
            [
                'material_number' => '57023-BZ020',
                'material_description' => 'MEMBER SUB-ASSY, RR SIDE, RH D14',
                'part_name' => 'A4ADMD14N01SW01FIN',
                'line_type' => 'WELD SPOT',
                'frequency_type' => 'DAILY',
            ],
            [
                'material_number' => 'A4ADMD30HDLPR06SEM',
                'material_description' => 'CASE M7005 - PROGRESSIVE',
                'part_name' => 'CASE M7005',
                'line_type' => 'BUDOMARI',
                'frequency_type' => 'WEEKLY',
            ],
        ];

        foreach ($parts as $partData) {
            Part::create($partData);
        }

        // Sample production plans
        $part = Part::where('material_number', '55351-BZ190')->first();
        if ($part) {
            ProductionPlan::create([
                'part_id' => $part->id,
                'order_number' => '111001089597',
                'target_qty' => 400,
                'plan_date' => now(),
                'shift' => '1',
            ]);
        }
    }
}
