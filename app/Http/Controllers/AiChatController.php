<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AiChatController extends Controller
{
    const HELPDESK = [
        ['name' => 'IT Support',            'number' => '6281234567890', 'display' => '0812-3456-7890'],
        ['name' => 'Maintenance Lead',      'number' => '6281398765432', 'display' => '0813-9876-5432'],
        ['name' => 'Production Supervisor', 'number' => '6281111223344', 'display' => '0811-1122-3344'],
    ];

    const FOLLOW_UPS = [
        'oee'         => ['Tren OEE minggu ini?', 'Line mana OEE terendah?', 'Detail availability per line?'],
        'ng'          => ['Part mana paling sering NG?', 'Berapa NG open saat ini?', 'Status PICA yang belum selesai?'],
        'robot'       => ['History produksi robot hari ini?', 'Robot mana yang sedang paused?', 'Total counter semua device?'],
        'maintenance' => ['Berapa lama rata-rata repair?', 'Line mana paling sering breakdown?', 'Maintenance aktif saat ini?'],
        'material'    => ['Transaksi material hari ini?', 'Material apa yang paling sering diambil?'],
        'output'      => ['Perbandingan output shift 1 vs shift 2?', 'Total NG output hari ini?'],
        'summary'     => ['Detail status robot?', 'NG report yang perlu tindakan?', 'OEE terbaru?'],
    ];

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
        ]);

        $msg     = $request->message;
        $history = $request->history ?? [];

        if ($this->contains(strtolower($msg), ['helpdesk', 'kontak', 'hubungi', 'telepon', 'contact', 'bantuan teknis', 'support'])) {
            $lines = ["📞 *Helpdesk 4W Department*\n"];
            foreach (self::HELPDESK as $h) {
                $lines[] = "• *{$h['name']}*\n  WA: {$h['display']}";
            }
            $lines[] = "\nJam operasional: Senin–Jumat, 07.00–16.00 WIB";
            return response()->json([
                'reply'      => implode("\n", $lines),
                'type'       => 'helpdesk',
                'follow_ups' => [],
            ]);
        }

        $context   = $this->buildDatabaseContext($msg, $history);
        $dataTypes = $this->detectDataTypes($msg, $history);

        $systemPrompt = "Kamu adalah asisten AI untuk sistem 4W Department PT Dharma Polimetal Tbk.
        Sistem ini mencakup: Control Stock, NG System, Die Shop, Material Monitoring, Robot Information, Line Stop Monitoring, dan OEE System.
        Jawab dalam Bahasa Indonesia, ramah, singkat, dan to the point.
        Gunakan format yang mudah dibaca. Gunakan bullet point bila perlu.
        Jika ada data dari database, tampilkan dengan rapi.
        Jika data tidak ditemukan atau kosong, sampaikan dengan jelas bahwa data memang tidak ada.
        Jangan mengarang data yang tidak ada di konteks.
        Ingat konteks percakapan sebelumnya dan gunakan untuk menjawab dengan lebih relevan.

DATA REAL-TIME DARI DATABASE:
{$context}";

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            array_slice($history, -10),
            [['role' => 'user', 'content' => $msg]]
        );

        $response = Http::withToken(env('CLOUDFLARE_API_TOKEN'))
            ->timeout(60)
            ->post(
                "https://api.cloudflare.com/client/v4/accounts/" . env('CLOUDFLARE_ACCOUNT_ID') . "/ai/run/@cf/meta/llama-3.1-8b-instruct",
                [
                    'messages'   => $messages,
                    'max_tokens' => 1024,
                ]
            );

        if ($response->failed()) {
            \Log::error('Cloudflare AI error: ' . $response->body());
            return response()->json(['error' => 'Gagal menghubungi AI', 'detail' => $response->body()], 500);
        }

        $text      = $response->json('result.response') ?? 'Maaf, tidak ada respons dari AI.';
        $followUps = $this->getFollowUps($dataTypes);

        return response()->json([
            'reply'      => $text,
            'follow_ups' => $followUps,
        ]);
    }

    public function alerts()
    {
        $alerts = [];
        $now    = Carbon::now();
        $today  = $now->toDateString();

        try {
            $ngOpen = DB::table('ng_reports')->where('status', 'open')->count();
            if ($ngOpen > 0) {
                $alerts[] = [
                    'level'   => $ngOpen >= 5 ? 'danger' : 'warning',
                    'icon'    => '⚠️',
                    'message' => "{$ngOpen} laporan NG masih open",
                    'action'  => 'Tampilkan NG reports yang open',
                ];
            }

            $ngTaOverdue = DB::table('ng_reports')
                ->where('status', 'open')
                ->whereNull('ta_submitted_at')
                ->where('reported_at', '<', $now->copy()->subDay()->toDateTimeString())
                ->count();
            if ($ngTaOverdue > 0) {
                $alerts[] = [
                    'level'   => 'danger',
                    'icon'    => '🚨',
                    'message' => "{$ngTaOverdue} NG melewati deadline Temporary Action",
                    'action'  => 'Tampilkan NG yang overdue TA',
                ];
            }

            $maintenanceActive = DB::table('maintenance_reports')
                ->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki'])
                ->count();
            if ($maintenanceActive > 0) {
                $alerts[] = [
                    'level'   => 'warning',
                    'icon'    => '🔧',
                    'message' => "{$maintenanceActive} maintenance sedang aktif",
                    'action'  => 'Tampilkan maintenance yang aktif',
                ];
            }

            $jamKerja = $now->hour >= 7 && $now->hour < 16;
            if ($jamKerja) {
                $robotOn = DB::table('esp32_devices')->where('relay_status', 1)->count();
                $totalRobot = DB::table('esp32_devices')->count();
                if ($totalRobot > 0 && $robotOn === 0) {
                    $alerts[] = [
                        'level'   => 'danger',
                        'icon'    => '🤖',
                        'message' => "Semua robot relay OFF di jam kerja",
                        'action'  => 'Tampilkan status semua robot',
                    ];
                } elseif ($robotOn < $totalRobot) {
                    $off = $totalRobot - $robotOn;
                    $alerts[] = [
                        'level'   => 'info',
                        'icon'    => '🤖',
                        'message' => "{$off} robot relay OFF",
                        'action'  => 'Tampilkan status robot',
                    ];
                }

                $robotPaused = DB::table('esp32_devices')->where('is_paused', 1)->count();
                if ($robotPaused > 0) {
                    $alerts[] = [
                        'level'   => 'warning',
                        'icon'    => '⏸️',
                        'message' => "{$robotPaused} robot sedang paused",
                        'action'  => 'Tampilkan robot yang paused',
                    ];
                }
            }

            $lineMaintenance = DB::table('lines')
                ->where('status', 'maintenance')
                ->where('is_archived', 0)
                ->count();
            if ($lineMaintenance > 0) {
                $alerts[] = [
                    'level'   => 'warning',
                    'icon'    => '🏭',
                    'message' => "{$lineMaintenance} line sedang maintenance",
                    'action'  => 'Tampilkan status line',
                ];
            }

            $oeeRendah = DB::table('line_oee_records as o')
                ->leftJoin('lines as l', 'l.id', '=', 'o.line_id')
                ->where('o.period_date', $today)
                ->where('o.oee', '<', 50)
                ->select('l.line_name', 'o.oee')
                ->get();
            foreach ($oeeRendah as $o) {
                $alerts[] = [
                    'level'   => 'danger',
                    'icon'    => '📉',
                    'message' => "OEE {$o->line_name} rendah: {$o->oee}%",
                    'action'  => 'Tampilkan data OEE terbaru',
                ];
            }

        } catch (\Exception $e) {
            \Log::error('AI Alerts Error: ' . $e->getMessage());
        }

        return response()->json(['alerts' => $alerts]);
    }

    public function exportData(Request $request)
    {
        $request->validate(['type' => 'required|string']);
        $type  = $request->type;
        $rows  = [];
        $sheetName = $type;

        switch ($type) {
            case 'robot':
                $sheetName = 'Robot ESP32';
                $data = DB::table('esp32_devices as d')
                    ->leftJoin('lines as l', 'l.id', '=', 'd.line_id')
                    ->select('d.device_id', 'l.line_name', 'd.counter_a', 'd.counter_b', 'd.reject', 'd.max_count', 'd.cycle_time', 'd.relay_status', 'd.is_paused', 'd.last_update')
                    ->get();
                $rows[] = ['Device ID', 'Line', 'Counter A', 'Counter B', 'Reject', 'Max Count', 'Cycle Time (ms)', 'Relay', 'Status', 'Last Update'];
                foreach ($data as $d) {
                    $rows[] = [$d->device_id, $d->line_name ?? '-', $d->counter_a, $d->counter_b, $d->reject, $d->max_count, $d->cycle_time, $d->relay_status ? 'ON' : 'OFF', $d->is_paused ? 'PAUSED' : 'RUNNING', $d->last_update];
                }
                break;

            case 'ng':
                $sheetName = 'NG Reports';
                $data = DB::table('ng_reports as n')
                    ->leftJoin('parts as p', 'p.id', '=', 'n.part_id')
                    ->select('n.report_number', 'p.part_name', 'n.status', 'n.ta_status', 'n.pica_status', 'n.reported_by', 'n.reported_at')
                    ->orderByDesc('n.reported_at')
                    ->get();
                $rows[] = ['No Report', 'Part', 'Status', 'TA Status', 'PICA Status', 'Dilaporkan Oleh', 'Tanggal'];
                foreach ($data as $n) {
                    $rows[] = [$n->report_number, $n->part_name ?? '-', $n->status, $n->ta_status ?? '-', $n->pica_status ?? '-', $n->reported_by, $n->reported_at];
                }
                break;

            case 'maintenance':
                $sheetName = 'Maintenance';
                $data = DB::table('maintenance_reports as m')
                    ->leftJoin('lines as l', 'l.id', '=', 'm.line_id')
                    ->leftJoin('machines as mc', 'mc.id', '=', 'm.machine_id')
                    ->select('m.report_number', 'l.line_name', 'mc.machine_name', 'm.problem', 'm.status', 'm.shift', 'm.reported_by', 'm.reported_at', 'm.repair_duration_minutes', 'm.line_stop_duration_minutes')
                    ->orderByDesc('m.reported_at')
                    ->get();
                $rows[] = ['No Report', 'Line', 'Mesin', 'Problem', 'Status', 'Shift', 'Dilaporkan Oleh', 'Tanggal', 'Repair (mnt)', 'Line Stop (mnt)'];
                foreach ($data as $r) {
                    $rows[] = [$r->report_number, $r->line_name ?? '-', $r->machine_name ?? '-', $r->problem, $r->status, $r->shift ?? '-', $r->reported_by, $r->reported_at, $r->repair_duration_minutes, $r->line_stop_duration_minutes];
                }
                break;

            case 'oee':
                $sheetName = 'OEE Records';
                $data = DB::table('line_oee_records as o')
                    ->leftJoin('lines as l', 'l.id', '=', 'o.line_id')
                    ->select('l.line_name', 'o.oee', 'o.availability', 'o.performance', 'o.quality', 'o.shift', 'o.period_date', 'o.period_type', 'o.total_counter_a', 'o.total_reject', 'o.good_count')
                    ->orderByDesc('o.period_date')
                    ->get();
                $rows[] = ['Line', 'OEE (%)', 'Availability (%)', 'Performance (%)', 'Quality (%)', 'Shift', 'Tanggal', 'Tipe Periode', 'Counter A', 'Reject', 'Good Count'];
                foreach ($data as $o) {
                    $rows[] = [$o->line_name ?? '-', $o->oee, $o->availability, $o->performance, $o->quality, $o->shift ?? '-', $o->period_date, $o->period_type, $o->total_counter_a, $o->total_reject, $o->good_count];
                }
                break;

            case 'stock':
                $sheetName = 'Transaksi Material';
                $data = DB::table('transaksi_materials as t')
                    ->leftJoin('materials as m', 'm.id', '=', 't.material_id')
                    ->select('t.transaksi_id', 'm.nama_material', 'm.material_type', 'm.satuan', 't.qty', 't.pic', 't.shift', 't.tanggal')
                    ->orderByDesc('t.tanggal')
                    ->get();
                $rows[] = ['ID Transaksi', 'Material', 'Tipe', 'Satuan', 'Qty', 'PIC', 'Shift', 'Tanggal'];
                foreach ($data as $s) {
                    $rows[] = [$s->transaksi_id, $s->nama_material ?? '-', $s->material_type ?? '-', $s->satuan ?? '-', $s->qty, $s->pic, $s->shift ?? '-', $s->tanggal];
                }
                break;

            case 'output':
                $sheetName = 'Output Product';
                $data = DB::table('output_products')
                    ->select('type', 'sap_no', 'penanggung_jawab', 'product_unit', 'qty_day', 'out_shift1', 'out_shift2', 'out_shift3', 'ng_shift1', 'ng_shift2', 'ng_shift3', 'total', 'stock_date')
                    ->orderByDesc('stock_date')
                    ->get();
                $rows[] = ['Type', 'SAP No', 'PJ', 'Unit', 'Qty/Day', 'Out S1', 'Out S2', 'Out S3', 'NG S1', 'NG S2', 'NG S3', 'Total', 'Tanggal'];
                foreach ($data as $o) {
                    $rows[] = [$o->type, $o->sap_no, $o->penanggung_jawab, $o->product_unit, $o->qty_day, $o->out_shift1, $o->out_shift2, $o->out_shift3, $o->ng_shift1, $o->ng_shift2, $o->ng_shift3, $o->total, $o->stock_date];
                }
                break;

            default:
                return response()->json(['error' => 'Tipe export tidak dikenal'], 400);
        }

        return response()->json(['rows' => $rows, 'sheetName' => $sheetName]);
    }

    private function detectDataTypes(string $message, array $history = []): array
    {
        $combined = strtolower($message . ' ' . implode(' ', array_column($history, 'content')));
        $types    = [];

        if ($this->contains($combined, ['oee', 'availability', 'performance', 'quality']))      $types[] = 'oee';
        if ($this->contains($combined, ['ng', 'defect', 'cacat', 'pica']))                      $types[] = 'ng';
        if ($this->contains($combined, ['robot', 'esp32', 'counter', 'device', 'relay']))       $types[] = 'robot';
        if ($this->contains($combined, ['maintenance', 'perbaikan', 'repair', 'downtime']))     $types[] = 'maintenance';
        if ($this->contains($combined, ['material', 'transaksi', 'stock', 'stok', 'gudang']))  $types[] = 'material';
        if ($this->contains($combined, ['output', 'produk', 'sap', 'hasil']))                   $types[] = 'output';
        if ($this->contains($combined, ['summary', 'ringkasan', 'rekap', 'dashboard']))         $types[] = 'summary';

        return $types;
    }

    private function getFollowUps(array $dataTypes): array
    {
        $result = [];
        foreach ($dataTypes as $type) {
            if (isset(self::FOLLOW_UPS[$type])) {
                $result = array_merge($result, self::FOLLOW_UPS[$type]);
            }
        }
        return array_slice(array_unique($result), 0, 3);
    }

    private function buildDatabaseContext(string $message, array $history = []): string
    {
        $combined = strtolower($message . ' ' . implode(' ', array_column($history, 'content')));
        $msg      = strtolower($message);
        $context  = [];

        try {
            if ($this->contains($combined, ['robot', 'esp32', 'counter', 'device', 'brio', 'relay', 'seat leg', 'radiator', 'comptail', 'spare tire', 'monitoring robot'])) {
                $devices = DB::table('esp32_devices as d')
                    ->leftJoin('lines as l', 'l.id', '=', 'd.line_id')
                    ->select('d.device_id', 'l.line_name', 'd.counter_a', 'd.counter_b', 'd.reject', 'd.relay_status', 'd.is_paused', 'd.max_count', 'd.cycle_time', 'd.last_update')
                    ->get();

                if ($devices->isNotEmpty()) {
                    $context[] = "=== DATA ROBOT/ESP32 (Real-time) ===";
                    foreach ($devices as $d) {
                        $relay  = $d->relay_status ? 'ON' : 'OFF';
                        $status = $d->is_paused ? 'PAUSED' : 'RUNNING';
                        $context[] = "Device: {$d->device_id} | Line: " . ($d->line_name ?? 'N/A');
                        $context[] = "  Counter A: {$d->counter_a} | Counter B: {$d->counter_b} | Reject: {$d->reject} | Max: {$d->max_count}";
                        $context[] = "  Relay: {$relay} | Status: {$status} | Cycle Time: {$d->cycle_time}ms | Last Update: {$d->last_update}";
                    }
                } else {
                    $context[] = "=== DATA ROBOT/ESP32 === Tidak ada data device.";
                }

                if ($this->contains($combined, ['histor', 'riwayat', 'kemarin', 'hari ini', 'today', 'produksi'])) {
                    $keyword = $this->extractDeviceKeyword($msg);
                    $history_data = DB::table('esp32_production_histories')
                        ->when($keyword, fn($q) => $q->where('device_id', 'like', "%{$keyword}%"))
                        ->orderByDesc('production_finished_at')
                        ->limit(10)
                        ->get();

                    if ($history_data->isNotEmpty()) {
                        $context[] = "\n=== HISTORY PRODUKSI ===";
                        foreach ($history_data as $h) {
                            $context[] = "Device: {$h->device_id} | Shift: {$h->shift}";
                            $context[] = "  Counter A: {$h->total_counter_a} | Counter B: {$h->total_counter_b} | Reject: {$h->total_reject}";
                            $context[] = "  Status: {$h->completion_status} | Mulai: {$h->production_started_at} | Selesai: {$h->production_finished_at}";
                        }
                    }
                }
            }

            if ($this->contains($combined, ['line', 'lini', 'status line', 'operating', 'stopped', 'plant'])) {
                $lines = DB::table('lines')
                    ->where('is_archived', 0)
                    ->select('line_code', 'line_name', 'plant', 'status', 'total_failures', 'total_repair_hours', 'last_operation_start', 'last_line_stop')
                    ->get();

                if ($lines->isNotEmpty()) {
                    $context[] = "\n=== STATUS LINE ===";
                    foreach ($lines as $l) {
                        $context[] = "[{$l->line_code}] {$l->line_name} ({$l->plant}) | Status: {$l->status} | Failures: {$l->total_failures} | Repair: {$l->total_repair_hours}h";
                    }
                }
            }

            if ($this->contains($combined, ['ng', 'defect', 'cacat', 'pica', 'laporan ng', 'report ng', 'overdue', 'deadline'])) {
                $ngReports = DB::table('ng_reports as n')
                    ->leftJoin('parts as p', 'p.id', '=', 'n.part_id')
                    ->select('n.report_number', 'p.part_name', 'p.part_code', 'n.status', 'n.ta_status', 'n.pica_status', 'n.reported_by', 'n.reported_at', 'n.ta_submitted_at', 'n.pica_uploaded_at')
                    ->orderByDesc('n.reported_at')
                    ->limit(10)
                    ->get();

                if ($ngReports->isNotEmpty()) {
                    $context[] = "\n=== DATA NG REPORTS (10 Terbaru) ===";
                    foreach ($ngReports as $n) {
                        $taOverdue   = !$n->ta_submitted_at && Carbon::parse($n->reported_at)->addDay()->isPast() ? ' [OVERDUE TA]' : '';
                        $picaOverdue = !$n->pica_uploaded_at && Carbon::parse($n->reported_at)->addDays(3)->isPast() ? ' [OVERDUE PICA]' : '';
                        $context[] = "No: {$n->report_number} | Part: " . ($n->part_name ?? '-') . " ({$n->part_code}){$taOverdue}{$picaOverdue}";
                        $context[] = "  Status: {$n->status} | TA: " . ($n->ta_status ?? 'belum') . " | PICA: " . ($n->pica_status ?? 'belum') . " | Oleh: {$n->reported_by} | {$n->reported_at}";
                    }
                    $summary = DB::table('ng_reports')->selectRaw("status, COUNT(*) as total")->groupBy('status')->get();
                    $context[] = "Ringkasan: " . $summary->map(fn($s) => "{$s->status}: {$s->total}")->join(' | ');
                } else {
                    $context[] = "\n=== DATA NG REPORTS === Tidak ada laporan NG.";
                }
            }

            if ($this->contains($combined, ['maintenance', 'perbaikan', 'repair', 'mttr', 'mtbf', 'downtime', 'line stop'])) {
                $reports = DB::table('maintenance_reports as m')
                    ->leftJoin('lines as l', 'l.id', '=', 'm.line_id')
                    ->leftJoin('machines as mc', 'mc.id', '=', 'm.machine_id')
                    ->select('m.report_number', 'l.line_name', 'mc.machine_name', 'm.problem', 'm.status', 'm.shift', 'm.reported_by', 'm.reported_at', 'm.repair_duration_minutes', 'm.line_stop_duration_minutes')
                    ->orderByDesc('m.reported_at')
                    ->limit(10)
                    ->get();

                if ($reports->isNotEmpty()) {
                    $context[] = "\n=== DATA MAINTENANCE (10 Terbaru) ===";
                    foreach ($reports as $r) {
                        $context[] = "No: {$r->report_number} | Line: " . ($r->line_name ?? '-') . " | Mesin: " . ($r->machine_name ?? '-');
                        $context[] = "  Problem: {$r->problem} | Status: {$r->status} | Shift: " . ($r->shift ?? '-');
                        $context[] = "  Repair: {$r->repair_duration_minutes} mnt | Line Stop: {$r->line_stop_duration_minutes} mnt | {$r->reported_at}";
                    }
                } else {
                    $context[] = "\n=== DATA MAINTENANCE === Tidak ada laporan maintenance.";
                }
            }

            if ($this->contains($combined, ['material', 'transaksi', 'pengambilan', 'ambil', 'pinjam', 'stock', 'stok', 'gudang', 'inventory'])) {
                $transaksi = DB::table('transaksi_materials as t')
                    ->leftJoin('materials as m', 'm.id', '=', 't.material_id')
                    ->select('t.transaksi_id', 'm.nama_material', 'm.material_type', 'm.satuan', 't.qty', 't.pic', 't.shift', 't.tanggal')
                    ->orderByDesc('t.tanggal')
                    ->limit(15)
                    ->get();

                if ($transaksi->isNotEmpty()) {
                    $context[] = "\n=== DATA TRANSAKSI MATERIAL (15 Terbaru) ===";
                    foreach ($transaksi as $t) {
                        $context[] = "[{$t->transaksi_id}] {$t->nama_material} ({$t->material_type}) | Qty: {$t->qty} {$t->satuan} | PIC: {$t->pic} | Shift: {$t->shift} | {$t->tanggal}";
                    }
                } else {
                    $context[] = "\n=== DATA TRANSAKSI MATERIAL === Tidak ada transaksi.";
                }

                $materialList = DB::table('materials')
                    ->whereNull('deleted_at')
                    ->select('nama_material', 'material_type', 'satuan', 'material_id')
                    ->orderBy('nama_material')
                    ->limit(20)
                    ->get();

                if ($materialList->isNotEmpty()) {
                    $context[] = "\n=== DAFTAR MATERIAL ===";
                    foreach ($materialList as $m) {
                        $context[] = "[{$m->material_id}] {$m->nama_material} | Tipe: {$m->material_type} | Satuan: {$m->satuan}";
                    }
                }
            }

            if ($this->contains($combined, ['output', 'produk', 'product', 'hasil produksi', 'sap'])) {
                $outputs = DB::table('output_products')
                    ->select('type', 'sap_no', 'penanggung_jawab', 'product_unit', 'qty_day', 'out_shift1', 'out_shift2', 'out_shift3', 'ng_shift1', 'ng_shift2', 'ng_shift3', 'total', 'stock_date')
                    ->orderByDesc('stock_date')
                    ->limit(15)
                    ->get();

                if ($outputs->isNotEmpty()) {
                    $context[] = "\n=== DATA OUTPUT PRODUCT TERBARU ===";
                    foreach ($outputs as $o) {
                        $totalOut = $o->out_shift1 + $o->out_shift2 + $o->out_shift3;
                        $totalNg  = $o->ng_shift1 + $o->ng_shift2 + $o->ng_shift3;
                        $context[] = "{$o->type} | SAP: {$o->sap_no} | PJ: {$o->penanggung_jawab} | Unit: {$o->product_unit}";
                        $context[] = "  Out: S1={$o->out_shift1} S2={$o->out_shift2} S3={$o->out_shift3} (Total: {$totalOut}) | NG: {$totalNg} | {$o->stock_date}";
                    }
                } else {
                    $context[] = "\n=== DATA OUTPUT PRODUCT === Tidak ada data.";
                }
            }

            if ($this->contains($combined, ['oee', 'availability', 'performance', 'quality', 'effectiveness'])) {
                $oee = DB::table('line_oee_records as o')
                    ->leftJoin('lines as l', 'l.id', '=', 'o.line_id')
                    ->select('l.line_name', 'o.oee', 'o.availability', 'o.performance', 'o.quality', 'o.period_date', 'o.shift', 'o.period_type', 'o.total_counter_a', 'o.total_reject', 'o.good_count', 'o.target_production')
                    ->orderByDesc('o.period_date')
                    ->limit(10)
                    ->get();

                if ($oee->isNotEmpty()) {
                    $context[] = "\n=== DATA OEE TERBARU ===";
                    foreach ($oee as $o) {
                        $status = $o->oee >= 85 ? 'EXCELLENT' : ($o->oee >= 70 ? 'GOOD' : ($o->oee >= 50 ? 'FAIR' : 'POOR'));
                        $context[] = "Line: " . ($o->line_name ?? '-') . " | OEE: {$o->oee}% [{$status}] | A: {$o->availability}% | P: {$o->performance}% | Q: {$o->quality}%";
                        $context[] = "  Shift: " . ($o->shift ?? '-') . " | Tipe: {$o->period_type} | Tanggal: {$o->period_date}";
                        $context[] = "  Counter A: {$o->total_counter_a} | Target: {$o->target_production} | Good: {$o->good_count} | Reject: {$o->total_reject}";
                    }
                } else {
                    $context[] = "\n=== DATA OEE === Tidak ada data OEE.";
                }
            }

            if ($this->contains($combined, ['summary', 'ringkasan', 'rekap', 'dashboard', 'hari ini', 'today'])) {
                $today         = Carbon::today()->toDateString();
                $totalNgOpen   = DB::table('ng_reports')->where('status', 'open')->count();
                $totalNgToday  = DB::table('ng_reports')->whereDate('reported_at', $today)->count();
                $ngOverdueTA   = DB::table('ng_reports')->where('status', 'open')->whereNull('ta_submitted_at')->where('reported_at', '<', Carbon::now()->subDay())->count();
                $maintActive   = DB::table('maintenance_reports')->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki'])->count();
                $robotRelayOn  = DB::table('esp32_devices')->where('relay_status', 1)->count();
                $robotPaused   = DB::table('esp32_devices')->where('is_paused', 1)->count();
                $totalRobot    = DB::table('esp32_devices')->count();
                $lineOperating = DB::table('lines')->where('status', 'operating')->where('is_archived', 0)->count();
                $lineMaint     = DB::table('lines')->where('status', 'maintenance')->where('is_archived', 0)->count();
                $lineStopped   = DB::table('lines')->where('status', 'stopped')->where('is_archived', 0)->count();
                $transaksiHari = DB::table('transaksi_materials')->whereDate('tanggal', $today)->count();
                $outputHari    = DB::table('output_products')->whereDate('stock_date', $today)->sum('total');

                $context[] = "\n=== SUMMARY HARI INI ({$today}) ===";
                $context[] = "NG: Open={$totalNgOpen} | Hari Ini={$totalNgToday} | Overdue TA={$ngOverdueTA}";
                $context[] = "Maintenance Aktif: {$maintActive}";
                $context[] = "Robot: {$robotRelayOn}/{$totalRobot} ON | Paused={$robotPaused}";
                $context[] = "Line: Operating={$lineOperating} | Maintenance={$lineMaint} | Stopped={$lineStopped}";
                $context[] = "Transaksi Material Hari Ini: {$transaksiHari}";
                $context[] = "Total Output Hari Ini: {$outputHari} pcs";
            }

        } catch (\Exception $e) {
            \Log::error('AI DB Context Error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            $context[] = "\nCatatan sistem: Terjadi error: " . $e->getMessage();
        }

        return empty($context)
            ? "Tidak ada data spesifik. Jawab berdasarkan pengetahuan umum sistem manufaktur."
            : implode("\n", $context);
    }

    private function contains(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) return true;
        }
        return false;
    }

    private function extractDeviceKeyword(string $message): ?string
    {
        preg_match_all('/\b([a-z0-9]{2,}(?:[-_][a-z0-9]+)*)\b/', $message, $matches);
        $stopWords = ['data','ambil','cari','lihat','tampil','robot','histori','riwayat','produksi',
                      'monitoring','dari','untuk','yang','semua','hari','ini','kemarin','today','terbaru','tampilkan'];
        foreach ($matches[1] ?? [] as $match) {
            if (!in_array($match, $stopWords) && strlen($match) > 2) return $match;
        }
        return null;
    }
}
