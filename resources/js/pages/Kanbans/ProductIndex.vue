<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Package, Plus, Search, Factory, X} from 'lucide-vue-next';

interface Line {
    id: number;
    line_name: string;
    line_code: string;
}

interface Product {
    id: string;
    product_code: string;
    product_name: string;
    customer: string;
    current_stock: number;
    line: Line;
    created_at: string;
}

interface Props {
    products: {
        data: Product[];
        current_page: number;
        last_page: number;
        total: number;
    };
    lines: Line[];
    filters?: {
        search?: string;
        line_id?: number;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters?.search || '');
const selectedLineId = ref(props.filters?.line_id || '');
const showCreateModal = ref(false);

const form = ref({
    id: '',
    line_id: '',
    product_name: '',
    customer: '',
    product_code: ''
});

const errors = ref<Record<string, string>>({});

watch([search, selectedLineId], () => {
    router.get('/products', {
        search: search.value,
        line_id: selectedLineId.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
}, {
    deep: true
});

const clearFilters = () => {
    search.value = '';
    selectedLineId.value = '';
};

const openCreateModal = () => {
    form.value = {
        id: '',
        line_id: '',
        product_name: '',
        customer: '',
        product_code: ''
    };
    errors.value = {};
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    form.value = {
        id: '',
        line_id: '',
        product_name: '',
        customer: '',
        product_code: ''
    };
    errors.value = {};
};

const submitForm = () => {
    router.post('/products', form.value, {
        onError: (err) => {
            errors.value = err;
        },
        onSuccess: () => {
            closeCreateModal();
        }
    });
};

const getStockBadgeClass = (stock: number) => {
    if (stock === 0) return 'from-gray-400 to-gray-500';
    return 'from-green-400 to-emerald-500';
};

const getStockStatus = (stock: number) => {
    if (stock === 0) return 'Habis';
    if (stock > 0) return 'Active';
    return 'In Stock';
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};
</script>

<template>
    <Head title="Products" />
    <AppLayout :breadcrumbs="[
        { title: 'Kanban System', href: '/products' },
        { title: 'Products', href: '/products' }
    ]">
        <div class="p-6 space-y-6 bg-gray-50 dark:!bg-gray-900 min-h-screen">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent flex items-center gap-3">
                        <Package class="w-8 h-8 text-blue-600" />
                        Product Management
                    </h1>
                </div>
                <button @click="openCreateModal" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium flex items-center gap-2">
                    <Plus class="w-5 h-5" />
                    Add Product
                </button>
            </div>

            <div class="flex gap-3">
                <div class="relative flex-1 max-w-md">
                    <input v-model="search" type="text" placeholder="Search products..." class="w-full pl-11 pr-4 py-3 rounded-xl border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" />
                    <Search class="w-5 h-5 text-gray-400 absolute left-3.5 top-3.5" />
                </div>
                <select v-model="selectedLineId" class="px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                    <option value="">All Lines</option>
                    <option v-for="line in lines" :key="line.id" :value="line.id">{{ line.line_name }}</option>
                </select>
                <button @click="clearFilters" class="px-6 py-3 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">
                    Clear
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <div v-for="product in products.data" :key="product.id" class="group bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 dark:border-gray-700 hover:-translate-y-1">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ product.product_name }}</h3>
                            <p class="text-sm text-purple-600 font-semibold mt-1">{{ product.id }}</p>
                        </div>
                    </div>

                    <div class="mb-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <Factory class="w-4 h-4 text-green-600" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ product.line.line_name }}</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-medium">Customer:</span> {{ product.customer || '-' }}
                        </p>
                    </div>

                    <div class="mb-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 border border-blue-100 dark:border-blue-800">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Current Stock</span>
                            <span class="text-xs font-medium px-2 py-1 rounded-lg" :class="product.current_stock === 0 ? 'bg-gray-200 text-gray-700' : product.current_stock < 50 ? 'bg-red-200 text-red-700' : product.current_stock < 100 ? 'bg-orange-200 text-orange-700' : 'bg-green-200 text-green-700'">
                                {{ getStockStatus(product.current_stock) }}
                            </span>
                        </div>
                        <div class="text-center">
                            <div class="text-6xl font-bold bg-gradient-to-r bg-clip-text text-transparent" :class="getStockBadgeClass(product.current_stock)">
                                {{ product.current_stock }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1 font-medium">units</div>
                        </div>
                    </div>

                    <div class="text-xs text-gray-500 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                        Created: {{ formatDate(product.created_at) }}
                    </div>
                </div>
            </div>

            <div v-if="products.last_page > 1" class="flex justify-center gap-2 mt-8">
                <button
                    v-for="page in products.last_page"
                    :key="page"
                    @click="router.get('/products', { page, search: search, line_id: selectedLineId })"
                    :class="[
                        'px-4 py-2 rounded-xl font-medium transition-all',
                        page === products.current_page
                            ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg'
                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:shadow-lg'
                    ]"
                >
                    {{ page }}
                </button>
            </div>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50" @click.self="closeCreateModal">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Package class="w-6 h-6 text-blue-600" />
                        Create New Product
                    </h3>
                    <button @click="closeCreateModal" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Product ID (SAP ID) <span class="text-red-500">*</span></label>
                        <input v-model="form.id" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Enter SAP Product ID" />
                        <p v-if="errors.id" class="text-red-500 text-sm mt-1">{{ errors.id }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Line <span class="text-red-500">*</span></label>
                        <select v-model="form.line_id" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            <option value="">Select Line</option>
                            <option v-for="line in lines" :key="line.id" :value="line.id">
                                {{ line.line_name }}
                            </option>
                        </select>
                        <p v-if="errors.line_id" class="text-red-500 text-sm mt-1">{{ errors.line_id }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Product Name <span class="text-red-500">*</span></label>
                        <input v-model="form.product_name" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Enter product name" />
                        <p v-if="errors.product_name" class="text-red-500 text-sm mt-1">{{ errors.product_name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Customer</label>
                        <input v-model="form.customer" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Enter customer name (opsional)" />
                        <p v-if="errors.customer" class="text-red-500 text-sm mt-1">{{ errors.customer }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Product Code <span class="text-gray-500 text-xs">(Optional - Auto generated)</span></label>
                        <input v-model="form.product_code" type="text" class="w-full border-2 border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Opsional" />
                        <p v-if="errors.product_code" class="text-red-500 text-sm mt-1">{{ errors.product_code }}</p>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2">
                            <Plus class="w-5 h-5" />
                            Create Product
                        </button>
                        <button type="button" @click="closeCreateModal" class="flex-1 bg-gray-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-gray-700 transition-all">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
