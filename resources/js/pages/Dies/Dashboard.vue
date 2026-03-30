<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import {
    Wrench, AlertTriangle, Clock, CheckCircle2, Activity,
    ChevronRight, BarChart2, RefreshCw, TrendingUp, Download,
    CalendarCheck, X, Calendar, Zap, Shield, AlertCircle,
    ChevronDown, ChevronUp, Eye, Layers, User
} from 'lucide-vue-next';
import Chart from 'chart.js/auto';
import * as XLSX from 'xlsx';

interface Forecast { day: number; stroke: number; percentage: number; status: string }
interface DiesProcess { id: number; process_name: string; tonase: number | null }
interface PicUser { id: number; name: string }
interface DueItem {
    id_sap: string; no_part: string; nama_dies: string; line: string;
    std_stroke: number; current_stroke: number; forecast_per_day: number;
    percentage: number; days_left: number | null; est_mtc_date: string | null;
    last_mtc_date: string | null; freq_maintenance: string | null;
    status_mtc: string; dies_status: string; is_overdue: boolean;
    forecasts: Forecast[]; has_scheduled: boolean;
    scheduled_id: number | null; scheduled_date: string | null;
    scheduled_process_id: number | null; scheduled_pic_name: string | null;
    processes: DiesProcess[];
}
interface ByLine { line: string; total: number; overdue: number; due_soon: number; due_warn: number }
interface RecentRecord {
    id: number; report_no: string; dies_id: string; pic_name: string;
    report_date: string; status: string; stroke_at_maintenance: number;
    dies: { id_sap: string; no_part: string; nama_dies: string; line: string } | null;
}
interface StrokeTrend { month: string; pm_count: number; avg_stroke: number }
interface Props {
    summary: { total_dies: number; total_active: number; overdue: number; due_soon: number; corrective_open: number };
    dueList: DueItem[];
    byLine: ByLine[];
    recentPm: RecentRecord[];
    recentCm: RecentRecord[];
    strokeTrend: StrokeTrend[];
    picList: PicUser[];
}

const props = defineProps<Props>();

const activeTab         = ref<'overview' | 'schedule' | 'activity'>('overview');
const expandedDies      = ref<string | null>(null);
const showScheduleModal = ref(false);
const schedulingDies    = ref<DueItem | null>(null);
const activityTab       = ref<'pm' | 'cm'>('pm');

const toggleExpand = (id: string) => { expandedDies.value = expandedDies.value === id ? null : id; };

// ── Schedule form ──
const scheduleForm = useForm({
    dies_id:        '',
    process_id:     null as number | null,
    scheduled_date: '',
    pic_id:         null as number | null,
});

const processOpen        = ref(false);
const selectedProcessObj = computed(() =>
    schedulingDies.value?.processes.find(p => p.id === scheduleForm.process_id) ?? null
);

// PIC searchable dropdown
const picSearch      = ref('');
const picOpen        = ref(false);
const selectedPicObj = computed(() => props.picList.find(u => u.id === scheduleForm.pic_id) ?? null);
const filteredPic    = computed(() => {
    const q = picSearch.value.toLowerCase().trim();
    if (!q) return props.picList;
    return props.picList.filter(u => u.name.toLowerCase().includes(q));
});
const selectPic        = (u: PicUser) => { scheduleForm.pic_id = u.id; picSearch.value = u.name; picOpen.value = false; };
const clearPic         = () => { scheduleForm.pic_id = null; picSearch.value = ''; picOpen.value = true; };
const closePicDropdown = () => { setTimeout(() => { picOpen.value = false; }, 180); };

const openSchedule = (d: DueItem) => {
    schedulingDies.value        = d;
    scheduleForm.dies_id        = d.id_sap;
    scheduleForm.process_id     = d.scheduled_process_id ?? null;
    scheduleForm.scheduled_date = d.scheduled_date
        ? (typeof d.scheduled_date === 'string' ? d.scheduled_date.slice(0, 10) : d.scheduled_date)
        : (d.est_mtc_date ?? new Date().toISOString().slice(0, 10));
    scheduleForm.pic_id         = null;
    processOpen.value           = false;
    picOpen.value               = false;

    // Pre-fill PIC if already scheduled
    if (d.scheduled_pic_name) {
        const found = props.picList.find(u => u.name === d.scheduled_pic_name);
        if (found) { scheduleForm.pic_id = found.id; picSearch.value = found.name; }
        else        { picSearch.value = ''; }
    } else {
        picSearch.value = '';
    }

    showScheduleModal.value = true;
};

const closeScheduleModal = () => {
    showScheduleModal.value = false;
    schedulingDies.value    = null;
    picSearch.value         = '';
    scheduleForm.reset();
};

const submitSchedule = () => {
    scheduleForm.post('/dies/schedule-pm', {
        onSuccess: () => closeScheduleModal(),
    });
};

// ── Chart ──
const isDark    = () => document.documentElement.classList.contains('dark');
const gridColor = () => isDark() ? '#374151' : '#f1f5f9';
const textColor = () => isDark() ? '#94a3b8' : '#64748b';
const trendCanvas = ref<HTMLCanvasElement | null>(null);
let trendChart: Chart | null = null;

const fmtMonth = (m: string) => {
    const [y, mo] = m.split('-');
    const months  = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return `${months[parseInt(mo) - 1]} '${y.slice(2)}`;
};

const createTrendChart = () => {
    if (!trendCanvas.value || props.strokeTrend.length === 0) return;
    trendChart = new Chart(trendCanvas.value, {
        type: 'bar',
        data: {
            labels: props.strokeTrend.map(t => fmtMonth(t.month)),
            datasets: [{
                label: 'Jumlah PM',
                data: props.strokeTrend.map(t => t.pm_count),
                backgroundColor: (ctx) => {
                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 200);
                    gradient.addColorStop(0, 'rgba(59,130,246,0.9)');
                    gradient.addColorStop(1, 'rgba(99,102,241,0.4)');
                    return gradient;
                },
                borderColor: '#6366f1', borderWidth: 1.5, borderRadius: 8, borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 600, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: isDark() ? '#1e293b' : '#0f172a', padding: 12, cornerRadius: 10, callbacks: { label: (ctx) => `  PM dilakukan: ${ctx.parsed.y}x` } }
            },
            scales: {
                x: { ticks: { color: textColor(), font: { size: 11, weight: 'bold' } }, grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, ticks: { color: textColor(), font: { size: 11 }, stepSize: 1 }, grid: { color: gridColor() }, border: { display: false } }
            }
        }
    });
};
const rebuildChart = () => { trendChart?.destroy(); trendChart = null; nextTick(createTrendChart); };
onMounted(async () => {
    await nextTick();
    createTrendChart();
    let lastDark = isDark();
    const obs = new MutationObserver(() => { const nowDark = isDark(); if (nowDark !== lastDark) { lastDark = nowDark; rebuildChart(); } });
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    onUnmounted(() => obs.disconnect());
});
onUnmounted(() => trendChart?.destroy());

// ── Config ──
const diesStatusCfg: Record<string, { badge: string; bar: string; text: string; bg: string; border: string }> = {
    'Siap Pakai':  { badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400', bar: 'bg-emerald-500',  text: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-900/20',  border: 'border-emerald-200 dark:border-emerald-800' },
    'Dijadwalkan': { badge: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400',     bar: 'bg-yellow-400',  text: 'text-yellow-600 dark:text-yellow-400',  bg: 'bg-yellow-50 dark:bg-yellow-900/20',    border: 'border-yellow-200 dark:border-yellow-800' },
    'Disegerakan': { badge: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',     bar: 'bg-orange-500',  text: 'text-orange-600 dark:text-orange-400',  bg: 'bg-orange-50 dark:bg-orange-900/20',    border: 'border-orange-200 dark:border-orange-800' },
    'Diharuskan':  { badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',                 bar: 'bg-red-500',     text: 'text-red-600 dark:text-red-400',        bg: 'bg-red-50 dark:bg-red-900/20',          border: 'border-red-200 dark:border-red-800' },
};
const statusMtcCfg: Record<string, { badge: string }> = {
    'OK MTC':      { badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' },
    'Prepare MTC': { badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' },
    'DELAY MTC':   { badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' },
};
const pmStatusCfg: Record<string, { label: string; badge: string }> = {
    scheduled:   { label: 'Scheduled',   badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' },
    pending:     { label: 'Pending',     badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' },
    completed:   { label: 'Completed',   badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' },
};
const cmStatusCfg: Record<string, { label: string; badge: string }> = {
    open:        { label: 'Open',        badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' },
    closed:      { label: 'Closed',      badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' },
};

const fmtDate = (d: string | null) =>
    !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const maxLineTotal = computed(() => Math.max(...props.byLine.map(l => l.total), 1));
const dueByStatus  = computed(() => {
    const groups: Record<string, DueItem[]> = { 'Diharuskan': [], 'Disegerakan': [], 'Dijadwalkan': [], 'Siap Pakai': [] };
    props.dueList.forEach(d => { if (groups[d.dies_status]) groups[d.dies_status].push(d); });
    return groups;
});
const normalCount = computed(() => Math.max(0, props.summary.total_active - props.summary.overdue - props.summary.due_soon));

const exportExcel = () => {
    const wb   = XLSX.utils.book_new();
    const rows = [
        ['Dies Dashboard Export'], [],
        ['SUMMARY'],
        ['Total Dies', 'Aktif', 'Overdue', 'Due Soon', 'CM Open', 'Normal'],
        [props.summary.total_dies, props.summary.total_active, props.summary.overdue, props.summary.due_soon, props.summary.corrective_open, normalCount.value],
        [], ['DUE LIST + FORECAST'],
        ['No Part', 'Nama Dies', 'Line', 'Proses', 'Std Stroke', 'Current', '%', 'Dies Status', 'Status MTC', 'Est. PM', 'Last MTC', 'PIC Jadwal'],
        ...props.dueList.map(d => [
            d.no_part, d.nama_dies, d.line,
            d.processes.find(p => p.id === d.scheduled_process_id)?.process_name ?? '-',
            d.std_stroke, d.current_stroke, `${d.percentage}%`, d.dies_status, d.status_mtc,
            d.est_mtc_date ?? '-', d.last_mtc_date ?? '-', d.scheduled_pic_name ?? '-',
        ]),
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = Array(12).fill({ wch: 15 });
    XLSX.utils.book_append_sheet(wb, ws, 'Dashboard');
    XLSX.writeFile(wb, `Dies_Dashboard_${new Date().toISOString().slice(0, 10)}.xlsx`);
};
</script>

<template>
    <Head title="Dies Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'Dies', href: '/dies/dashboard' }, { title: 'Dashboard', href: '/dies/dashboard' }]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex-shrink-0 shadow-lg shadow-blue-500/30">
                            <BarChart2 class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
                        </span>
                        Dies Dashboard
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-11">Monitoring PM & CM dies</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="exportExcel" class="flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold active:scale-95 transition-all shadow-sm">
                        <Download class="w-3.5 h-3.5" /><span class="hidden sm:inline">Export</span>
                    </button>
                    <button @click="router.reload()" class="flex items-center gap-1.5 px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:bg-gray-200 active:scale-95 transition-all">
                        <RefreshCw class="w-3.5 h-3.5" /><span class="hidden sm:inline">Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3">
                <div class="col-span-2 sm:col-span-1 relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 p-4 text-white shadow-lg shadow-blue-500/20">
                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-white/10 rounded-full"></div>
                    <p class="text-xs font-semibold text-blue-100 flex items-center gap-1 relative z-10"><Activity class="w-3 h-3" /> Total Dies</p>
                    <p class="text-4xl font-black mt-1 relative z-10">{{ summary.total_dies }}</p>
                    <p class="text-xs text-blue-200 mt-0.5 relative z-10">{{ summary.total_active }} aktif</p>
                </div>
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-red-500 to-rose-600 p-4 text-white shadow-lg shadow-red-500/20">
                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-white/10 rounded-full"></div>
                    <p class="text-xs font-semibold text-red-100 flex items-center gap-1 relative z-10"><AlertTriangle class="w-3 h-3" /> Overdue</p>
                    <p class="text-4xl font-black mt-1 relative z-10">{{ summary.overdue }}</p>
                    <p class="text-xs text-red-200 mt-0.5 relative z-10">Harus PM segera</p>
                </div>
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 to-amber-500 p-4 text-white shadow-lg shadow-orange-500/20">
                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-white/10 rounded-full"></div>
                    <p class="text-xs font-semibold text-orange-100 flex items-center gap-1 relative z-10"><Clock class="w-3 h-3" /> Due Soon</p>
                    <p class="text-4xl font-black mt-1 relative z-10">{{ summary.due_soon }}</p>
                    <p class="text-xs text-orange-100 mt-0.5 relative z-10">85–100% stroke</p>
                </div>
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-purple-600 to-violet-700 p-4 text-white shadow-lg shadow-purple-500/20">
                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-white/10 rounded-full"></div>
                    <p class="text-xs font-semibold text-purple-100 flex items-center gap-1 relative z-10"><Wrench class="w-3 h-3" /> CM Open</p>
                    <p class="text-4xl font-black mt-1 relative z-10">{{ summary.corrective_open }}</p>
                    <p class="text-xs text-purple-200 mt-0.5 relative z-10">Belum selesai</p>
                </div>
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-4 text-white shadow-lg shadow-emerald-500/20">
                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-white/10 rounded-full"></div>
                    <p class="text-xs font-semibold text-emerald-100 flex items-center gap-1 relative z-10"><CheckCircle2 class="w-3 h-3" /> Normal</p>
                    <p class="text-4xl font-black mt-1 relative z-10">{{ normalCount }}</p>
                    <p class="text-xs text-emerald-100 mt-0.5 relative z-10">Siap pakai</p>
                </div>
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-yellow-500 to-orange-400 p-4 text-white shadow-lg shadow-yellow-500/20">
                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-white/10 rounded-full"></div>
                    <p class="text-xs font-semibold text-yellow-100 flex items-center gap-1 relative z-10"><Zap class="w-3 h-3" /> Perlu PM</p>
                    <p class="text-4xl font-black mt-1 relative z-10">{{ dueList.length }}</p>
                    <p class="text-xs text-yellow-100 mt-0.5 relative z-10">≥75% stroke</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-2xl">
                <button v-for="tab in [
                    { v: 'overview', l: 'Overview', icon: BarChart2 },
                    { v: 'schedule', l: 'Schedule PM', icon: CalendarCheck },
                    { v: 'activity', l: 'Aktivitas', icon: Activity },
                ]" :key="tab.v" @click="activeTab = tab.v as any"
                    :class="['flex-1 flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all',
                        activeTab === tab.v ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                    <component :is="tab.icon" class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">{{ tab.l }}</span>
                    <span class="sm:hidden">{{ tab.l.split(' ')[0] }}</span>
                </button>
            </div>

            <!-- TAB: OVERVIEW -->
            <div v-show="activeTab === 'overview'" class="space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div v-for="[status, cfg] in Object.entries(diesStatusCfg)" :key="status" :class="['flex items-center gap-3 p-3 rounded-xl border', cfg.bg, cfg.border]">
                        <div :class="['w-3 h-3 rounded-full flex-shrink-0', cfg.bar]"></div>
                        <div class="min-w-0">
                            <p :class="['text-xs font-bold', cfg.text]">{{ status }}</p>
                            <p class="text-xs text-gray-400">{{ status === 'Siap Pakai' ? '< 75%' : status === 'Dijadwalkan' ? '75–85%' : status === 'Disegerakan' ? '85–100%' : '≥ 100%' }}</p>
                        </div>
                        <p :class="['text-xl font-black ml-auto', cfg.text]">{{ dueList.filter(d => d.dies_status === status).length }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h2 class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2"><TrendingUp class="w-4 h-4 text-blue-500" /> Status per Line</h2>
                            <span class="text-xs text-gray-400">{{ byLine.length }} line</span>
                        </div>
                        <div class="p-4 space-y-3">
                            <div v-if="byLine.length === 0" class="py-8 text-center text-gray-400 text-xs">Tidak ada data</div>
                            <div v-for="l in byLine" :key="l.line">
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-gray-700 dark:text-gray-200 w-12">{{ l.line }}</span>
                                        <div class="flex gap-1">
                                            <span v-if="l.overdue"  class="px-1.5 py-0.5 bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400 rounded text-xs font-bold">{{ l.overdue }} OD</span>
                                            <span v-if="l.due_soon" class="px-1.5 py-0.5 bg-orange-100 text-orange-600 dark:bg-orange-900/40 dark:text-orange-400 rounded text-xs font-bold">{{ l.due_soon }}</span>
                                            <span v-if="l.due_warn" class="px-1.5 py-0.5 bg-yellow-100 text-yellow-600 dark:bg-yellow-900/40 dark:text-yellow-400 rounded text-xs font-bold">{{ l.due_warn }}</span>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-400 font-semibold">{{ l.total }}</span>
                                </div>
                                <div class="h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden flex gap-px">
                                    <div v-if="l.overdue"  class="h-full bg-gradient-to-r from-red-500 to-rose-600 rounded-l-full" :style="{ width: `${(l.overdue / maxLineTotal) * 100}%` }"></div>
                                    <div v-if="l.due_soon" class="h-full bg-gradient-to-r from-orange-500 to-amber-500" :style="{ width: `${(l.due_soon / maxLineTotal) * 100}%` }"></div>
                                    <div v-if="l.due_warn" class="h-full bg-gradient-to-r from-yellow-400 to-yellow-500" :style="{ width: `${(l.due_warn / maxLineTotal) * 100}%` }"></div>
                                    <div class="h-full bg-gradient-to-r from-emerald-400 to-teal-500 flex-1 rounded-r-full"></div>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-1.5 text-xs text-gray-500"><div class="w-2.5 h-2.5 bg-red-500 rounded-sm"></div> Overdue</div>
                                <div class="flex items-center gap-1.5 text-xs text-gray-500"><div class="w-2.5 h-2.5 bg-orange-500 rounded-sm"></div> Disegerakan</div>
                                <div class="flex items-center gap-1.5 text-xs text-gray-500"><div class="w-2.5 h-2.5 bg-yellow-400 rounded-sm"></div> Dijadwalkan</div>
                                <div class="flex items-center gap-1.5 text-xs text-gray-500"><div class="w-2.5 h-2.5 bg-emerald-500 rounded-sm"></div> Aman</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <h2 class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2"><BarChart2 class="w-4 h-4 text-indigo-500" /> Trend PM (6 Bulan)</h2>
                        </div>
                        <div class="p-4">
                            <div v-if="strokeTrend.length === 0" class="py-10 text-center text-gray-400 text-xs">Belum ada data PM</div>
                            <div v-else style="height: 200px; position: relative;"><canvas ref="trendCanvas"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2"><AlertCircle class="w-4 h-4 text-red-500" /> Top Dies Kritis</h2>
                        <button @click="activeTab = 'schedule'" class="text-xs text-blue-500 font-semibold flex items-center gap-1 hover:underline">Lihat semua <ChevronRight class="w-3 h-3" /></button>
                    </div>
                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-if="dueList.filter(d => d.dies_status === 'Diharuskan' || d.dies_status === 'Disegerakan').length === 0" class="py-10 text-center text-gray-400 text-xs">
                            <Shield class="w-8 h-8 mx-auto mb-2 text-emerald-300" /> Semua dies dalam kondisi baik
                        </div>
                        <div v-for="d in dueList.filter(d => d.dies_status === 'Diharuskan' || d.dies_status === 'Disegerakan').slice(0, 5)" :key="d.id_sap"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <div :class="['w-1.5 h-12 rounded-full flex-shrink-0', diesStatusCfg[d.dies_status]?.bar]"></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-xs font-black text-gray-900 dark:text-white">{{ d.no_part }}</p>
                                    <span class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded text-xs font-semibold">{{ d.line }}</span>
                                    <span :class="['px-1.5 py-0.5 rounded text-xs font-bold', diesStatusCfg[d.dies_status]?.badge]">{{ d.dies_status }}</span>
                                    <span :class="['px-1.5 py-0.5 rounded text-xs font-bold', statusMtcCfg[d.status_mtc]?.badge]">{{ d.status_mtc }}</span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ d.nama_dies }}</p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden max-w-40">
                                        <div :class="['h-full rounded-full', diesStatusCfg[d.dies_status]?.bar]" :style="{ width: `${Math.min(d.percentage, 100)}%` }"></div>
                                    </div>
                                    <span :class="['text-xs font-black', diesStatusCfg[d.dies_status]?.text]">{{ d.percentage }}%</span>
                                    <span class="text-xs text-gray-400">{{ d.current_stroke.toLocaleString() }}/{{ d.std_stroke.toLocaleString() }}</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                                <span v-if="d.days_left !== null" :class="['text-xs font-black', d.days_left <= 0 ? 'text-red-600 dark:text-red-400' : d.days_left <= 3 ? 'text-orange-600 dark:text-orange-400' : 'text-amber-600 dark:text-amber-400']">
                                    {{ d.days_left <= 0 ? 'OVERDUE' : `${d.days_left}h` }}
                                </span>
                                <button @click="openSchedule(d)"
                                    :class="['flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all active:scale-95',
                                        d.has_scheduled ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400' : 'bg-orange-500 hover:bg-orange-600 text-white']">
                                    <CalendarCheck class="w-3 h-3" /> {{ d.has_scheduled ? 'Ubah' : 'Jadwal' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: SCHEDULE PM -->
            <div v-show="activeTab === 'schedule'" class="space-y-3">
                <div class="flex gap-2 overflow-x-auto scrollbar-none pb-1">
                    <div v-for="[status, cfg] in Object.entries(diesStatusCfg)" :key="status"
                        :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold flex-shrink-0', cfg.bg, cfg.border, cfg.text]">
                        <div :class="['w-1.5 h-1.5 rounded-full', cfg.bar]"></div>
                        {{ status }} <span class="font-black">{{ dueList.filter(d => d.dies_status === status).length }}</span>
                    </div>
                </div>
                <template v-for="[status, items] in Object.entries(dueByStatus)" :key="status">
                    <div v-if="items.length > 0" class="space-y-2">
                        <div :class="['flex items-center gap-2 px-3 py-2 rounded-xl border', diesStatusCfg[status]?.bg, diesStatusCfg[status]?.border]">
                            <div :class="['w-2.5 h-2.5 rounded-full flex-shrink-0', diesStatusCfg[status]?.bar]"></div>
                            <span :class="['text-xs font-black', diesStatusCfg[status]?.text]">{{ status }}</span>
                            <span class="text-xs text-gray-400">— {{ items.length }} dies</span>
                        </div>
                        <div v-for="d in items" :key="d.id_sap"
                            :class="['bg-white dark:bg-gray-800 rounded-2xl border shadow-sm overflow-hidden',
                                d.dies_status === 'Diharuskan'  ? 'border-red-200 dark:border-red-800' :
                                d.dies_status === 'Disegerakan' ? 'border-orange-200 dark:border-orange-800' :
                                d.dies_status === 'Dijadwalkan' ? 'border-yellow-200 dark:border-yellow-800' :
                                'border-gray-100 dark:border-gray-700']">
                            <div class="p-3.5">
                                <div class="flex items-start gap-3">
                                    <div :class="['w-1 min-h-12 rounded-full flex-shrink-0 self-stretch', diesStatusCfg[d.dies_status]?.bar]"></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <p class="text-sm font-black text-gray-900 dark:text-white">{{ d.no_part }}</p>
                                                    <span class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded text-xs font-bold">{{ d.line }}</span>
                                                    <span v-if="d.has_scheduled" class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded text-xs font-bold flex items-center gap-0.5">
                                                        <CalendarCheck class="w-3 h-3" /> Terjadwal
                                                    </span>
                                                </div>
                                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ d.nama_dies }}</p>
                                                <div v-if="d.has_scheduled" class="mt-1 flex items-center gap-3 flex-wrap">
                                                    <span v-if="d.scheduled_process_id" class="flex items-center gap-1 text-xs text-purple-600 dark:text-purple-400 font-semibold">
                                                        <Layers class="w-3 h-3" /> {{ d.processes.find(p => p.id === d.scheduled_process_id)?.process_name }}
                                                    </span>
                                                    <span v-if="d.scheduled_pic_name" class="flex items-center gap-1 text-xs text-gray-500">
                                                        <User class="w-3 h-3" /> {{ d.scheduled_pic_name }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex gap-1.5 flex-shrink-0">
                                                <button @click="router.visit(`/dies/${d.id_sap}`)" class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-500 rounded-lg hover:bg-gray-200 transition-colors">
                                                    <Eye class="w-3.5 h-3.5" />
                                                </button>
                                                <button @click="openSchedule(d)"
                                                    :class="['flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all active:scale-95',
                                                        d.has_scheduled ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 hover:bg-blue-200' : 'bg-orange-500 hover:bg-orange-600 text-white']">
                                                    <CalendarCheck class="w-3 h-3" /> {{ d.has_scheduled ? 'Ubah' : 'Jadwalkan' }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-2.5 space-y-2">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-2.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                                    <div :class="['h-full rounded-full transition-all', diesStatusCfg[d.dies_status]?.bar]" :style="{ width: `${Math.min(d.percentage, 100)}%` }"></div>
                                                </div>
                                                <span :class="['text-sm font-black w-12 text-right', diesStatusCfg[d.dies_status]?.text]">{{ d.percentage }}%</span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                                                <span>Stroke: <span class="font-bold text-gray-700 dark:text-gray-300">{{ d.current_stroke.toLocaleString() }}</span> / {{ d.std_stroke.toLocaleString() }}</span>
                                                <span>Last MTC: <span class="font-bold text-gray-700 dark:text-gray-300">{{ fmtDate(d.last_mtc_date) }}</span></span>
                                                <span v-if="d.est_mtc_date" :class="['font-bold', d.is_overdue ? 'text-red-500' : 'text-orange-500']">Est. PM: {{ fmtDate(d.est_mtc_date) }}</span>
                                                <span v-if="d.days_left !== null" :class="['font-black text-sm', d.days_left <= 0 ? 'text-red-600 dark:text-red-400' : d.days_left <= 3 ? 'text-orange-600 dark:text-orange-400' : 'text-amber-600 dark:text-amber-400']">
                                                    {{ d.days_left <= 0 ? '⚠ OVERDUE' : `${d.days_left} hari lagi` }}
                                                </span>
                                                <span :class="['px-1.5 py-0.5 rounded text-xs font-bold', statusMtcCfg[d.status_mtc]?.badge]">{{ d.status_mtc }}</span>
                                            </div>
                                        </div>
                                        <button @click="toggleExpand(d.id_sap)" class="mt-2.5 flex items-center gap-1 text-xs text-gray-400 hover:text-blue-500 transition-colors font-semibold">
                                            <component :is="expandedDies === d.id_sap ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" /> Forecast H+1 s/d H+5
                                        </button>
                                        <div v-if="expandedDies === d.id_sap" class="mt-2.5 grid grid-cols-5 gap-1.5">
                                            <div v-for="f in d.forecasts" :key="f.day"
                                                :class="['rounded-xl p-2 text-center border',
                                                    f.status === 'Diharuskan'  ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' :
                                                    f.status === 'Disegerakan' ? 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800' :
                                                    f.status === 'Dijadwalkan' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800' :
                                                    'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800']">
                                                <p class="text-xs font-bold text-gray-500 dark:text-gray-400">H+{{ f.day }}</p>
                                                <p :class="['text-base font-black mt-0.5', diesStatusCfg[f.status]?.text ?? 'text-gray-600']">{{ f.percentage }}%</p>
                                                <p :class="['text-xs font-semibold leading-tight mt-0.5', diesStatusCfg[f.status]?.text ?? '']">{{ f.status }}</p>
                                                <p class="text-xs text-gray-400 mt-0.5">{{ f.stroke.toLocaleString() }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <div v-if="dueList.length === 0" class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <Shield class="w-12 h-12 mx-auto mb-3 text-emerald-400" />
                    <p class="text-sm font-bold text-gray-600 dark:text-gray-300">Semua dies dalam kondisi aman</p>
                    <p class="text-xs text-gray-400 mt-1">Tidak ada dies yang perlu perhatian saat ini</p>
                </div>
            </div>

            <!-- TAB: AKTIVITAS -->
            <div v-show="activeTab === 'activity'" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <button @click="activityTab = 'pm'" :class="['flex items-center gap-2 p-4 rounded-2xl border-2 transition-all', activityTab === 'pm' ? 'bg-blue-600 border-blue-600 text-white shadow-lg shadow-blue-500/20' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-blue-300']">
                        <CheckCircle2 class="w-5 h-5" /><div class="text-left"><p class="text-sm font-bold">Preventive</p><p class="text-xs opacity-70">{{ recentPm.length }} terbaru</p></div>
                    </button>
                    <button @click="activityTab = 'cm'" :class="['flex items-center gap-2 p-4 rounded-2xl border-2 transition-all', activityTab === 'cm' ? 'bg-orange-500 border-orange-500 text-white shadow-lg shadow-orange-500/20' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-orange-300']">
                        <Wrench class="w-5 h-5" /><div class="text-left"><p class="text-sm font-bold">Corrective</p><p class="text-xs opacity-70">{{ recentCm.length }} terbaru</p></div>
                    </button>
                </div>
                <div v-show="activityTab === 'pm'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2"><CheckCircle2 class="w-4 h-4 text-blue-600" /> Preventive Terbaru</h2>
                        <a href="/dies/preventive" class="text-xs text-blue-500 font-semibold flex items-center gap-1 hover:underline">Semua PM <ChevronRight class="w-3 h-3" /></a>
                    </div>
                    <div v-if="recentPm.length === 0" class="py-12 text-center text-gray-400 text-xs">Belum ada data PM</div>
                    <div v-else class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-for="r in recentPm" :key="r.id" class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors cursor-pointer" @click="router.visit('/dies/preventive')">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center flex-shrink-0 shadow-sm"><CheckCircle2 class="w-5 h-5 text-white" /></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ r.dies?.no_part ?? r.dies_id }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ r.dies?.nama_dies }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">PIC: {{ r.pic_name }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-bold', pmStatusCfg[r.status]?.badge ?? '']">{{ pmStatusCfg[r.status]?.label ?? r.status }}</span>
                                <p class="text-xs text-gray-400 mt-1">{{ fmtDate(r.report_date) }}</p>
                                <p class="text-xs font-bold text-blue-500">{{ r.stroke_at_maintenance.toLocaleString() }} stroke</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-show="activityTab === 'cm'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2"><Wrench class="w-4 h-4 text-orange-500" /> Corrective Terbaru</h2>
                        <a href="/dies/corrective" class="text-xs text-orange-500 font-semibold flex items-center gap-1 hover:underline">Semua CM <ChevronRight class="w-3 h-3" /></a>
                    </div>
                    <div v-if="recentCm.length === 0" class="py-12 text-center text-gray-400 text-xs">Belum ada data CM</div>
                    <div v-else class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-for="r in recentCm" :key="r.id" class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors cursor-pointer" @click="router.visit('/dies/corrective')">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center flex-shrink-0 shadow-sm"><Wrench class="w-5 h-5 text-white" /></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ r.dies?.no_part ?? r.dies_id }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ r.dies?.nama_dies }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">PIC: {{ r.pic_name }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-bold', cmStatusCfg[r.status]?.badge ?? '']">{{ cmStatusCfg[r.status]?.label ?? r.status }}</span>
                                <p class="text-xs text-gray-400 mt-1">{{ fmtDate(r.report_date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ MODAL SCHEDULE PM ══ -->
        <div v-if="showScheduleModal && schedulingDies"
            class="fixed inset-0 backdrop-blur-sm bg-black/40 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-3xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl max-h-[95vh] overflow-y-auto">
                <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <CalendarCheck class="w-5 h-5 text-orange-500" /> Jadwalkan PM
                    </h2>
                    <button @click="closeScheduleModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"><X class="w-4 h-4" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <!-- Dies info -->
                    <div :class="['rounded-xl p-4 border', diesStatusCfg[schedulingDies.dies_status]?.bg, diesStatusCfg[schedulingDies.dies_status]?.border]">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-black text-gray-900 dark:text-white">{{ schedulingDies.no_part }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ schedulingDies.nama_dies }}</p>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0', diesStatusCfg[schedulingDies.dies_status]?.badge]">{{ schedulingDies.dies_status }}</span>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="flex-1 h-2.5 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                    <div :class="['h-full rounded-full', diesStatusCfg[schedulingDies.dies_status]?.bar]" :style="{ width: `${Math.min(schedulingDies.percentage, 100)}%` }"></div>
                                </div>
                                <span :class="['text-sm font-black', diesStatusCfg[schedulingDies.dies_status]?.text]">{{ schedulingDies.percentage }}%</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-500">
                                <span>{{ schedulingDies.current_stroke.toLocaleString() }} / {{ schedulingDies.std_stroke.toLocaleString() }}</span>
                                <span v-if="schedulingDies.days_left !== null" :class="['font-bold', schedulingDies.days_left <= 0 ? 'text-red-500' : 'text-amber-500']">
                                    {{ schedulingDies.days_left <= 0 ? '⚠ OVERDUE' : `${schedulingDies.days_left} hari lagi` }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Forecast mini -->
                    <div class="grid grid-cols-5 gap-1">
                        <div v-for="f in schedulingDies.forecasts" :key="f.day"
                            :class="['rounded-lg p-1.5 text-center border',
                                f.status === 'Diharuskan'  ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' :
                                f.status === 'Disegerakan' ? 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800' :
                                f.status === 'Dijadwalkan' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800' :
                                'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800']">
                            <p class="text-xs text-gray-400 font-semibold">H+{{ f.day }}</p>
                            <p :class="['text-xs font-black', diesStatusCfg[f.status]?.text ?? '']">{{ f.percentage }}%</p>
                        </div>
                    </div>

                    <!-- Proses -->
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Proses <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <button type="button" @click="processOpen = !processOpen"
                                :class="['w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-sm transition-colors dark:bg-gray-700',
                                    scheduleForm.process_id ? 'border-purple-400 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-semibold' : 'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-purple-400']">
                                <span class="flex items-center gap-2">
                                    <Layers class="w-3.5 h-3.5 text-purple-400 flex-shrink-0" />
                                    <span>{{ selectedProcessObj ? selectedProcessObj.process_name : 'Pilih proses...' }}</span>
                                </span>
                                <div class="flex items-center gap-1">
                                    <button v-if="scheduleForm.process_id" type="button" @click.stop="scheduleForm.process_id = null" class="p-0.5 text-gray-400 hover:text-red-500"><X class="w-3.5 h-3.5" /></button>
                                    <ChevronDown :class="['w-3.5 h-3.5 text-gray-400 transition-transform', processOpen ? 'rotate-180' : '']" />
                                </div>
                            </button>
                            <div v-if="processOpen" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                <div v-if="schedulingDies.processes.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada proses</div>
                                <button v-for="pr in schedulingDies.processes" :key="pr.id" type="button"
                                    @mousedown.prevent="scheduleForm.process_id = pr.id; processOpen = false"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors flex items-center justify-between',
                                        scheduleForm.process_id === pr.id ? 'bg-purple-50 dark:bg-purple-900/20' : '']">
                                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ pr.process_name }}</span>
                                    <span v-if="pr.tonase" class="text-xs text-gray-400">{{ pr.tonase }} ton</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="scheduleForm.errors.process_id" class="mt-1 text-xs text-red-500">{{ scheduleForm.errors.process_id }}</p>
                    </div>

                    <!-- PIC -->
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">PIC <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <User class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                            <input v-model="picSearch" type="text" placeholder="Cari nama PIC..." autocomplete="off"
                                @focus="picOpen = true" @blur="closePicDropdown"
                                :class="['w-full pl-8 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors dark:bg-gray-700',
                                    selectedPicObj ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-semibold' : 'border-gray-200 dark:border-gray-600 focus:border-blue-400']" />
                            <button v-if="selectedPicObj" type="button" @click="clearPic" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500"><X class="w-4 h-4" /></button>
                            <div v-if="picOpen" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                                <div v-if="filteredPic.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada PIC terdaftar</div>
                                <button v-for="u in filteredPic" :key="u.id" type="button"
                                    @mousedown.prevent="selectPic(u)"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors flex items-center gap-2',
                                        selectedPicObj?.id === u.id ? 'bg-blue-50 dark:bg-blue-900/20' : '']">
                                    <div class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center flex-shrink-0">
                                        <User class="w-3 h-3 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ u.name }}</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="scheduleForm.errors.pic_id" class="mt-1 text-xs text-red-500">{{ scheduleForm.errors.pic_id }}</p>
                    </div>

                    <!-- Tanggal -->
                    <div>
                        <label class="block text-sm font-semibold mb-1.5 text-gray-700 dark:text-gray-300">Tanggal PM <span class="text-red-500">*</span></label>
                        <input v-model="scheduleForm.scheduled_date" type="date"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 text-sm focus:border-orange-400 focus:outline-none" />
                        <p v-if="schedulingDies.est_mtc_date" class="mt-1 text-xs text-gray-400">
                            Estimasi: <span class="text-orange-500 font-bold">{{ fmtDate(schedulingDies.est_mtc_date) }}</span>
                        </p>
                        <p v-if="scheduleForm.errors.scheduled_date" class="mt-1 text-xs text-red-500">{{ scheduleForm.errors.scheduled_date }}</p>
                    </div>

                    <div class="flex gap-3 pb-safe">
                        <button @click="closeScheduleModal" class="px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm active:scale-95 transition-all">Batal</button>
                        <button @click="submitSchedule" :disabled="scheduleForm.processing || !scheduleForm.process_id || !scheduleForm.scheduled_date || !scheduleForm.pic_id"
                            class="flex-1 py-3 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-xl hover:from-orange-600 hover:to-orange-700 disabled:opacity-50 font-bold text-sm active:scale-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-orange-500/30">
                            <Calendar class="w-4 h-4" />
                            {{ scheduleForm.processing ? 'Menyimpan...' : schedulingDies.has_scheduled ? 'Update Jadwal' : 'Jadwalkan PM' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
