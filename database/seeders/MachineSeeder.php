<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MachineSeeder extends Seeder
{
    private $counter = 0;

    public function run()
    {
        $csvFile = database_path('seeders/data/machines.csv');

        if (!File::exists($csvFile)) {
            $this->command->error('File CSV tidak ditemukan!');
            return;
        }

        // Baca file sebagai string
        $content = File::get($csvFile);

        // Deteksi delimiter
        $delimiters = [',', ';', "\t", '|'];
        $delimiter = ',';

        foreach ($delimiters as $d) {
            if (substr_count($content, $d) > substr_count($content, $delimiter)) {
                $delimiter = $d;
            }
        }

        $this->command->info("Delimiter detected: '{$delimiter}'");

        $file = fopen($csvFile, 'r');
        $header = fgetcsv($file, 0, $delimiter);

        $this->command->info('Columns: ' . implode(' | ', $header));

        $data = [];
        $rowNum = 1;
        $successCount = 0;

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            $rowNum++;

            // Skip empty rows
            if (count($row) < 3 || empty(array_filter($row))) {
                continue;
            }

            // Sample first row
            if ($rowNum == 2) {
                $this->command->info('Sample: ' . implode(' | ', $row));
            }

            $plant = trim($row[0]);
            $line = trim($row[1]);
            $machineName = trim($row[2]);
            $machineType = isset($row[3]) ? trim($row[3]) : '';

            // Skip if critical data is empty
            if (empty($plant) || empty($machineName)) {
                $this->command->warn("Row {$rowNum} skipped: empty critical data");
                continue;
            }

            // Generate UNIQUE barcode dengan counter
            $barcode = $this->generateUniqueBarcode($plant, $line);

            $data[] = [
                'machine_name' => $machineName,
                'barcode' => $barcode,
                'plant' => $plant,
                'line' => $line,
                'machine_type' => $machineType ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert per record untuk menghindari duplicate
            // Lebih lambat tapi lebih aman
            try {
                DB::table('machines')->insert($data[0]);
                $successCount++;
                $data = []; // Reset

                if ($successCount % 10 == 0) {
                    $this->command->info("✓ Imported {$successCount} records...");
                }
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $this->command->warn("Row {$rowNum}: Duplicate barcode, regenerating...");

                    // Regenerate barcode dan coba lagi
                    $data[0]['barcode'] = $this->generateUniqueBarcode($plant, $line, true);

                    try {
                        DB::table('machines')->insert($data[0]);
                        $successCount++;
                    } catch (\Exception $e2) {
                        $this->command->error("Row {$rowNum}: Failed after retry - " . $e2->getMessage());
                    }
                }
                $data = []; // Reset
            }
        }

        fclose($file);

        $total = DB::table('machines')->count();
        $this->command->info("🎉 Import completed!");
        $this->command->info("✓ Successfully imported: {$successCount} records");
        $this->command->info("✓ Total machines in database: {$total}");
    }

    /**
     * Generate unique barcode with counter
     */
    private function generateUniqueBarcode($plant, $line, $forceNew = false)
    {
        if ($forceNew) {
            usleep(1000); // 1ms delay
        }

        $this->counter++;

        $plantCode = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($plant)), 0, 2) ?: 'XX';
        $lineCode = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($line)), 0, 2) ?: 'XX';

        // Format: MC-PLANT-LINE-TIMESTAMP-COUNTER
        $timestamp = now()->format('ymdHis');
        $random = str_pad($this->counter, 4, '0', STR_PAD_LEFT);

        $barcode = "MC-{$plantCode}-{$lineCode}-{$timestamp}{$random}";

        // Double check if exists
        $exists = DB::table('machines')->where('barcode', $barcode)->exists();

        if ($exists) {
            // Tambah random number
            $barcode .= rand(1000, 9999);
        }

        return $barcode;
    }
}
