<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { Trash2, Plus, Check, Save, X } from 'lucide-vue-next';

interface Part {
    id: number;
    material_number: string;
    material_description: string;
    line_type: string;
}

interface ProductionPlan {
    id: number;
    order_number: string;
    target_qty: number;
    plan_date: string;
    shift: string;
    cycle1_start?: string;
    cycle1_end?: string;
    cycle1_qty?: number;
    cycle2_start?: string;
    cycle2_end?: string;
    cycle2_qty?: number;
    total_actual: number;
    variance: number;
    part: Part;
}

interface Props {
    plans: ProductionPlan[];
    parts: Part[];
    filters: {
        date?: string;
        shift?: string;
        line_type?: string;
    };
}

const props = defineProps<Props>();

const showAddModal = ref(false);
const showSaveToast = ref(false);
const editedRows = ref<Set<number>>(new Set());
const localPlans = ref<ProductionPlan[]>(JSON.parse(JSON.stringify(props.plans)));

const newRow = ref({
    part_id: null as number | null,
    order_number: '',
    target_qty: null as number | null,
    plan_date: new Date().toISOString().split('T')[0],
    shift: '1',
});

const filterDate = ref(props.filters.date || '');
const filterShift = ref(props.filters.shift || '');
const filterLineType = ref(props.filters.line_type || '');

const lineTypes = ['BIG PRESS', 'INSULATOR', 'SEYI', 'CAULKING', 'WELD SPOT', 'BUDOMARI'];

const hasChanges = computed(() => editedRows.value.size > 0);
const hasActiveFilters = computed(() => filterDate.value || filterShift.value || filterLineType.value);

// Statistics
const stats = computed(() => {
    const total = localPlans.value.length;
    const completed = localPlans.value.filter(p => p.total_actual >= p.target_qty).length;
    const inProgress = localPlans.value.filter(p => p.total_actual > 0 && p.total_actual < p.target_qty).length;
    const notStarted = localPlans.value.filter(p => p.total_actual === 0).length;

    return { total, completed, inProgress, notStarted };
});

watch([filterDate, filterShift, filterLineType], () => {
    router.get('/production-plans', {
        date: filterDate.value || undefined,
        shift: filterShift.value || undefined,
        line_type: filterLineType.value || undefined,
    }, {
        preserveState: false,
        preserveScroll: false,
    });
}, { deep: true });

watch(() => props.plans, (newPlans) => {
    localPlans.value = JSON.parse(JSON.stringify(newPlans));
    editedRows.value.clear();
}, { deep: true });

const markAsEdited = (planId: number) => {
    editedRows.value.add(planId);
};

const clearFilters = () => {
    filterDate.value = '';
    filterShift.value = '';
    filterLineType.value = '';
};

const saveAllChanges = () => {
    if (editedRows.value.size === 0) return;

    showSaveToast.value = true;

    const editedIds = Array.from(editedRows.value);
    let currentIndex = 0;

    const saveNext = () => {
        if (currentIndex >= editedIds.length) {
            editedRows.value.clear();
            setTimeout(() => {
                showSaveToast.value = false;
            }, 2000);
            return;
        }

        const planId = editedIds[currentIndex];
        const plan = localPlans.value.find(p => p.id === planId);

        if (!plan) {
            currentIndex++;
            saveNext();
            return;
        }

        const cycle1 = plan.cycle1_qty || 0;
        const cycle2 = plan.cycle2_qty || 0;
        plan.total_actual = cycle1 + cycle2;
        plan.variance = plan.total_actual - (plan.target_qty || 0);

        router.put(`/production-plans/${plan.id}`, {
            order_number: plan.order_number || '',
            target_qty: plan.target_qty,
            cycle1_start: plan.cycle1_start || null,
            cycle1_end: plan.cycle1_end || null,
            cycle1_qty: plan.cycle1_qty !== undefined ? plan.cycle1_qty : null,
            cycle2_start: plan.cycle2_start || null,
            cycle2_end: plan.cycle2_end || null,
            cycle2_qty: plan.cycle2_qty !== undefined ? plan.cycle2_qty : null,
        }, {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => {
                currentIndex++;
                saveNext();
            },
        });
    };

    saveNext();
};

const openAddModal = () => {
    newRow.value = {
        part_id: null,
        order_number: '',
        target_qty: null,
        plan_date: filterDate.value || new Date().toISOString().split('T')[0],
        shift: filterShift.value || '1',
    };
    showAddModal.value = true;
};

const addNewRow = () => {
    if (!newRow.value.part_id) {
        alert('Please select a part');
        return;
    }

    showSaveToast.value = true;
    router.post('/production-plans', {
        part_id: newRow.value.part_id,
        order_number: newRow.value.order_number || '',
        target_qty: newRow.value.target_qty || 0,
        plan_date: newRow.value.plan_date,
        shift: newRow.value.shift,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
        },
        onFinish: () => {
            setTimeout(() => {
                showSaveToast.value = false;
            }, 2000);
        },
    });
};

const deleteRow = (id: number) => {
    if (confirm('Delete this production plan?')) {
        showSaveToast.value = true;
        router.delete(`/production-plans/${id}`, {
            preserveScroll: true,
            onFinish: () => {
                setTimeout(() => {
                    showSaveToast.value = false;
                }, 2000);
            },
        });
    }
};

const getStatusColor = (plan: ProductionPlan) => {
    if (!plan.target_qty || plan.target_qty === 0) return '';
    const percentage = (plan.total_actual / plan.target_qty) * 100;
    if (percentage >= 100) return 'bg-green-50 dark:bg-green-950/30 border-l-4 border-l-green-500';
    if (percentage >= 50) return 'bg-yellow-50 dark:bg-yellow-950/30 border-l-4 border-l-yellow-500';
    if (percentage > 0) return 'bg-blue-50 dark:bg-blue-950/30 border-l-4 border-l-blue-500';
    return 'bg-gray-50 dark:bg-gray-900/30 border-l-4 border-l-gray-400';
};

const getProgressPercentage = (plan: ProductionPlan) => {
    if (!plan.target_qty || plan.target_qty === 0) return 0;
    return Math.min((plan.total_actual / plan.target_qty) * 100, 100);
};

</script>

<template>
    <Head title="Production Input" />
    <AppLayout :breadcrumbs="[{ title: 'BSTB 4W - STAMPING ADM', href: '/production-plans' }]">
        <div class="p-3 space-y-3">
            <!-- Save Toast -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-[-20px] scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-[-20px] scale-95"
            >
                <div
                    v-if="showSaveToast"
                    class="fixed top-4 right-4 z-50 bg-gradient-to-r from-green-600 to-green-500 text-white px-4 py-2.5 rounded-lg shadow-2xl flex items-center gap-2"
                >
                    <Check class="w-4 h-4" />
                    <span class="text-xs font-semibold">Saved!</span>
                </div>
            </Transition>

            <!-- Compact Header with Filters -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-900 dark:to-blue-800 rounded-lg p-3 text-white shadow-md">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <!-- Left: Title + Stats -->
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2 text-xs">
                            <span class="bg-white/20 px-2 py-1 rounded">Total: <strong>{{ stats.total }}</strong></span>
                            <span class="bg-green-500/30 px-2 py-1 rounded">✓ {{ stats.completed }}</span>
                            <span class="bg-yellow-500/30 px-2 py-1 rounded">⟳ {{ stats.inProgress }}</span>
                        </div>
                    </div>

                    <!-- Right: Filters + Add Button -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <input
                            v-model="filterDate"
                            type="date"
                            class="rounded-md bg-white/95 border-0 px-3 py-1.5 text-gray-900 text-xs focus:ring-2 focus:ring-white shadow-sm font-medium"
                        />
                        <select
                            v-model="filterShift"
                            class="rounded-md bg-white/95 border-0 px-3 py-1.5 text-gray-900 text-xs focus:ring-2 focus:ring-white shadow-sm font-medium"
                        >
                            <option value="">All Shifts</option>
                            <option value="1">Shift 1</option>
                            <option value="2">Shift 2</option>
                            <option value="3">Shift 3</option>
                        </select>
                        <select
                            v-model="filterLineType"
                            class="rounded-md bg-white/95 border-0 px-3 py-1.5 text-gray-900 text-xs focus:ring-2 focus:ring-white shadow-sm font-medium"
                        >
                            <option value="">All Lines</option>
                            <option v-for="line in lineTypes" :key="line" :value="line">{{ line }}</option>
                        </select>
                        <button
                            v-if="hasActiveFilters"
                            @click="clearFilters"
                            class="p-1.5 bg-red-500 hover:bg-red-600 rounded-md transition-all shadow-sm"
                            title="Clear filters"
                        >
                            <X class="w-4 h-4" />
                        </button>
                        <button
                            @click="openAddModal"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-white text-blue-600 rounded-md hover:bg-blue-50 text-xs font-semibold transition-all hover:shadow-lg active:scale-95"
                        >
                            <Plus class="w-4 h-4" />
                            Add
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Modal - Compact -->
            <Transition
                enter-active-class="transition-all duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showAddModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="showAddModal = false">
                    <div class="bg-white dark:bg-sidebar rounded-xl max-w-lg w-full shadow-2xl">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4 rounded-t-xl">
                            <h2 class="text-base font-bold text-white">Add Production Plan</h2>
                        </div>
                        <form @submit.prevent="addNewRow" class="p-4 space-y-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1">Part Number *</label>
                                <select
                                    v-model.number="newRow.part_id"
                                    required
                                    class="w-full rounded-lg border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                >
                                    <option :value="null">Select a part...</option>
                                    <option v-for="part in parts" :key="part.id" :value="part.id">
                                        {{ part.material_number }} - {{ part.material_description }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Order Number</label>
                                    <input
                                        v-model="newRow.order_number"
                                        type="text"
                                        placeholder="e.g., ORD-001"
                                        class="w-full rounded-lg border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Target Qty</label>
                                    <input
                                        v-model.number="newRow.target_qty"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        class="w-full rounded-lg border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Plan Date *</label>
                                    <input
                                        v-model="newRow.plan_date"
                                        type="date"
                                        required
                                        class="w-full rounded-lg border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Shift *</label>
                                    <select
                                        v-model="newRow.shift"
                                        required
                                        class="w-full rounded-lg border border-sidebar-border px-3 py-2 dark:bg-sidebar-accent text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    >
                                        <option value="1">Shift 1</option>
                                        <option value="2">Shift 2</option>
                                        <option value="3">Shift 3</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex gap-2 pt-2">
                                <button
                                    type="button"
                                    @click="showAddModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-sidebar-accent text-xs font-medium transition-all"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="flex-1 px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 text-xs font-medium transition-all hover:shadow-lg active:scale-95"
                                >
                                    Add Plan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Table Section -->
<div class="bg-white dark:bg-sidebar rounded-xl shadow-sm border border-sidebar-border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-xs border-collapse">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-sidebar-accent dark:to-sidebar sticky top-0 z-10">
                <tr class="border-b-2 border-gray-300 dark:border-gray-600">
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 whitespace-nowrap" rowspan="2" style="width: 120px;">
                        <div class="text-xs">Order</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-950/30 whitespace-nowrap" rowspan="2" style="width: 180px;">
                        <div class="text-xs">Part Details</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 whitespace-nowrap" rowspan="2">
                        <div class="text-xs">Line</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-950/30 whitespace-nowrap" rowspan="2">
                        <div class="text-xs">Date</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 whitespace-nowrap" rowspan="2">
                        <div class="text-xs">Shift</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-950/30 whitespace-nowrap" rowspan="2">
                        <div class="text-xs">Target</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-950/30 dark:to-blue-900/30 whitespace-nowrap" colspan="3">
                        <div class="text-xs">Cycle 1</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-950/30 dark:to-purple-900/30 whitespace-nowrap" colspan="3">
                        <div class="text-xs">Cycle 2</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 whitespace-nowrap" rowspan="2">
                        <div class="text-xs">Total</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold border-r-2 border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-950/30 whitespace-nowrap" rowspan="2">
                        <div class="text-xs">Variance</div>
                    </th>
                    <th class="px-4 py-4 text-center font-bold whitespace-nowrap" rowspan="2">
                        <div class="text-xs">Action</div>
                    </th>
                </tr>
                <tr class="border-b-2 border-gray-300 dark:border-gray-600">
                    <th class="px-2 py-3 text-center text-xs font-semibold border-r border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-950/30 whitespace-nowrap">Start</th>
                    <th class="px-2 py-3 text-center text-xs font-semibold border-r border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-950/30 whitespace-nowrap">End</th>
                    <th class="px-2 py-3 text-center text-xs font-semibold border-r-2 border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-950/30 whitespace-nowrap">Qty</th>
                    <th class="px-2 py-3 text-center text-xs font-semibold border-r border-gray-200 dark:border-gray-700 bg-purple-50 dark:bg-purple-950/30 whitespace-nowrap">Start</th>
                    <th class="px-2 py-3 text-center text-xs font-semibold border-r border-gray-200 dark:border-gray-700 bg-purple-50 dark:bg-purple-950/30 whitespace-nowrap">End</th>
                    <th class="px-2 py-3 text-center text-xs font-semibold border-r-2 border-gray-300 dark:border-gray-600 bg-purple-50 dark:bg-purple-950/30 whitespace-nowrap">Qty</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="plan in localPlans"
                    :key="plan.id"
                    class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/30 transition-all duration-150"
                    :class="getStatusColor(plan)"
                >
                    <td class="px-2 py-3 border-r-2 border-gray-200 dark:border-gray-700 text-center" style="width: 120px;">
                        <div class="overflow-x-auto">
                            <input
                                v-model="plan.order_number"
                                type="text"
                                @input="markAsEdited(plan.id)"
                                class="w-full min-w-[100px] bg-white/50 dark:bg-sidebar-accent/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-blue-400 rounded-lg px-2 py-1.5 transition-all text-xs font-medium text-center"
                                placeholder="Order #"
                            />
                        </div>
                    </td>
                    <td class="px-2 py-3 border-r-2 border-gray-200 dark:border-gray-700" style="width: 180px;">
                        <div class="overflow-x-auto space-y-1">
                            <div class="text-xs font-bold whitespace-nowrap">{{ plan.part.material_description }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono whitespace-nowrap">{{ plan.part.material_number }}</div>
                        </div>
                    </td>
                    <td class="px-2 py-3 text-center border-r-2 border-gray-200 dark:border-gray-700">
                        <span class="inline-block px-2 py-1 bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-lg text-xs font-bold whitespace-nowrap">
                            {{ plan.part.line_type }}
                        </span>
                    </td>
                    <td class="px-2 py-3 text-center border-r-2 border-gray-200 dark:border-gray-700">
                        <div class="text-xs font-medium whitespace-nowrap">{{ plan.plan_date }}</div>
                    </td>
                    <td class="px-2 py-3 text-center border-r-2 border-gray-200 dark:border-gray-700">
                        <span class="inline-block px-2 py-1 bg-gradient-to-r from-blue-100 to-blue-200 dark:from-blue-900 dark:to-blue-800 text-blue-700 dark:text-blue-300 rounded-lg text-xs font-bold whitespace-nowrap">
                            Shift {{ plan.shift }}
                        </span>
                    </td>
                    <td class="px-2 py-3 border-r-2 border-gray-200 dark:border-gray-700 text-center">
                        <input
                            v-model.number="plan.target_qty"
                            type="number"
                            @input="markAsEdited(plan.id)"
                            class="w-16 text-center bg-white/50 dark:bg-sidebar-accent/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-blue-400 rounded-lg px-1 py-1.5 transition-all text-xs font-bold"
                        />
                    </td>

                    <!-- Cycle 1 -->
                    <td class="px-1 py-3 border-r border-gray-200 dark:border-gray-700 bg-blue-50/50 dark:bg-blue-900/10 text-center">
                        <input
                            v-model="plan.cycle1_start"
                            type="time"
                            @input="markAsEdited(plan.id)"
                            class="w-15 bg-white dark:bg-sidebar-accent border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-blue-400 rounded-lg px-1 py-1 text-xs transition-all"
                        />
                    </td>
                    <td class="px-1 py-3 border-r border-gray-200 dark:border-gray-700 bg-blue-50/50 dark:bg-blue-900/10 text-center">
                        <input
                            v-model="plan.cycle1_end"
                            type="time"
                            @input="markAsEdited(plan.id)"
                            class="w-15 bg-white dark:bg-sidebar-accent border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-blue-400 rounded-lg px-1 py-1 text-xs transition-all"
                        />
                    </td>
                    <td class="px-1 py-3 border-r-2 border-gray-200 dark:border-gray-700 bg-blue-50/50 dark:bg-blue-900/10 text-center">
                        <input
                            v-model.number="plan.cycle1_qty"
                            type="number"
                            @input="markAsEdited(plan.id)"
                            class="w-16 text-center bg-white dark:bg-sidebar-accent border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-blue-400 rounded-lg px-1 py-1 transition-all text-xs font-bold"
                        />
                    </td>

                    <!-- Cycle 2 -->
                    <td class="px-1 py-3 border-r border-gray-200 dark:border-gray-700 bg-purple-50/50 dark:bg-purple-900/10 text-center">
                        <input
                            v-model="plan.cycle2_start"
                            type="time"
                            @input="markAsEdited(plan.id)"
                            class="w-15 bg-white dark:bg-sidebar-accent border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-purple-400 rounded-lg px-1 py-1 text-xs transition-all"
                        />
                    </td>
                    <td class="px-1 py-3 border-r border-gray-200 dark:border-gray-700 bg-purple-50/50 dark:bg-purple-900/10 text-center">
                        <input
                            v-model="plan.cycle2_end"
                            type="time"
                            @input="markAsEdited(plan.id)"
                            class="w-15 bg-white dark:bg-sidebar-accent border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-purple-400 rounded-lg px-1 py-1 text-xs transition-all"
                        />
                    </td>
                    <td class="px-1 py-3 border-r-2 border-gray-200 dark:border-gray-700 bg-purple-50/50 dark:bg-purple-900/10 text-center">
                        <input
                            v-model.number="plan.cycle2_qty"
                            type="number"
                            @input="markAsEdited(plan.id)"
                            class="w-16 text-center bg-white dark:bg-sidebar-accent border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-purple-400 rounded-lg px-1 py-1 transition-all text-xs font-bold"
                        />
                    </td>

                    <td class="px-2 py-3 border-r-2 border-gray-200 dark:border-gray-700">
                        <div class="text-center">
                            <div class="text-base font-bold">{{ plan.total_actual }}</div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-1">
                                <div
                                    class="h-1.5 rounded-full transition-all duration-300"
                                    :class="{
                                        'bg-green-500': getProgressPercentage(plan) >= 100,
                                        'bg-yellow-500': getProgressPercentage(plan) >= 50 && getProgressPercentage(plan) < 100,
                                        'bg-blue-500': getProgressPercentage(plan) > 0 && getProgressPercentage(plan) < 50,
                                        'bg-gray-400': getProgressPercentage(plan) === 0
                                    }"
                                    :style="{ width: getProgressPercentage(plan) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-2 py-3 border-r-2 border-gray-200 dark:border-gray-700">
                        <div class="text-center">
                            <span
                                class="inline-block px-2 py-1 rounded-lg text-xs font-bold whitespace-nowrap"
                                :class="plan.variance >= 0
                                    ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                                    : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'"
                            >
                                {{ plan.variance >= 0 ? '+' : '' }}{{ plan.variance }}
                            </span>
                        </div>
                    </td>
                    <td class="px-2 py-3 text-center">
                        <button
                            @click="deleteRow(plan.id)"
                            class="p-1.5 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition-all active:scale-95"
                            title="Delete production plan"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="localPlans.length === 0">
                    <td colspan="14" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center gap-3 text-gray-400">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div>
                                <div class="text-lg font-semibold text-gray-500 dark:text-gray-400">No production plans found</div>
                                <div class="text-sm text-gray-400 dark:text-gray-500 mt-1">Create your first production plan to get started</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

            <!-- Save Button - Compact Fixed Bottom -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-8"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-8"
            >
                <div v-if="hasChanges" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-40">
                    <button
                        @click="saveAllChanges"
                        class="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-green-600 to-green-500 text-white rounded-xl hover:from-green-700 hover:to-green-600 text-sm font-bold transition-all hover:shadow-xl active:scale-95 shadow-lg"
                    >
                        <Save class="w-5 h-5" />
                        Save Changes ({{ editedRows.size }})
                    </button>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>
