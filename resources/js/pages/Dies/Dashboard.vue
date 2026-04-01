<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import {
    Wrench, AlertTriangle, Clock, CheckCircle2, Activity,
    ChevronRight, BarChart2, RefreshCw, Download,
    CalendarCheck, X, Calendar, Shield, AlertCircle,
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
interface CmRankItem {
    dies_id: string; no_part: string; nama_dies: string; line: string;
    process_id: number | null; process_name: string; tonase: number | null;
    cm_count: number; last_cm_date: string | null;
}
interface Props {
    summary: { total_dies: number; total_active: number; overdue: number; due_soon: number; corrective_open: number };
    dueList: DueItem[];
    byLine: ByLine[];
    recentPm: RecentRecord[];
    recentCm: RecentRecord[];
    strokeTrend: StrokeTrend[];
    picList: PicUser[];
    cmRanking: CmRankItem[];
    cmPeriod: string;
}

const props = defineProps<Props>();

const activeTab         = ref<'overview' | 'schedule' | 'activity'>('overview');
const expandedDies      = ref<string | null>(null);
const showScheduleModal = ref(false);
const schedulingDies    = ref<DueItem | null>(null);
const activityTab       = ref<'pm' | 'cm'>('pm');

const cmPeriodFilter  = ref(props.cmPeriod ?? '6');
const cmPeriodOptions = [
    { value: '3',   label: '3 Bln' },
    { value: '6',   label: '6 Bln' },
    { value: '12',  label: '1 Thn' },
    { value: 'all', label: 'Semua' },
];
const applyCmFilter = () => {
    router.get('/dies/dashboard', { cm_period: cmPeriodFilter.value }, { preserveState: true, preserveScroll: true });
};

const toggleExpand = (id: string) => { expandedDies.value = expandedDies.value === id ? null : id; };

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

const isDark    = () => document.documentElement.classList.contains('dark');
const gridColor = () => isDark() ? '#1f2937' : '#f1f5f9';
const textColor = () => isDark() ? '#6b7280' : '#9ca3af';

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
                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 180);
                    gradient.addColorStop(0, 'rgba(99,102,241,0.85)');
                    gradient.addColorStop(1, 'rgba(99,102,241,0.15)');
                    return gradient;
                },
                borderColor: 'rgba(99,102,241,0.6)',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 500 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark() ? '#111827' : '#1f2937',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: { label: (ctx) => `  PM: ${ctx.parsed.y}x` },
                },
            },
            scales: {
                x: { ticks: { color: textColor(), font: { size: 10 } }, grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, ticks: { color: textColor(), font: { size: 10 }, stepSize: 1 }, grid: { color: gridColor() }, border: { display: false } },
            },
        },
    });
};
const rebuildTrendChart = () => { trendChart?.destroy(); trendChart = null; nextTick(createTrendChart); };

const cmBarCanvas = ref<HTMLCanvasElement | null>(null);
let cmBarChart: Chart | null = null;

const createCmBarChart = () => {
    if (!cmBarCanvas.value || props.cmRanking.length === 0) return;
    const labels = props.cmRanking.map(r => r.no_part);
    const data   = props.cmRanking.map(r => r.cm_count);
    const maxVal = Math.max(...data, 1);

    cmBarChart = new Chart(cmBarCanvas.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Jumlah CM',
                data,
                backgroundColor: data.map((v) => {
                    const ratio = v / maxVal;
                    if (ratio >= 0.8) return 'rgba(239,68,68,0.8)';
                    if (ratio >= 0.5) return 'rgba(249,115,22,0.8)';
                    return 'rgba(251,191,36,0.8)';
                }),
                borderRadius: 5,
                borderSkipped: false,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark() ? '#111827' : '#1f2937',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        title: (items) => {
                            const r = props.cmRanking[items[0].dataIndex];
                            return r ? `${r.no_part} — ${r.nama_dies}` : '';
                        },
                        label: (ctx) => {
                            const r = props.cmRanking[ctx.dataIndex];
                            return [
                                `  CM: ${ctx.parsed.x}x`,
                                `  Proses: ${r?.process_name ?? '-'}`,
                                `  Line: ${r?.line ?? '-'}`,
                                r?.last_cm_date ? `  Last: ${r.last_cm_date}` : '',
                            ].filter(Boolean);
                        },
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { color: textColor(), font: { size: 10 }, stepSize: 1 },
                    grid: { color: gridColor() },
                    border: { display: false },
                },
                y: {
                    ticks: { color: textColor(), font: { size: 10 } },
                    grid: { display: false },
                    border: { display: false },
                },
            },
        },
    });
};

const rebuildCmChart = () => { cmBarChart?.destroy(); cmBarChart = null; nextTick(createCmBarChart); };

watch(() => props.cmRanking, () => rebuildCmChart());

onMounted(async () => {
    await nextTick();
    createTrendChart();
    createCmBarChart();
    let lastDark = isDark();
    const obs = new MutationObserver(() => {
        const nowDark = isDark();
        if (nowDark !== lastDark) {
            lastDark = nowDark;
            rebuildTrendChart();
            rebuildCmChart();
        }
    });
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    onUnmounted(() => obs.disconnect());
});

onUnmounted(() => { trendChart?.destroy(); cmBarChart?.destroy(); });

const diesStatusCfg: Record<string, { badge: string; bar: string; text: string; bg: string; border: string }> = {
    'Siap Pakai':  { badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', bar: 'bg-emerald-500', text: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-900/10',  border: 'border-emerald-200 dark:border-emerald-800/50' },
    'Dijadwalkan': { badge: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',     bar: 'bg-yellow-400',  text: 'text-yellow-600 dark:text-yellow-400',  bg: 'bg-yellow-50 dark:bg-yellow-900/10',    border: 'border-yellow-200 dark:border-yellow-800/50' },
    'Disegerakan': { badge: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',     bar: 'bg-orange-500',  text: 'text-orange-600 dark:text-orange-400',  bg: 'bg-orange-50 dark:bg-orange-900/10',    border: 'border-orange-200 dark:border-orange-800/50' },
    'Diharuskan':  { badge: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',                 bar: 'bg-red-500',     text: 'text-red-600 dark:text-red-400',        bg: 'bg-red-50 dark:bg-red-900/10',          border: 'border-red-200 dark:border-red-800/50' },
};
const statusMtcCfg: Record<string, { badge: string }> = {
    'OK MTC':      { badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
    'Prepare MTC': { badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
    'DELAY MTC':   { badge: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
};
const pmStatusCfg: Record<string, { label: string; badge: string }> = {
    scheduled:   { label: 'Scheduled',   badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    pending:     { label: 'Pending',     badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
    completed:   { label: 'Completed',   badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
};
const cmStatusCfg: Record<string, { label: string; badge: string }> = {
    open:        { label: 'Open',        badge: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    in_progress: { label: 'In Progress', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
    closed:      { label: 'Closed',      badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
};

const fmtDate = (d: string | null) =>
    !d ? '-' : new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const dueByStatus = computed(() => {
    const groups: Record<string, DueItem[]> = { 'Diharuskan': [], 'Disegerakan': [], 'Dijadwalkan': [], 'Siap Pakai': [] };
    props.dueList.forEach(d => { if (groups[d.dies_status]) groups[d.dies_status].push(d); });
    return groups;
});
const normalCount    = computed(() => Math.max(0, props.summary.total_active - props.summary.overdue - props.summary.due_soon));
const cmChartHeight  = computed(() => Math.max(props.cmRanking.length * 36 + 16, 140));

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
        [], ['CM RANKING'],
        ['Rank', 'No Part', 'Nama Dies', 'Line', 'Proses', 'Tonase', 'Jumlah CM', 'Last CM'],
        ...props.cmRanking.map((r, i) => [
            i + 1, r.no_part, r.nama_dies, r.line, r.process_name, r.tonase ?? '-', r.cm_count, r.last_cm_date ?? '-',
        ]),
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = Array(12).fill({ wch: 18 });
    XLSX.utils.book_append_sheet(wb, ws, 'Dashboard');
    XLSX.writeFile(wb, `Dies_Dashboard_${new Date().toISOString().slice(0, 10)}.xlsx`);
};
</script>

<template>
    <Head title="Dies Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'Dies', href: '/dies/dashboard' }, { title: 'Dashboard', href: '/dies/dashboard' }]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-5">

            <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 rounded-2xl shadow-lg overflow-hidden">
                <div class="px-4 pt-4 pb-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="bg-white/15 p-2 rounded-xl backdrop-blur-sm shrink-0">
                            <BarChart2 class="w-4 h-4 text-white" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-base font-bold text-white leading-tight">Dies Dashboard</h1>
                            <p class="text-white/60 text-xs">Monitoring PM & CM dies</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="exportExcel"
                            class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 backdrop-blur-sm text-white text-xs font-semibold px-3 py-2 rounded-xl border border-white/20 transition-all active:scale-95">
                            <Download class="w-3.5 h-3.5" /><span class="hidden sm:inline">Export</span>
                        </button>
                        <button @click="router.reload()"
                            class="bg-white/10 hover:bg-white/20 p-2 rounded-xl border border-white/20 transition-all active:scale-95">
                            <RefreshCw class="w-3.5 h-3.5 text-white" />
                        </button>
                    </div>
                </div>

                <div class="px-4 pb-4">
                    <div class="grid grid-cols-5 gap-2">
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 border border-white/10">
                            <p class="text-white/60 text-xs flex items-center gap-1"><Activity class="w-3 h-3" /> Total</p>
                            <p class="text-2xl font-black text-white mt-1 tabular-nums">{{ summary.total_dies }}</p>
                            <p class="text-white/50 text-xs mt-0.5">{{ summary.total_active }} aktif</p>
                        </div>
                        <div class="bg-red-500/30 backdrop-blur-sm rounded-xl p-3 border border-red-400/30">
                            <p class="text-red-200 text-xs flex items-center gap-1"><AlertTriangle class="w-3 h-3" /> Overdue</p>
                            <p class="text-2xl font-black text-white mt-1 tabular-nums">{{ summary.overdue }}</p>
                            <p class="text-red-200/70 text-xs mt-0.5">Segera PM</p>
                        </div>
                        <div class="bg-orange-500/25 backdrop-blur-sm rounded-xl p-3 border border-orange-400/30">
                            <p class="text-orange-200 text-xs flex items-center gap-1"><Clock class="w-3 h-3" /> Due Soon</p>
                            <p class="text-2xl font-black text-white mt-1 tabular-nums">{{ summary.due_soon }}</p>
                            <p class="text-orange-200/70 text-xs mt-0.5">85–100%</p>
                        </div>
                        <div class="bg-purple-500/25 backdrop-blur-sm rounded-xl p-3 border border-purple-400/30">
                            <p class="text-purple-200 text-xs flex items-center gap-1"><Wrench class="w-3 h-3" /> CM Open</p>
                            <p class="text-2xl font-black text-white mt-1 tabular-nums">{{ summary.corrective_open }}</p>
                            <p class="text-purple-200/70 text-xs mt-0.5">Pending</p>
                        </div>
                        <div class="bg-emerald-500/25 backdrop-blur-sm rounded-xl p-3 border border-emerald-400/30">
                            <p class="text-emerald-200 text-xs flex items-center gap-1"><CheckCircle2 class="w-3 h-3" /> Normal</p>
                            <p class="text-2xl font-black text-white mt-1 tabular-nums">{{ normalCount }}</p>
                            <p class="text-emerald-200/70 text-xs mt-0.5">Siap pakai</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div v-for="[status, cfg] in Object.entries(diesStatusCfg)" :key="status"
                    :class="['flex items-center gap-3 p-3 rounded-xl border', cfg.bg, cfg.border]">
                    <div :class="['w-2 h-2 rounded-full flex-shrink-0', cfg.bar]"></div>
                    <div class="min-w-0 flex-1">
                        <p :class="['text-xs font-semibold', cfg.text]">{{ status }}</p>
                        <p class="text-xs text-gray-400">
                            {{ status === 'Siap Pakai' ? '< 75%' : status === 'Dijadwalkan' ? '75–85%' : status === 'Disegerakan' ? '85–100%' : '≥ 100%' }}
                        </p>
                    </div>
                    <p :class="['text-lg font-bold tabular-nums', cfg.text]">{{ dueList.filter(d => d.dies_status === status).length }}</p>
                </div>
            </div>

            <div class="flex gap-1 bg-gray-100 dark:bg-gray-800/80 p-1 rounded-xl">
                <button v-for="tab in [
                    { v: 'overview', l: 'Overview', icon: BarChart2 },
                    { v: 'schedule', l: 'Schedule PM', icon: CalendarCheck },
                    { v: 'activity', l: 'Aktivitas', icon: Activity },
                ]" :key="tab.v" @click="activeTab = tab.v as any"
                    :class="['flex-1 flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-semibold transition-all',
                        activeTab === tab.v
                            ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                            : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300']">
                    <component :is="tab.icon" class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">{{ tab.l }}</span>
                    <span class="sm:hidden">{{ tab.l.split(' ')[0] }}</span>
                </button>
            </div>

            <div v-show="activeTab === 'overview'" class="space-y-4">

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-3 flex-wrap">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                            <Wrench class="w-4 h-4 text-orange-400" /> Dies Paling Sering CM
                        </h2>
                        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5">
                            <button
                                v-for="opt in cmPeriodOptions" :key="opt.value"
                                @click="cmPeriodFilter = opt.value; applyCmFilter()"
                                :class="['px-2.5 py-1 rounded-md text-xs font-medium transition-all',
                                    cmPeriodFilter === opt.value
                                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm'
                                        : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300']">
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div v-if="cmRanking.length === 0" class="py-10 text-center">
                            <Shield class="w-8 h-8 mx-auto mb-2 text-gray-200 dark:text-gray-600" />
                            <p class="text-xs text-gray-400">Tidak ada data CM pada periode ini</p>
                        </div>
                        <div v-else :style="{ height: cmChartHeight + 'px', position: 'relative' }">
                            <canvas ref="cmBarCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                            <AlertCircle class="w-4 h-4 text-red-400" /> Dies Kritis
                        </h2>
                        <button @click="activeTab = 'schedule'" class="text-xs text-blue-500 font-medium flex items-center gap-0.5 hover:underline">
                            Semua <ChevronRight class="w-3 h-3" />
                        </button>
                    </div>
                    <div class="divide-y divide-gray-50 dark:divide-gray-700/40">
                        <div v-if="dueList.filter(d => d.dies_status === 'Diharuskan' || d.dies_status === 'Disegerakan').length === 0"
                            class="py-10 text-center">
                            <Shield class="w-8 h-8 mx-auto mb-2 text-gray-200 dark:text-gray-600" />
                            <p class="text-xs text-gray-400">Semua dies kondisi baik</p>
                        </div>
                        <div v-for="d in dueList.filter(d => d.dies_status === 'Diharuskan' || d.dies_status === 'Disegerakan').slice(0, 5)"
                            :key="d.id_sap"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors">
                            <div :class="['w-1 h-10 rounded-full flex-shrink-0', diesStatusCfg[d.dies_status]?.bar]"></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ d.no_part }}</p>
                                    <span class="text-xs text-gray-400">{{ d.line }}</span>
                                </div>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <div class="flex-1 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div :class="['h-full rounded-full', diesStatusCfg[d.dies_status]?.bar]"
                                            :style="{ width: `${Math.min(d.percentage, 100)}%` }"></div>
                                    </div>
                                    <span :class="['text-xs font-bold tabular-nums', diesStatusCfg[d.dies_status]?.text]">{{ d.percentage }}%</span>
                                </div>
                            </div>
                            <button @click="openSchedule(d)"
                                :class="['flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all active:scale-95 flex-shrink-0',
                                    d.has_scheduled
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400'
                                        : 'bg-orange-500 hover:bg-orange-600 text-white']">
                                <CalendarCheck class="w-3 h-3" />
                                {{ d.has_scheduled ? 'Ubah' : 'Jadwal' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-show="activeTab === 'schedule'" class="space-y-3">
                <div class="flex gap-2 overflow-x-auto scrollbar-none pb-0.5">
                    <div v-for="[status, cfg] in Object.entries(diesStatusCfg)" :key="status"
                        :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-medium flex-shrink-0', cfg.bg, cfg.border, cfg.text]">
                        <div :class="['w-1.5 h-1.5 rounded-full', cfg.bar]"></div>
                        {{ status }}
                        <span class="font-bold">{{ dueList.filter(d => d.dies_status === status).length }}</span>
                    </div>
                </div>

                <template v-for="[status, items] in Object.entries(dueByStatus)" :key="status">
                    <div v-if="items.length > 0" class="space-y-2">
                        <p :class="['text-xs font-semibold px-1', diesStatusCfg[status]?.text]">
                            {{ status }} — {{ items.length }} dies
                        </p>
                        <div v-for="d in items" :key="d.id_sap"
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="flex">
                                <div :class="['w-1 flex-shrink-0', diesStatusCfg[d.dies_status]?.bar]"></div>
                                <div class="flex-1 p-3.5 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ d.no_part }}</p>
                                                <span class="text-xs text-gray-400 font-medium">{{ d.line }}</span>
                                                <span v-if="d.has_scheduled"
                                                    class="px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/20 text-blue-500 rounded text-xs font-medium flex items-center gap-0.5">
                                                    <CalendarCheck class="w-3 h-3" /> Terjadwal
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ d.nama_dies }}</p>
                                            <div v-if="d.has_scheduled" class="mt-1 flex items-center gap-3 flex-wrap">
                                                <span v-if="d.scheduled_process_id" class="flex items-center gap-1 text-xs text-purple-500 dark:text-purple-400">
                                                    <Layers class="w-3 h-3" /> {{ d.processes.find(p => p.id === d.scheduled_process_id)?.process_name }}
                                                </span>
                                                <span v-if="d.scheduled_pic_name" class="flex items-center gap-1 text-xs text-gray-400">
                                                    <User class="w-3 h-3" /> {{ d.scheduled_pic_name }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex gap-1.5 flex-shrink-0">
                                            <button @click="router.visit(`/dies/${d.id_sap}`)"
                                                class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-400 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                                <Eye class="w-3.5 h-3.5" />
                                            </button>
                                            <button @click="openSchedule(d)"
                                                :class="['flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all active:scale-95',
                                                    d.has_scheduled
                                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-500'
                                                        : 'bg-orange-500 hover:bg-orange-600 text-white']">
                                                <CalendarCheck class="w-3 h-3" />
                                                {{ d.has_scheduled ? 'Ubah' : 'Jadwalkan' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-3 space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                                <div :class="['h-full rounded-full', diesStatusCfg[d.dies_status]?.bar]"
                                                    :style="{ width: `${Math.min(d.percentage, 100)}%` }"></div>
                                            </div>
                                            <span :class="['text-sm font-bold w-10 text-right tabular-nums', diesStatusCfg[d.dies_status]?.text]">
                                                {{ d.percentage }}%
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                                            <span class="tabular-nums">{{ d.current_stroke.toLocaleString() }} / {{ d.std_stroke.toLocaleString() }}</span>
                                            <span>Last: <span class="text-gray-600 dark:text-gray-300">{{ fmtDate(d.last_mtc_date) }}</span></span>
                                            <span v-if="d.est_mtc_date" :class="['font-medium', d.is_overdue ? 'text-red-500' : 'text-orange-500']">
                                                Est. {{ fmtDate(d.est_mtc_date) }}
                                            </span>
                                            <span v-if="d.days_left !== null"
                                                :class="['font-bold', d.days_left <= 0 ? 'text-red-500' : d.days_left <= 3 ? 'text-orange-500' : 'text-amber-500']">
                                                {{ d.days_left <= 0 ? '⚠ OVERDUE' : `${d.days_left}h lagi` }}
                                            </span>
                                            <span :class="['px-1.5 py-0.5 rounded text-xs', statusMtcCfg[d.status_mtc]?.badge]">{{ d.status_mtc }}</span>
                                        </div>
                                    </div>

                                    <button @click="toggleExpand(d.id_sap)"
                                        class="mt-2.5 flex items-center gap-1 text-xs text-gray-400 hover:text-blue-500 transition-colors">
                                        <component :is="expandedDies === d.id_sap ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" />
                                        Forecast H+1 s/d H+5
                                    </button>

                                    <div v-if="expandedDies === d.id_sap" class="mt-2 grid grid-cols-5 gap-1.5">
                                        <div v-for="f in d.forecasts" :key="f.day"
                                            :class="['rounded-lg p-2 text-center border',
                                                f.status === 'Diharuskan'  ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800/50' :
                                                f.status === 'Disegerakan' ? 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800/50' :
                                                f.status === 'Dijadwalkan' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800/50' :
                                                'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/50']">
                                            <p class="text-xs text-gray-400">H+{{ f.day }}</p>
                                            <p :class="['text-sm font-bold mt-0.5', diesStatusCfg[f.status]?.text ?? 'text-gray-600']">{{ f.percentage }}%</p>
                                            <p class="text-xs text-gray-400 mt-0.5 tabular-nums">{{ f.stroke.toLocaleString() }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="dueList.length === 0"
                    class="py-16 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                    <Shield class="w-10 h-10 mx-auto mb-3 text-gray-200 dark:text-gray-600" />
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Semua dies dalam kondisi aman</p>
                    <p class="text-xs text-gray-400 mt-1">Tidak ada dies yang perlu perhatian</p>
                </div>
            </div>

            <div v-show="activeTab === 'activity'" class="space-y-4">
                <div class="grid grid-cols-2 gap-2">
                    <button @click="activityTab = 'pm'"
                        :class="['flex items-center gap-2.5 p-3.5 rounded-xl border-2 transition-all text-left',
                            activityTab === 'pm'
                                ? 'border-indigo-500 bg-indigo-500 text-white'
                                : 'border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-indigo-200']">
                        <CheckCircle2 class="w-4 h-4 flex-shrink-0" />
                        <div>
                            <p class="text-sm font-semibold">Preventive</p>
                            <p class="text-xs opacity-60">{{ recentPm.length }} terbaru</p>
                        </div>
                    </button>
                    <button @click="activityTab = 'cm'"
                        :class="['flex items-center gap-2.5 p-3.5 rounded-xl border-2 transition-all text-left',
                            activityTab === 'cm'
                                ? 'border-orange-500 bg-orange-500 text-white'
                                : 'border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-orange-200']">
                        <Wrench class="w-4 h-4 flex-shrink-0" />
                        <div>
                            <p class="text-sm font-semibold">Corrective</p>
                            <p class="text-xs opacity-60">{{ recentCm.length }} terbaru</p>
                        </div>
                    </button>
                </div>

                <div v-show="activityTab === 'pm'"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white">Preventive Terbaru</h2>
                        <a href="/dies/preventive" class="text-xs text-blue-500 font-medium flex items-center gap-0.5 hover:underline">
                            Semua <ChevronRight class="w-3 h-3" />
                        </a>
                    </div>
                    <div v-if="recentPm.length === 0" class="py-12 text-center text-xs text-gray-400">Belum ada data PM</div>
                    <div v-else class="divide-y divide-gray-50 dark:divide-gray-700/40">
                        <div v-for="r in recentPm" :key="r.id"
                            class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors cursor-pointer"
                            @click="router.visit('/dies/preventive')">
                            <div class="w-8 h-8 rounded-lg bg-indigo-500/10 dark:bg-indigo-500/20 flex items-center justify-center flex-shrink-0">
                                <CheckCircle2 class="w-4 h-4 text-indigo-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ r.dies?.no_part ?? r.dies_id }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ r.dies?.nama_dies }}</p>
                                <p class="text-xs text-gray-400">PIC: {{ r.pic_name }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-medium', pmStatusCfg[r.status]?.badge ?? '']">
                                    {{ pmStatusCfg[r.status]?.label ?? r.status }}
                                </span>
                                <p class="text-xs text-gray-400 mt-1">{{ fmtDate(r.report_date) }}</p>
                                <p class="text-xs font-semibold text-indigo-500 tabular-nums">{{ r.stroke_at_maintenance.toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="activityTab === 'cm'"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white">Corrective Terbaru</h2>
                        <a href="/dies/corrective" class="text-xs text-orange-500 font-medium flex items-center gap-0.5 hover:underline">
                            Semua <ChevronRight class="w-3 h-3" />
                        </a>
                    </div>
                    <div v-if="recentCm.length === 0" class="py-12 text-center text-xs text-gray-400">Belum ada data CM</div>
                    <div v-else class="divide-y divide-gray-50 dark:divide-gray-700/40">
                        <div v-for="r in recentCm" :key="r.id"
                            class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors cursor-pointer"
                            @click="router.visit('/dies/corrective')">
                            <div class="w-8 h-8 rounded-lg bg-orange-500/10 dark:bg-orange-500/20 flex items-center justify-center flex-shrink-0">
                                <Wrench class="w-4 h-4 text-orange-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ r.dies?.no_part ?? r.dies_id }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ r.dies?.nama_dies }}</p>
                                <p class="text-xs text-gray-400">PIC: {{ r.pic_name }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-medium', cmStatusCfg[r.status]?.badge ?? '']">
                                    {{ cmStatusCfg[r.status]?.label ?? r.status }}
                                </span>
                                <p class="text-xs text-gray-400 mt-1">{{ fmtDate(r.report_date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showScheduleModal && schedulingDies"
            class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl w-full sm:max-w-sm shadow-2xl max-h-[95vh] overflow-y-auto">
                <div class="w-8 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1 sm:hidden"></div>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <CalendarCheck class="w-4 h-4 text-orange-500" /> Jadwalkan PM
                    </h2>
                    <button @click="closeScheduleModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <X class="w-4 h-4 text-gray-400" />
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div :class="['rounded-xl p-4 border', diesStatusCfg[schedulingDies.dies_status]?.bg, diesStatusCfg[schedulingDies.dies_status]?.border]">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ schedulingDies.no_part }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ schedulingDies.nama_dies }}</p>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0', diesStatusCfg[schedulingDies.dies_status]?.badge]">
                                {{ schedulingDies.dies_status }}
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center gap-2 mb-1.5">
                                <div class="flex-1 h-2 bg-black/10 dark:bg-white/10 rounded-full overflow-hidden">
                                    <div :class="['h-full rounded-full', diesStatusCfg[schedulingDies.dies_status]?.bar]"
                                        :style="{ width: `${Math.min(schedulingDies.percentage, 100)}%` }"></div>
                                </div>
                                <span :class="['text-sm font-bold tabular-nums', diesStatusCfg[schedulingDies.dies_status]?.text]">
                                    {{ schedulingDies.percentage }}%
                                </span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-400">
                                <span class="tabular-nums">{{ schedulingDies.current_stroke.toLocaleString() }} / {{ schedulingDies.std_stroke.toLocaleString() }}</span>
                                <span v-if="schedulingDies.days_left !== null"
                                    :class="['font-semibold', schedulingDies.days_left <= 0 ? 'text-red-500' : 'text-amber-500']">
                                    {{ schedulingDies.days_left <= 0 ? '⚠ OVERDUE' : `${schedulingDies.days_left} hari lagi` }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-5 gap-1">
                        <div v-for="f in schedulingDies.forecasts" :key="f.day"
                            :class="['rounded-lg p-1.5 text-center border',
                                f.status === 'Diharuskan'  ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800/50' :
                                f.status === 'Disegerakan' ? 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800/50' :
                                f.status === 'Dijadwalkan' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800/50' :
                                'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/50']">
                            <p class="text-xs text-gray-400">H+{{ f.day }}</p>
                            <p :class="['text-xs font-bold', diesStatusCfg[f.status]?.text ?? '']">{{ f.percentage }}%</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-600 dark:text-gray-400">Proses <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <button type="button" @click="processOpen = !processOpen"
                                :class="['w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-sm transition-colors',
                                    scheduleForm.process_id
                                        ? 'border-purple-300 dark:border-purple-700 bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300'
                                        : 'border-gray-200 dark:border-gray-600 text-gray-400 dark:bg-gray-700 hover:border-gray-300']">
                                <span class="flex items-center gap-2">
                                    <Layers class="w-3.5 h-3.5 text-purple-400 flex-shrink-0" />
                                    <span class="text-sm">{{ selectedProcessObj ? selectedProcessObj.process_name : 'Pilih proses...' }}</span>
                                </span>
                                <div class="flex items-center gap-1">
                                    <button v-if="scheduleForm.process_id" type="button" @click.stop="scheduleForm.process_id = null"
                                        class="p-0.5 text-gray-400 hover:text-red-400">
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                    <ChevronDown :class="['w-3.5 h-3.5 text-gray-400 transition-transform', processOpen ? 'rotate-180' : '']" />
                                </div>
                            </button>
                            <div v-if="processOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                <div v-if="schedulingDies.processes.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada proses</div>
                                <button v-for="pr in schedulingDies.processes" :key="pr.id" type="button"
                                    @mousedown.prevent="scheduleForm.process_id = pr.id; processOpen = false"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors flex items-center justify-between',
                                        scheduleForm.process_id === pr.id ? 'bg-purple-50 dark:bg-purple-900/20' : '']">
                                    <span class="text-xs font-medium text-gray-800 dark:text-gray-200">{{ pr.process_name }}</span>
                                    <span v-if="pr.tonase" class="text-xs text-gray-400">{{ pr.tonase }} ton</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="scheduleForm.errors.process_id" class="mt-1 text-xs text-red-500">{{ scheduleForm.errors.process_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-600 dark:text-gray-400">PIC <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <User class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none z-10" />
                            <input v-model="picSearch" type="text" placeholder="Cari PIC..." autocomplete="off"
                                @focus="picOpen = true" @blur="closePicDropdown"
                                :class="['w-full pl-9 pr-8 py-2.5 border rounded-xl text-sm focus:outline-none transition-colors',
                                    selectedPicObj
                                        ? 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                        : 'border-gray-200 dark:border-gray-600 dark:bg-gray-700 focus:border-gray-300 dark:text-gray-200']" />
                            <button v-if="selectedPicObj" type="button" @click="clearPic"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-400">
                                <X class="w-4 h-4" />
                            </button>
                            <div v-if="picOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                <div v-if="filteredPic.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">Tidak ada PIC</div>
                                <button v-for="u in filteredPic" :key="u.id" type="button"
                                    @mousedown.prevent="selectPic(u)"
                                    :class="['w-full text-left px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2',
                                        selectedPicObj?.id === u.id ? 'bg-gray-50 dark:bg-gray-700' : '']">
                                    <span class="text-xs font-medium text-gray-800 dark:text-gray-200">{{ u.name }}</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="scheduleForm.errors.pic_id" class="mt-1 text-xs text-red-500">{{ scheduleForm.errors.pic_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1.5 text-gray-600 dark:text-gray-400">Tanggal PM <span class="text-red-500">*</span></label>
                        <input v-model="scheduleForm.scheduled_date" type="date"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-gray-200 text-sm focus:border-orange-400 focus:outline-none transition-colors" />
                        <p v-if="schedulingDies.est_mtc_date" class="mt-1 text-xs text-gray-400">
                            Est: <span class="text-orange-500 font-medium">{{ fmtDate(schedulingDies.est_mtc_date) }}</span>
                        </p>
                        <p v-if="scheduleForm.errors.scheduled_date" class="mt-1 text-xs text-red-500">{{ scheduleForm.errors.scheduled_date }}</p>
                    </div>

                    <div class="flex gap-2 pb-safe">
                        <button @click="closeScheduleModal"
                            class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-sm font-medium active:scale-95 transition-all">
                            Batal
                        </button>
                        <button @click="submitSchedule"
                            :disabled="scheduleForm.processing || !scheduleForm.process_id || !scheduleForm.scheduled_date || !scheduleForm.pic_id"
                            class="flex-1 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-40 text-white rounded-xl font-semibold text-sm active:scale-95 transition-all flex items-center justify-center gap-2">
                            <Calendar class="w-4 h-4" />
                            {{ scheduleForm.processing ? 'Menyimpan...' : schedulingDies.has_scheduled ? 'Update Jadwal' : 'Jadwalkan PM' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
