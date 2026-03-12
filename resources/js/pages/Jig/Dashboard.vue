<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Wrench, AlertTriangle, CheckCircle2, Clock, ChevronRight, Calendar, User, Activity, Download, X, ChevronLeft, TrendingUp } from 'lucide-vue-next';
import Chart from 'chart.js/auto';
import * as XLSX from 'xlsx';

interface PicPerf {
    id: number; name: string; total: number; done: number; late: number;
    pending: number; cm_active: number; cm_total: number;
    imp_active: number; imp_total: number;
    completion_rate: number;
    monthly: { label: string; done: number; late: number; pending: number }[];
}
interface TrendItem    { bulan: number; label: string; total: number; done: number; late: number; pending: number; }
interface CmTrendItem  { bulan: number; label: string; open: number; in_progress: number; closed: number; total: number; }
interface ImpTrendItem { bulan: number; label: string; open: number; in_progress: number; closed: number; total: number; }

interface Props {
    pmSummary:      { total: number; done: number; late: number; pending: number };
    cmSummary:      { open: number; in_progress: number; closed: number };
    impSummary:     { open: number; in_progress: number; closed: number };
    recentCm:       any[];
    recentImp:      any[];
    upcomingPm:     any[];
    completionRate: number;
    picPerformance: PicPerf[];
    pmTrend:        TrendItem[];
    cmTrend:        CmTrendItem[];
    impTrend:       ImpTrendItem[];
    bulan: any; tahun: number; isPic: boolean;
    pics: { id: number; name: string }[];
    filters: { bulan?: any; tahun?: any; pic_id?: any; minggu?: any };
}

const props = defineProps<Props>();
const activeTab    = ref<'pm' | 'cm' | 'improvement' | 'performance'>('pm');
const filterBulan  = ref(props.filters.bulan  ?? props.bulan);
const filterTahun  = ref(props.filters.tahun  ?? props.tahun);
const filterPic    = ref(props.filters.pic_id ?? '');
const filterMinggu = ref(props.filters.minggu ?? '');

const navigate = () => {
    router.visit('/jig/dashboard', {
        method: 'get',
        data: { bulan: filterBulan.value, tahun: filterTahun.value, pic_id: filterPic.value, minggu: filterMinggu.value },
        only: ['pmSummary', 'cmSummary', 'impSummary', 'completionRate', 'picPerformance', 'pmTrend', 'cmTrend', 'impTrend', 'upcomingPm', 'recentCm', 'recentImp', 'filters'],
        preserveState: true, preserveScroll: true, replace: true,
    });
};

const BULAN      = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const BULAN_LIST = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'].map((l,i) => ({ val: i+1, label: l }));
const MINGGU_LIST = [1,2,3,4,5].map(w => ({ val: w, label: `W${w}` }));

const periodeLabel = computed(() => {
    const bulanStr = filterBulan.value === 'all' ? `Tahun ${filterTahun.value}` : `${BULAN[filterBulan.value]} ${filterTahun.value}`;
    return filterMinggu.value ? `${bulanStr} — Week ${filterMinggu.value}` : bulanStr;
});

const formatDate = (d: string|null) => {
    if (!d) return '-';
    const dt = new Date(d);
    return isNaN(dt.getTime()) ? '-' : dt.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
};
const formatWeek = (s: string|null, e: string|null) => {
    if (!s || !e) return '-';
    const sd = new Date(s), ed = new Date(e);
    return isNaN(sd.getTime()) ? '-' :
        `${sd.toLocaleDateString('id-ID',{day:'2-digit',month:'short'})} – ${ed.toLocaleDateString('id-ID',{day:'2-digit',month:'short'})}`;
};

const cmStatusColor: Record<string,string> = {
    open:'bg-red-100 text-red-700', in_progress:'bg-yellow-100 text-yellow-700', closed:'bg-green-100 text-green-700'
};
const cmStatusLabel: Record<string,string> = { open:'Open', in_progress:'In Progress', closed:'Closed' };
const rateColor = (r: number) => r >= 80 ? 'text-green-600' : r >= 50 ? 'text-yellow-600' : 'text-red-600';

const showExportModal = ref(false);
const exportType = ref<'pm' | 'cm' | 'improvement' | 'both'>('both');

const exportToExcel = () => {
    const wb = XLSX.utils.book_new();
    const periode  = periodeLabel.value;
    const picLabel = filterPic.value ? (props.pics.find(p => p.id == filterPic.value)?.name ?? '') : 'Semua PIC';

    if (exportType.value === 'pm' || exportType.value === 'both') {
        const summaryRows: any[][] = [
            ['Laporan Preventive Maintenance'], ['Periode', periode], ['PIC', picLabel], [],
            ['RINGKASAN'], ['Total Jadwal', 'Selesai', 'Pending', 'Terlambat', 'Completion Rate'],
            [props.pmSummary.total, props.pmSummary.done, props.pmSummary.pending, props.pmSummary.late, `${props.completionRate}%`],
            [], ['TREND BULANAN'], ['Bulan', 'Total', 'Selesai', 'Terlambat', 'Pending'],
            ...props.pmTrend.map(t => [t.label, t.total, t.done, t.late, t.pending]),
        ];
        if (!props.isPic && props.picPerformance.length > 0) {
            summaryRows.push(
                [], ['PERFORMA PER PIC'],
                ['Nama PIC', 'Total PM', 'Selesai', 'Terlambat', 'Pending', 'Completion Rate', 'Total CM', 'Total Improvement'],
                ...props.picPerformance.map(p => [p.name, p.total, p.done, p.late, p.pending, `${p.completion_rate}%`, p.cm_total, p.imp_total])
            );
        }
        const wsPm = XLSX.utils.aoa_to_sheet(summaryRows);
        wsPm['!cols'] = [{ wch: 20 }, { wch: 12 }, { wch: 12 }, { wch: 12 }, { wch: 16 }, { wch: 14 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, wsPm, 'Preventive');
    }

    if (exportType.value === 'cm' || exportType.value === 'both') {
        const cmTotal = props.cmSummary.open + props.cmSummary.in_progress + props.cmSummary.closed;
        const cmRows: any[][] = [
            ['Laporan Corrective Maintenance'], ['Periode', periode], ['PIC', picLabel], [],
            ['RINGKASAN'], ['Open', 'In Progress', 'Closed', 'Total'],
            [props.cmSummary.open, props.cmSummary.in_progress, props.cmSummary.closed, cmTotal],
            [], ['TREND BULANAN'], ['Bulan', 'Total', 'Open', 'In Progress', 'Closed'],
            ...props.cmTrend.map(t => [t.label, t.total, t.open, t.in_progress, t.closed]),
        ];
        const wsCm = XLSX.utils.aoa_to_sheet(cmRows);
        wsCm['!cols'] = [{ wch: 14 }, { wch: 12 }, { wch: 14 }, { wch: 12 }, { wch: 12 }];
        XLSX.utils.book_append_sheet(wb, wsCm, 'Corrective');
    }

    if (exportType.value === 'improvement' || exportType.value === 'both') {
        const impTotal = props.impSummary.open + props.impSummary.in_progress + props.impSummary.closed;
        const impRows: any[][] = [
            ['Laporan Improvement'], ['Periode', periode], ['PIC', picLabel], [],
            ['RINGKASAN'], ['Open', 'In Progress', 'Closed', 'Total'],
            [props.impSummary.open, props.impSummary.in_progress, props.impSummary.closed, impTotal],
            [], ['TREND BULANAN'], ['Bulan', 'Total', 'Open', 'In Progress', 'Closed'],
            ...props.impTrend.map(t => [t.label, t.total, t.open, t.in_progress, t.closed]),
        ];
        const wsImp = XLSX.utils.aoa_to_sheet(impRows);
        wsImp['!cols'] = [{ wch: 14 }, { wch: 12 }, { wch: 14 }, { wch: 12 }, { wch: 12 }];
        XLSX.utils.book_append_sheet(wb, wsImp, 'Improvement');
    }

    XLSX.writeFile(wb, `Laporan_JIG_${periodeLabel.value.replace(/\s+/g, '_')}.xlsx`);
    showExportModal.value = false;
};

const donutCanvas  = ref<HTMLCanvasElement|null>(null);
const pmBarCanvas  = ref<HTMLCanvasElement|null>(null);
const cmBarCanvas  = ref<HTMLCanvasElement|null>(null);
const impBarCanvas = ref<HTMLCanvasElement|null>(null);
let donutChart:  Chart|null = null;
let pmBarChart:  Chart|null = null;
let cmBarChart:  Chart|null = null;
let impBarChart: Chart|null = null;

const isDark    = () => document.documentElement.classList.contains('dark');
const gridColor = () => isDark() ? '#374151' : '#f3f4f6';
const textColor = () => isDark() ? '#9ca3af' : '#6b7280';

const createDonut = () => {
    if (!donutCanvas.value) return;
    const { done, late, pending, total } = props.pmSummary;
    const isEmpty = total === 0;
    donutChart = new Chart(donutCanvas.value, {
        type: 'doughnut',
        data: {
            labels: isEmpty ? ['Tidak ada data'] : ['Selesai', 'Pending', 'Terlambat'],
            datasets: [{
                data: isEmpty ? [1] : [done, pending, late],
                backgroundColor: isEmpty ? ['#e5e7eb'] : ['#22c55e', '#facc15', '#f87171'],
                borderColor:     isEmpty ? ['#d1d5db'] : ['#16a34a', '#eab308', '#ef4444'],
                borderWidth: 0, hoverOffset: 4, spacing: 3,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true, aspectRatio: 1,
            resizeDelay: 0, cutout: '78%', animation: { duration: 400 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: !isEmpty, backgroundColor: '#1e293b', padding: 10, cornerRadius: 8,
                    callbacks: { label: (ctx) => { const val = ctx.parsed; const pct = total > 0 ? Math.round((val/total)*100) : 0; return `  ${ctx.label}: ${val} (${pct}%)`; } }
                }
            }
        }
    });
};

const updateDonut = () => { if (donutChart) { donutChart.destroy(); donutChart = null; } createDonut(); };

const createPmBar = () => {
    if (!pmBarCanvas.value) return;
    pmBarChart = new Chart(pmBarCanvas.value, {
        type: 'bar',
        data: {
            labels: props.pmTrend.map(t => t.label),
            datasets: [
                { label: 'Total',     data: props.pmTrend.map(t => t.total),   backgroundColor: 'rgba(199,210,254,0.5)', borderColor: '#a5b4fc', borderWidth: 1, borderRadius: 4, order: 3 },
                { label: 'Selesai',   data: props.pmTrend.map(t => t.done),    backgroundColor: 'rgba(34,197,94,0.8)',   borderColor: '#16a34a', borderWidth: 1, borderRadius: 4, order: 1 },
                { label: 'Terlambat', data: props.pmTrend.map(t => t.late),    backgroundColor: 'rgba(239,68,68,0.75)',  borderColor: '#dc2626', borderWidth: 1, borderRadius: 4, order: 2 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation: { duration: 400 },
            plugins: { legend: { display: true, position: 'top', labels: { color: textColor(), font: { size: 11 }, boxWidth: 12, padding: 12 } }, tooltip: { mode: 'index', intersect: false } },
            scales: { x: { ticks: { color: textColor(), font: { size: 11 } }, grid: { color: gridColor() } }, y: { beginAtZero: true, ticks: { color: textColor(), font: { size: 11 }, stepSize: 1 }, grid: { color: gridColor() } } }
        }
    });
};

const updatePmBar = () => {
    if (!pmBarChart) { createPmBar(); return; }
    pmBarChart.data.datasets[0].data = props.pmTrend.map(t => t.total);
    pmBarChart.data.datasets[1].data = props.pmTrend.map(t => t.done);
    pmBarChart.data.datasets[2].data = props.pmTrend.map(t => t.late);
    pmBarChart.update('active');
};

const createCmBar = () => {
    if (!cmBarCanvas.value) return;
    cmBarChart = new Chart(cmBarCanvas.value, {
        type: 'bar',
        data: {
            labels: props.cmTrend.map(t => t.label),
            datasets: [
                { label: 'Total',  data: props.cmTrend.map(t => t.total),  backgroundColor: 'rgba(254,202,202,0.6)', borderColor: '#fca5a5', borderWidth: 1, borderRadius: 4, order: 3 },
                { label: 'Closed', data: props.cmTrend.map(t => t.closed), backgroundColor: 'rgba(34,197,94,0.75)',  borderColor: '#16a34a', borderWidth: 1, borderRadius: 4, order: 1 },
                { label: 'Open',   data: props.cmTrend.map(t => t.open),   backgroundColor: 'rgba(239,68,68,0.75)',  borderColor: '#dc2626', borderWidth: 1, borderRadius: 4, order: 2 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation: { duration: 400 },
            plugins: { legend: { display: true, position: 'top', labels: { color: textColor(), font: { size: 11 }, boxWidth: 12, padding: 12 } }, tooltip: { mode: 'index', intersect: false } },
            scales: { x: { ticks: { color: textColor(), font: { size: 11 } }, grid: { color: gridColor() } }, y: { beginAtZero: true, ticks: { color: textColor(), font: { size: 11 }, stepSize: 1 }, grid: { color: gridColor() } } }
        }
    });
};

const updateCmBar = () => {
    if (!cmBarChart) { createCmBar(); return; }
    cmBarChart.data.datasets[0].data = props.cmTrend.map(t => t.total);
    cmBarChart.data.datasets[1].data = props.cmTrend.map(t => t.closed);
    cmBarChart.data.datasets[2].data = props.cmTrend.map(t => t.open);
    cmBarChart.update('active');
};

const createImpBar = () => {
    if (!impBarCanvas.value) return;
    impBarChart = new Chart(impBarCanvas.value, {
        type: 'bar',
        data: {
            labels: props.impTrend.map(t => t.label),
            datasets: [
                { label: 'Total',  data: props.impTrend.map(t => t.total),  backgroundColor: 'rgba(147,197,253,0.5)', borderColor: '#60a5fa', borderWidth: 1, borderRadius: 4, order: 3 },
                { label: 'Closed', data: props.impTrend.map(t => t.closed), backgroundColor: 'rgba(34,197,94,0.75)',  borderColor: '#16a34a', borderWidth: 1, borderRadius: 4, order: 1 },
                { label: 'Open',   data: props.impTrend.map(t => t.open),   backgroundColor: 'rgba(239,68,68,0.75)',  borderColor: '#dc2626', borderWidth: 1, borderRadius: 4, order: 2 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation: { duration: 400 },
            plugins: { legend: { display: true, position: 'top', labels: { color: textColor(), font: { size: 11 }, boxWidth: 12, padding: 12 } }, tooltip: { mode: 'index', intersect: false } },
            scales: { x: { ticks: { color: textColor(), font: { size: 11 } }, grid: { color: gridColor() } }, y: { beginAtZero: true, ticks: { color: textColor(), font: { size: 11 }, stepSize: 1 }, grid: { color: gridColor() } } }
        }
    });
};

const updateImpBar = () => {
    if (!impBarChart) { createImpBar(); return; }
    impBarChart.data.datasets[0].data = props.impTrend.map(t => t.total);
    impBarChart.data.datasets[1].data = props.impTrend.map(t => t.closed);
    impBarChart.data.datasets[2].data = props.impTrend.map(t => t.open);
    impBarChart.update('active');
};

const rebuildAllCharts = () => {
    donutChart?.destroy();  donutChart  = null; createDonut();
    pmBarChart?.destroy();  pmBarChart  = null; createPmBar();
    cmBarChart?.destroy();  cmBarChart  = null; createCmBar();
    impBarChart?.destroy(); impBarChart = null; createImpBar();
};

onMounted(async () => {
    await nextTick();
    createDonut(); createPmBar(); createCmBar(); createImpBar();

    const unsubFinish = router.on('finish', () => {
        nextTick(() => { updateDonut(); updatePmBar(); updateCmBar(); updateImpBar(); });
    });

    let lastDark = isDark();
    const obs = new MutationObserver(() => {
        const nowDark = isDark();
        if (nowDark === lastDark) return;
        lastDark = nowDark;
        rebuildAllCharts();
    });
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    onUnmounted(() => { unsubFinish(); obs.disconnect(); });
});

onUnmounted(() => { donutChart?.destroy(); pmBarChart?.destroy(); cmBarChart?.destroy(); impBarChart?.destroy(); });

const incrementTahun = () => { filterTahun.value = Number(filterTahun.value) + 1; navigate(); };
const decrementTahun = () => { filterTahun.value = Number(filterTahun.value) - 1; navigate(); };
</script>
<template>
    <Head title="Dashboard JIG" />
    <AppLayout :breadcrumbs="[{title:'JIG',href:'/jig/dashboard'},{title:'Dashboard',href:'/jig/dashboard'}]">
        <div class="p-3 sm:p-5 lg:p-6 space-y-4">

            <!-- Header -->
            <div class="bg-gradient-to-br from-violet-600 via-purple-600 to-fuchsia-600 rounded-2xl shadow-xl overflow-hidden">
                <div class="px-4 pt-4 pb-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="bg-white/15 p-2 rounded-xl backdrop-blur-sm shrink-0">
                            <Wrench class="w-4 h-4 text-white" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-base font-bold text-white tracking-tight leading-tight">Dashboard JIG</h1>
                            <p class="text-white/60 text-xs truncate">{{ periodeLabel }}</p>
                        </div>
                    </div>
                    <button @click="showExportModal = true"
                        class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 backdrop-blur-sm text-white text-xs font-semibold px-3 py-2 rounded-xl border border-white/20 transition-all shrink-0">
                        <Download class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Export</span>
                    </button>
                </div>

                <div class="px-4 pb-3 flex items-center gap-2">
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-xl border border-white/10 overflow-hidden shrink-0">
                        <button @click="decrementTahun" class="px-2.5 py-2 hover:bg-white/20 transition-colors">
                            <ChevronLeft class="w-3.5 h-3.5 text-white" />
                        </button>
                        <span class="text-white font-bold text-sm px-2 tabular-nums select-none">{{ filterTahun }}</span>
                        <button @click="incrementTahun" class="px-2.5 py-2 hover:bg-white/20 transition-colors">
                            <ChevronRight class="w-3.5 h-3.5 text-white" />
                        </button>
                    </div>
                    <select v-if="!isPic && pics.length" v-model="filterPic" @change="navigate()"
                        class="flex-1 min-w-0 bg-white/15 backdrop-blur-sm text-white text-xs font-semibold rounded-xl px-3 py-2 border border-white/20 focus:outline-none appearance-none truncate">
                        <option value="" class="text-gray-900 bg-white">Semua PIC</option>
                        <option v-for="p in pics" :key="p.id" :value="p.id" class="text-gray-900 bg-white">{{ p.name }}</option>
                    </select>
                </div>

                <div class="px-4 pb-3">
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-1 flex items-center overflow-x-auto scrollbar-none gap-0.5">
                        <button @click="filterBulan = 'all'; navigate()"
                            :class="['px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap shrink-0',
                                filterBulan === 'all' ? 'bg-white text-violet-700 shadow-sm' : 'text-white/80 hover:text-white hover:bg-white/10']">
                            Semua
                        </button>
                        <button v-for="b in BULAN_LIST" :key="b.val" @click="filterBulan = b.val; navigate()"
                            :class="['px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap shrink-0',
                                filterBulan == b.val ? 'bg-white text-violet-700 shadow-sm' : 'text-white/80 hover:text-white hover:bg-white/10']">
                            {{ b.label }}
                        </button>
                    </div>
                </div>

                <div class="px-4 pb-4">
                    <div class="bg-black/20 backdrop-blur-sm rounded-xl p-1 flex items-center gap-0.5">
                        <button @click="filterMinggu = ''; navigate()"
                            :class="['flex-1 py-1.5 rounded-lg text-xs font-semibold transition-all text-center whitespace-nowrap',
                                filterMinggu === '' ? 'bg-white/25 text-white shadow-sm ring-1 ring-white/30' : 'text-white/70 hover:text-white hover:bg-white/10']">
                            Semua
                        </button>
                        <button v-for="w in MINGGU_LIST" :key="w.val" @click="filterMinggu = w.val; navigate()"
                            :class="['flex-1 py-1.5 rounded-lg text-xs font-semibold transition-all text-center whitespace-nowrap',
                                filterMinggu == w.val ? 'bg-white/25 text-white shadow-sm ring-1 ring-white/30' : 'text-white/70 hover:text-white hover:bg-white/10']">
                            {{ w.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Export Modal -->
            <Teleport to="body">
                <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showExportModal = false">
                        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="showExportModal" class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm border border-gray-100 dark:border-gray-700">
                                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 bg-violet-100 dark:bg-violet-900/30 rounded-lg flex items-center justify-center">
                                            <Download class="w-3.5 h-3.5 text-violet-600" />
                                        </div>
                                        <h3 class="font-bold text-sm text-gray-900 dark:text-white">Export Excel</h3>
                                    </div>
                                    <button @click="showExportModal = false" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <X class="w-4 h-4 text-gray-400" />
                                    </button>
                                </div>
                                <div class="p-5 space-y-4">
                                    <div>
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">Pilih Data</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button @click="exportType = 'pm'"
                                                :class="['flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 text-xs font-semibold transition-all',
                                                    exportType === 'pm' ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-400' : 'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-gray-300']">
                                                <Calendar class="w-4 h-4" /> Preventive
                                            </button>
                                            <button @click="exportType = 'cm'"
                                                :class="['flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 text-xs font-semibold transition-all',
                                                    exportType === 'cm' ? 'border-red-500 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-gray-300']">
                                                <AlertTriangle class="w-4 h-4" /> Corrective
                                            </button>
                                            <button @click="exportType = 'improvement'"
                                                :class="['flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 text-xs font-semibold transition-all',
                                                    exportType === 'improvement' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-gray-300']">
                                                <TrendingUp class="w-4 h-4" /> Improvement
                                            </button>
                                            <button @click="exportType = 'both'"
                                                :class="['flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 text-xs font-semibold transition-all',
                                                    exportType === 'both' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400' : 'border-gray-200 dark:border-gray-600 text-gray-500 hover:border-gray-300']">
                                                <Activity class="w-4 h-4" /> Semua
                                            </button>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3.5 space-y-1.5">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Ringkasan Export</p>
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-500 dark:text-gray-400">Periode</span>
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ periodeLabel }}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-500 dark:text-gray-400">PIC</span>
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ filterPic ? (pics.find(p => p.id == filterPic)?.name ?? '-') : 'Semua PIC' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-500 dark:text-gray-400">Sheet</span>
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ exportType === 'pm' ? 'Preventive' : exportType === 'cm' ? 'Corrective' : exportType === 'improvement' ? 'Improvement' : 'Semua (3 sheet)' }}
                                            </span>
                                        </div>
                                        <div v-if="exportType === 'pm' || exportType === 'both'" class="flex items-center justify-between text-xs">
                                            <span class="text-gray-500 dark:text-gray-400">PM Completion</span>
                                            <span class="font-bold" :class="rateColor(completionRate)">{{ completionRate }}%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-5 pb-5 flex gap-2">
                                    <button @click="showExportModal = false"
                                        class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        Batal
                                    </button>
                                    <button @click="exportToExcel"
                                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-purple-600 text-white text-sm font-semibold hover:from-violet-700 hover:to-purple-700 transition-all shadow-sm">
                                        <Download class="w-4 h-4" /> Download
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </Transition>
            </Teleport>

            <!-- Tab Bar -->
            <div class="bg-gray-100 dark:bg-gray-800 rounded-xl p-1">
                <div class="flex gap-1 overflow-x-auto scrollbar-none">
                    <button @click="activeTab = 'pm'"
                        :class="['flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg font-semibold text-xs transition-all whitespace-nowrap shrink-0 flex-1 min-w-0',
                            activeTab==='pm' ? 'bg-white dark:bg-gray-700 text-violet-600 shadow' : 'text-gray-500 hover:text-gray-700']">
                        <Calendar class="w-3.5 h-3.5 shrink-0" />
                        <span class="truncate">PM</span>
                    </button>
                    <button @click="activeTab = 'cm'"
                        :class="['flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg font-semibold text-xs transition-all whitespace-nowrap shrink-0 flex-1 min-w-0',
                            activeTab==='cm' ? 'bg-white dark:bg-gray-700 text-red-600 shadow' : 'text-gray-500 hover:text-gray-700']">
                        <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
                        <span class="truncate">CM</span>
                    </button>
                    <button @click="activeTab = 'improvement'"
                        :class="['flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg font-semibold text-xs transition-all whitespace-nowrap shrink-0 flex-1 min-w-0',
                            activeTab==='improvement' ? 'bg-white dark:bg-gray-700 text-blue-600 shadow' : 'text-gray-500 hover:text-gray-700']">
                        <TrendingUp class="w-3.5 h-3.5 shrink-0" />
                        <span class="truncate">IM</span>
                    </button>
                    <button v-if="!isPic" @click="activeTab = 'performance'"
                        :class="['flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg font-semibold text-xs transition-all whitespace-nowrap shrink-0 flex-1 min-w-0',
                            activeTab==='performance' ? 'bg-white dark:bg-gray-700 text-purple-600 shadow' : 'text-gray-500 hover:text-gray-700']">
                        <Activity class="w-3.5 h-3.5 shrink-0" />
                        <span class="truncate">PIC</span>
                    </button>
                </div>
            </div>

            <!-- TAB: Preventive -->
            <div v-show="activeTab === 'pm'" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4">
                        <p class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-4">PM Completion Rate</p>
                        <div class="flex items-center gap-4">
                            <div class="relative shrink-0" style="width:130px;height:130px">
                                <canvas ref="donutCanvas" style="display:block;max-width:130px;max-height:130px"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-[26px] font-black text-gray-900 dark:text-white leading-none">{{ completionRate }}%</span>
                                    <span class="text-[9px] text-gray-400 mt-1 font-medium text-center px-2">{{ periodeLabel }}</span>
                                </div>
                            </div>
                            <div class="flex-1 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-block w-2 h-2 rounded-full bg-green-500 shrink-0"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Selesai</span>
                                    </div>
                                    <span class="font-black text-green-600 text-base tabular-nums">{{ pmSummary.done }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-block w-2 h-2 rounded-full bg-yellow-400 shrink-0"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Pending</span>
                                    </div>
                                    <span class="font-black text-yellow-500 text-base tabular-nums">{{ pmSummary.pending }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-block w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Terlambat</span>
                                    </div>
                                    <span class="font-black text-red-500 text-base tabular-nums">{{ pmSummary.late }}</span>
                                </div>
                                <div class="pt-2 border-t border-dashed border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                    <span class="text-xs text-gray-400">Total</span>
                                    <span class="font-black text-gray-900 dark:text-white text-base tabular-nums">{{ pmSummary.total }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-2 grid grid-cols-2 gap-3">
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm p-3.5">
                            <p class="text-xs text-gray-400 font-medium">Total Jadwal</p>
                            <p class="text-3xl font-black text-gray-900 dark:text-white mt-1">{{ pmSummary.total }}</p>
                            <p class="text-xs text-gray-400 mt-1 truncate">{{ periodeLabel }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl border border-green-100 dark:border-green-900/50 shadow-sm p-3.5">
                            <p class="text-xs text-green-600 font-semibold flex items-center gap-1"><CheckCircle2 class="w-3 h-3"/>Selesai</p>
                            <p class="text-3xl font-black text-green-600 mt-1">{{ pmSummary.done }}</p>
                            <p class="text-xs text-green-400 mt-1">Tepat waktu</p>
                        </div>
                        <div class="bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 rounded-xl border border-yellow-100 dark:border-yellow-900/50 shadow-sm p-3.5">
                            <p class="text-xs text-yellow-600 font-semibold flex items-center gap-1"><Clock class="w-3 h-3"/>Pending</p>
                            <p class="text-3xl font-black text-yellow-600 mt-1">{{ pmSummary.pending }}</p>
                            <p class="text-xs text-yellow-400 mt-1">Belum dilaporkan</p>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-xl border border-red-100 dark:border-red-900/50 shadow-sm p-3.5">
                            <p class="text-xs text-red-600 font-semibold flex items-center gap-1"><AlertTriangle class="w-3 h-3"/>Terlambat</p>
                            <p class="text-3xl font-black text-red-600 mt-1">{{ pmSummary.late }}</p>
                            <p class="text-xs text-red-400 mt-1">Melewati deadline</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <Calendar class="w-4 h-4 text-violet-500" /> PM per Bulan — {{ filterTahun }}
                        </h3>
                        <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-full">{{ pmTrend.filter(t => t.total > 0).length }} bulan aktif</span>
                    </div>
                    <div class="p-4">
                        <div style="height:240px;position:relative;">
                            <canvas ref="pmBarCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3.5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <Clock class="w-4 h-4 text-yellow-500" /> Preventive Pending
                        </h3>
                        <Link href="/jig/pm/report" class="text-xs text-violet-600 hover:underline flex items-center gap-1">
                            Lihat Semua <ChevronRight class="w-3 h-3" />
                        </Link>
                    </div>
                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-if="upcomingPm.length === 0" class="py-10 text-center">
                            <CheckCircle2 class="w-8 h-8 mx-auto mb-1.5 text-green-400 opacity-50" />
                            <p class="text-xs text-gray-400">Semua PM sudah selesai</p>
                        </div>
                        <div v-for="pm in upcomingPm" :key="pm.id" class="px-4 py-3 hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ pm.pm_schedule?.jig?.name }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
                                        {{ pm.pm_schedule?.jig?.line }}
                                        <span class="text-gray-300 dark:text-gray-600">|</span>
                                        <User class="w-3 h-3" />{{ pm.pic?.name }}
                                    </p>
                                </div>
                                <span class="text-xs font-semibold text-violet-600 bg-violet-50 dark:bg-violet-900/20 px-2 py-0.5 rounded-lg shrink-0">
                                    {{ formatWeek(pm.planned_week_start, pm.planned_week_end) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Corrective -->
            <div v-show="activeTab === 'cm'" class="space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-xl border border-red-100 shadow-sm p-3 text-center">
                        <p class="text-xs text-red-600 font-semibold">Open</p>
                        <p class="text-3xl font-black text-red-600 mt-1">{{ cmSummary.open }}</p>
                        <p class="text-xs text-red-400 mt-1">Perlu tindakan</p>
                    </div>
                    <div class="bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 rounded-xl border border-yellow-100 shadow-sm p-3 text-center">
                        <p class="text-xs text-yellow-600 font-semibold leading-tight">In Progress</p>
                        <p class="text-3xl font-black text-yellow-600 mt-1">{{ cmSummary.in_progress }}</p>
                        <p class="text-xs text-yellow-400 mt-1">Dikerjakan</p>
                    </div>
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl border border-green-100 shadow-sm p-3 text-center">
                        <p class="text-xs text-green-600 font-semibold">Closed</p>
                        <p class="text-3xl font-black text-green-600 mt-1">{{ cmSummary.closed }}</p>
                        <p class="text-xs text-green-400 mt-1">Selesai</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-red-500" /> CM per Bulan — {{ filterTahun }}
                        </h3>
                        <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-full">{{ cmTrend.filter(t => t.total > 0).length }} bulan aktif</span>
                    </div>
                    <div class="p-4">
                        <div style="height:240px;position:relative;">
                            <canvas ref="cmBarCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3.5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-red-500" /> CM Aktif
                        </h3>
                        <Link href="/jig/cm" class="text-xs text-red-500 hover:underline flex items-center gap-1">
                            Lihat Semua <ChevronRight class="w-3 h-3" />
                        </Link>
                    </div>
                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-if="recentCm.length === 0" class="py-10 text-center">
                            <CheckCircle2 class="w-8 h-8 mx-auto mb-1.5 text-green-400 opacity-50" />
                            <p class="text-xs text-gray-400">Tidak ada CM aktif</p>
                        </div>
                        <div v-for="cm in recentCm" :key="cm.id" class="px-4 py-3 hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ cm.jig?.name }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ cm.description }}</p>
                                    <p class="text-xs text-gray-400 flex items-center gap-1 mt-0.5">
                                        <User class="w-3 h-3" />{{ cm.pic?.name }} · {{ formatDate(cm.report_date) }}
                                    </p>
                                </div>
                                <span :class="['shrink-0 px-2 py-0.5 rounded-full text-xs font-bold', cmStatusColor[cm.status]]">
                                    {{ cmStatusLabel[cm.status] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Improvement -->
            <div v-show="activeTab === 'improvement'" class="space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-xl border border-red-100 shadow-sm p-3 text-center">
                        <p class="text-xs text-red-600 font-semibold">Open</p>
                        <p class="text-3xl font-black text-red-600 mt-1">{{ impSummary.open }}</p>
                        <p class="text-xs text-red-400 mt-1">Perlu tindakan</p>
                    </div>
                    <div class="bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 rounded-xl border border-yellow-100 shadow-sm p-3 text-center">
                        <p class="text-xs text-yellow-600 font-semibold leading-tight">In Progress</p>
                        <p class="text-3xl font-black text-yellow-600 mt-1">{{ impSummary.in_progress }}</p>
                        <p class="text-xs text-yellow-400 mt-1">Dikerjakan</p>
                    </div>
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl border border-green-100 shadow-sm p-3 text-center">
                        <p class="text-xs text-green-600 font-semibold">Closed</p>
                        <p class="text-3xl font-black text-green-600 mt-1">{{ impSummary.closed }}</p>
                        <p class="text-xs text-green-400 mt-1">Selesai</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <TrendingUp class="w-4 h-4 text-blue-500" /> Improvement per Bulan — {{ filterTahun }}
                        </h3>
                        <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-full">{{ impTrend.filter(t => t.total > 0).length }} bulan aktif</span>
                    </div>
                    <div class="p-4">
                        <div style="height:240px;position:relative;">
                            <canvas ref="impBarCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3.5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <TrendingUp class="w-4 h-4 text-blue-500" /> Improvement Aktif
                        </h3>
                        <Link href="/jig/improvement" class="text-xs text-blue-500 hover:underline flex items-center gap-1">
                            Lihat Semua <ChevronRight class="w-3 h-3" />
                        </Link>
                    </div>
                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-if="recentImp.length === 0" class="py-10 text-center">
                            <CheckCircle2 class="w-8 h-8 mx-auto mb-1.5 text-green-400 opacity-50" />
                            <p class="text-xs text-gray-400">Tidak ada Improvement aktif</p>
                        </div>
                        <div v-for="imp in recentImp" :key="imp.id" class="px-4 py-3 hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ imp.jig?.name }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ imp.description }}</p>
                                    <p class="text-xs text-gray-400 flex items-center gap-1 mt-0.5">
                                        <User class="w-3 h-3" />{{ imp.pic?.name }} · {{ formatDate(imp.report_date) }}
                                    </p>
                                </div>
                                <span :class="['shrink-0 px-2 py-0.5 rounded-full text-xs font-bold', cmStatusColor[imp.status]]">
                                    {{ cmStatusLabel[imp.status] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Performa PIC -->
            <div v-show="activeTab === 'performance' && !isPic" class="space-y-4">
                <div v-if="picPerformance.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm py-16 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                        <Activity class="w-6 h-6 text-gray-400" />
                    </div>
                    <p class="text-sm font-semibold text-gray-500">Belum ada data</p>
                    <p class="text-xs text-gray-400 mt-1">Tidak ada data performa untuk periode ini</p>
                </div>

                <template v-else>
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <Activity class="w-4 h-4 text-violet-500" /> Performa PIC
                            <span v-if="filterMinggu" class="text-xs font-normal bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 px-2 py-0.5 rounded-full">
                                Week {{ filterMinggu }}
                            </span>
                        </h3>
                        <div class="hidden sm:flex items-center gap-3 text-xs text-gray-500">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>Selesai</span>
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span>Pending</span>
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>Terlambat</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div v-for="(pic, idx) in picPerformance" :key="pic.id"
                            class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 hover:shadow-md transition-shadow duration-200">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div :class="['w-7 h-7 rounded-xl flex items-center justify-center text-xs font-black shrink-0',
                                        idx===0 ? 'bg-yellow-400 text-yellow-900' :
                                        idx===1 ? 'bg-gray-200 text-gray-600' :
                                        idx===2 ? 'bg-orange-200 text-orange-700' :
                                        'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400']">
                                        {{ idx + 1 }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ pic.name }}</p>
                                        <p class="text-xs text-gray-400">{{ pic.total }} jadwal PM</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span :class="['text-2xl font-black leading-none', rateColor(pic.completion_rate)]">{{ pic.completion_rate }}%</span>
                                    <p class="text-[10px] text-gray-400 mt-0.5">completion</p>
                                </div>
                            </div>

                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden mb-3" v-if="pic.total > 0">
                                <div class="h-2 rounded-full transition-all duration-700 bg-green-500" :style="{width:(pic.done/pic.total*100)+'%'}"></div>
                            </div>

                            <div class="grid grid-cols-5 gap-1.5">
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-2 text-center">
                                    <p class="text-base font-black text-green-600 leading-none">{{ pic.done }}</p>
                                    <p class="text-[10px] text-green-500 mt-1 font-medium">Selesai</p>
                                </div>
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-2 text-center">
                                    <p class="text-base font-black text-yellow-600 leading-none">{{ pic.pending }}</p>
                                    <p class="text-[10px] text-yellow-500 mt-1 font-medium">Pending</p>
                                </div>
                                <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-2 text-center">
                                    <p class="text-base font-black text-red-500 leading-none">{{ pic.late }}</p>
                                    <p class="text-[10px] text-red-400 mt-1 font-medium">Late</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2 text-center">
                                    <p class="text-base font-black text-gray-600 dark:text-gray-300 leading-none">{{ pic.cm_total }}</p>
                                    <p class="text-[10px] text-gray-400 mt-1 font-medium">CM</p>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-2 text-center">
                                    <p class="text-base font-black text-blue-600 dark:text-blue-400 leading-none">{{ pic.imp_total }}</p>
                                    <p class="text-[10px] text-blue-400 mt-1 font-medium">Improv</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

        </div>
    </AppLayout>
</template>
