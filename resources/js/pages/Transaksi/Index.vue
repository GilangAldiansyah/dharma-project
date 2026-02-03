<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, nextTick } from 'vue';
import { Search, Plus, Package, Eye, Trash2, X, Camera, Calendar, User, Hash, ChevronLeft, ChevronRight, Download, Undo2, History, ChevronDown, ChevronUp, Loader2 } from 'lucide-vue-next';
import * as XLSX from 'xlsx';

interface Material {
    id: number;
    material_id: string;
    nama_material: string;
    material_type: string;
    satuan: string;
    part_materials: PartMaterial[];
}

interface PartMaterial {
    id: number;
    part_id: string;
    nama_part: string;
    material_id: number;
}

interface Transaksi {
    id: number;
    transaksi_id: string;
    tanggal: string;
    shift: number;
    pic: string;
    qty: number;
    foto: string[];
    material: {
        material_id: string;
        nama_material: string;
        satuan: string;
        material_type: string;
    };
    part_material?: {
        part_id: string;
        nama_part: string;
    };
    user?: {
        name: string;
    };
    created_at: string;
    total_pengembalian?: number;
    sisa_pengembalian?: number;
    tanggal_pengembalian_terakhir?: string;
}

interface PengembalianHistory {
    id: number;
    pengembalian_id: string;
    tanggal_pengembalian: string;
    pic: string;
    qty_pengembalian: number;
    foto: string[];
    keterangan: string;
    user: {
        name: string;
    };
    created_at: string;
}

interface Props {
    transaksi: {
        data: Transaksi[];
        current_page: number;
        last_page: number;
        total: number;
    };
    statistics: {
        total: number;
        with_return: number;
        shift_1: number;
        shift_2: number;
        shift_3: number;
    };
    filters: {
        search?: string;
        shift?: number;
        date_from?: string;
        date_to?: string;
        has_return?: string;
    };
    effectiveDate: string;
    currentShift: number;
}

const props = defineProps<Props>();

const MAX_WIDTH = 1920;
const MAX_HEIGHT = 1920;
const COMPRESSION_QUALITY = 0.8;

const searchQuery = ref(props.filters.search || '');
const filterShift = ref(props.filters.shift || '');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');
const filterHasReturn = ref(props.filters.has_return || '');
const datePreset = ref('');

const selectedItems = ref<number[]>([]);
const selectAll = ref(false);
const expandedRows = ref<number[]>([]);

const showModal = ref(false);
const showDetailModal = ref(false);
const showPengembalianModal = ref(false);
const showPengembalianHistoryModal = ref(false);
const showImageModal = ref(false);
const selectedTransaksi = ref<Transaksi | null>(null);
const pengembalianHistory = ref<PengembalianHistory[]>([]);
const searchMaterialQuery = ref('');
const searchResults = ref<Material[]>([]);
const selectedMaterial = ref<Material | null>(null);
const isSearching = ref(false);
const previewImages = ref<string[]>([]);
const pengembalianPreviewImages = ref<string[]>([]);
const currentImageIndex = ref(0);
const currentImageList = ref<string[]>([]);
const isCompressingImage = ref(false);
const compressionProgress = ref('');

const showReturnSearchModal = ref(false);
const returnSearchQuery = ref('');
const returnSearchResults = ref<Transaksi[]>([]);
const isSearchingReturn = ref(false);

const form = useForm({
    tanggal: new Date().toISOString().split('T')[0],
    shift: 1,
    pic: '',
    material_id: null as number | null,
    part_material_id: null as number | null,
    qty: '',
    foto: [] as File[],
});

const pengembalianForm = useForm({
    transaksi_material_id: null as number | null,
    tanggal_pengembalian: new Date().toISOString().split('T')[0],
    pic: '',
    qty_pengembalian: '',
    keterangan: '',
    foto: [] as File[],
});

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

const compressImage = (file: File): Promise<File> => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('Gagal membaca file'));
        reader.onload = (e) => {
            const img = new Image();
            img.onerror = () => reject(new Error('Gagal memuat gambar'));
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                if (width > MAX_WIDTH || height > MAX_HEIGHT) {
                    if (width > height) {
                        height = (height / width) * MAX_WIDTH;
                        width = MAX_WIDTH;
                    } else {
                        width = (width / height) * MAX_HEIGHT;
                        height = MAX_HEIGHT;
                    }
                }
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                if (!ctx) { reject(new Error('Gagal mendapatkan canvas context')); return; }
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    if (!blob) { reject(new Error('Gagal mengkompresi gambar')); return; }
                    const compressedFile = new File([blob], file.name.replace(/\.[^/.]+$/, '.jpg'), { type: 'image/jpeg', lastModified: Date.now() });
                    resolve(compressedFile);
                }, 'image/jpeg', COMPRESSION_QUALITY);
            };
            img.src = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    });
};

const formatQty = (qty: number) => { return parseFloat(qty.toString()); };

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatTime = (dateString: string) => {
    return new Date(dateString).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

const getSisaQty = (item: Transaksi): number => {
    if (!item.total_pengembalian || item.total_pengembalian === 0) return item.qty;
    return item.sisa_pengembalian ?? (item.qty - item.total_pengembalian);
};

const toggleExpandRow = (id: number) => {
    const index = expandedRows.value.indexOf(id);
    if (index > -1) { expandedRows.value.splice(index, 1); } else { expandedRows.value.push(id); }
};

const setDatePreset = (preset: string) => {
    datePreset.value = preset;
    const today = new Date();
    switch(preset) {
        case 'today':
            filterDateFrom.value = today.toISOString().split('T')[0];
            filterDateTo.value = today.toISOString().split('T')[0];
            break;
        case 'yesterday':
            const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
            filterDateFrom.value = yesterday.toISOString().split('T')[0];
            filterDateTo.value = yesterday.toISOString().split('T')[0];
            break;
        case 'last7':
            const last7 = new Date(today); last7.setDate(last7.getDate() - 7);
            filterDateFrom.value = last7.toISOString().split('T')[0];
            filterDateTo.value = today.toISOString().split('T')[0];
            break;
        case 'last30':
            const last30 = new Date(today); last30.setDate(last30.getDate() - 30);
            filterDateFrom.value = last30.toISOString().split('T')[0];
            filterDateTo.value = today.toISOString().split('T')[0];
            break;
        case 'thisMonth':
            filterDateFrom.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            filterDateTo.value = today.toISOString().split('T')[0];
            break;
    }
};

const openImageModal = (images: string[], index: number) => {
    currentImageList.value = images; currentImageIndex.value = index; showImageModal.value = true;
};
const closeImageModal = () => { showImageModal.value = false; currentImageList.value = []; currentImageIndex.value = 0; };
const nextImage = () => { if (currentImageIndex.value < currentImageList.value.length - 1) currentImageIndex.value++; };
const prevImage = () => { if (currentImageIndex.value > 0) currentImageIndex.value--; };

const exportToExcel = async () => {
    try {
        const exportButton = document.querySelector('[title="Export ke Excel"]') as HTMLButtonElement;
        const originalHTML = exportButton?.innerHTML;
        if (exportButton) {
            exportButton.innerHTML = '<svg class="animate-spin h-4 w-4 text-white inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span class="hidden sm:inline">Mengunduh...</span>';
            exportButton.disabled = true;
        }
        const params = new URLSearchParams();
        if (searchQuery.value) params.append('search', searchQuery.value);
        if (filterShift.value) params.append('shift', filterShift.value.toString());
        if (filterDateFrom.value) params.append('date_from', filterDateFrom.value);
        if (filterDateTo.value) params.append('date_to', filterDateTo.value);
        if (filterHasReturn.value !== '') params.append('has_return', filterHasReturn.value);
        const response = await fetch(`/transaksi/export-data?${params.toString()}`);
        if (!response.ok) throw new Error('Gagal mengambil data');
        const allData = await response.json();
        const excelData = allData.map((item: Transaksi) => ({
            'ID Transaksi': item.transaksi_id, 'Tanggal': formatDate(item.tanggal), 'Shift': `Shift ${item.shift}`,
            'PIC': item.pic, 'ID Material': item.material.material_id, 'Nama Material': item.material.nama_material,
            'Tipe Material': item.material.material_type, 'Satuan': item.material.satuan, 'Quantity': formatQty(item.qty),
            'Total Pengembalian': item.total_pengembalian || 0,
            'Tanggal Pengembalian': item.tanggal_pengembalian_terakhir ? formatDate(item.tanggal_pengembalian_terakhir) : '-',
            'Dipakai': item.sisa_pengembalian || item.qty, 'Dibuat Oleh': item.user?.name || '-', 'Waktu Input': formatDateTime(item.created_at)
        }));
        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Transaksi Material');
        ws['!cols'] = [{ wch: 18 }, { wch: 12 }, { wch: 8 }, { wch: 20 }, { wch: 15 }, { wch: 30 }, { wch: 20 }, { wch: 10 }, { wch: 15 }, { wch: 18 }, { wch: 18 }, { wch: 12 }, { wch: 15 }, { wch: 22 }];
        XLSX.writeFile(wb, `Transaksi_Material_${new Date().toISOString().split('T')[0]}.xlsx`);
        if (exportButton && originalHTML) { exportButton.innerHTML = originalHTML; exportButton.disabled = false; }
    } catch (error) {
        console.error('Error exporting data:', error);
        alert('Gagal mengexport data. Silakan coba lagi.');
        const exportButton = document.querySelector('[title="Export ke Excel"]') as HTMLButtonElement;
        if (exportButton) {
            exportButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg><span class="hidden sm:inline">Export</span>';
            exportButton.disabled = false;
        }
    }
};

const search = () => {
    const params: Record<string, any> = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (filterShift.value) params.shift = filterShift.value;
    if (filterDateFrom.value) params.date_from = filterDateFrom.value;
    if (filterDateTo.value) params.date_to = filterDateTo.value;
    if (filterHasReturn.value !== '') params.has_return = filterHasReturn.value;
    router.get('/transaksi', params, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchQuery.value = ''; filterShift.value = ''; filterDateFrom.value = '';
    filterDateTo.value = ''; filterHasReturn.value = ''; datePreset.value = '';
    nextTick(() => { router.get('/transaksi', {}, { preserveState: false, preserveScroll: false }); });
};

const goToPage = (page: number) => {
    router.get(`/transaksi?page=${page}`, {
        search: searchQuery.value, shift: filterShift.value,
        date_from: filterDateFrom.value, date_to: filterDateTo.value, has_return: filterHasReturn.value,
    }, { preserveState: true, preserveScroll: true });
};

const getPaginationRange = () => {
    const currentPage = props.transaksi.current_page;
    const lastPage = props.transaksi.last_page;
    const delta = 2;
    const range: (number | string)[] = [];
    for (let i = Math.max(2, currentPage - delta); i <= Math.min(lastPage - 1, currentPage + delta); i++) range.push(i);
    if (currentPage - delta > 2) range.unshift('...');
    if (currentPage + delta < lastPage - 1) range.push('...');
    range.unshift(1);
    if (lastPage > 1) range.push(lastPage);
    return range;
};

const deleteTransaksi = (id: number) => {
    if (confirm('Yakin ingin menghapus transaksi ini?')) {
        router.delete(`/transaksi/${id}`, { onSuccess: () => {}, onError: () => { alert('Gagal menghapus transaksi. Silakan coba lagi.'); } });
    }
};

const toggleSelectAll = () => {
    if (selectAll.value) { selectedItems.value = props.transaksi.data.map(item => item.id); } else { selectedItems.value = []; }
};

const toggleSelectItem = (id: number) => {
    const index = selectedItems.value.indexOf(id);
    if (index > -1) { selectedItems.value.splice(index, 1); } else { selectedItems.value.push(id); }
    selectAll.value = selectedItems.value.length === props.transaksi.data.length;
};

const deleteMultiple = () => {
    if (selectedItems.value.length === 0) { alert('Pilih minimal 1 transaksi untuk dihapus'); return; }
    if (confirm(`Yakin ingin menghapus ${selectedItems.value.length} transaksi yang dipilih?`)) {
        router.post('/transaksi/delete-multiple', { ids: selectedItems.value }, {
            onSuccess: () => { selectedItems.value = []; selectAll.value = false; },
            onError: () => { alert('Gagal menghapus transaksi. Silakan coba lagi.'); }
        });
    }
};

const openModal = () => {
    showModal.value = true; form.reset(); selectedMaterial.value = null;
    searchMaterialQuery.value = ''; searchResults.value = []; previewImages.value = [];
    form.tanggal = props.effectiveDate; form.shift = props.currentShift; form.pic = '';
};
const closeModal = () => {
    showModal.value = false; form.reset(); selectedMaterial.value = null;
    searchMaterialQuery.value = ''; searchResults.value = []; previewImages.value = [];
};

const viewDetail = (transaksi: Transaksi) => { selectedTransaksi.value = transaksi; showDetailModal.value = true; };
const closeDetailModal = () => { showDetailModal.value = false; selectedTransaksi.value = null; };

const openPengembalianModal = (transaksi: Transaksi) => {
    selectedTransaksi.value = transaksi; pengembalianForm.reset();
    pengembalianForm.transaksi_material_id = transaksi.id;
    pengembalianForm.tanggal_pengembalian = props.effectiveDate;
    pengembalianForm.pic = ''; pengembalianPreviewImages.value = [];
    showPengembalianModal.value = true;
};
const closePengembalianModal = () => {
    showPengembalianModal.value = false; selectedTransaksi.value = null;
    pengembalianForm.reset(); pengembalianPreviewImages.value = [];
};

const submitPengembalian = () => { pengembalianForm.post('/pengembalian', { onSuccess: () => { closePengembalianModal(); } }); };

const viewPengembalianHistory = async (transaksi: Transaksi) => {
    try {
        const response = await fetch(`/transaksi/${transaksi.id}/pengembalian-history`);
        const data = await response.json();
        pengembalianHistory.value = data.pengembalian;
        selectedTransaksi.value = { ...transaksi, total_pengembalian: data.totalPengembalian };
        showPengembalianHistoryModal.value = true;
    } catch { alert('Gagal memuat riwayat pengembalian'); }
};
const closePengembalianHistoryModal = () => {
    showPengembalianHistoryModal.value = false; selectedTransaksi.value = null; pengembalianHistory.value = [];
};

const searchMaterial = async () => {
    if (!searchMaterialQuery.value || searchMaterialQuery.value.length < 2) { searchResults.value = []; return; }
    isSearching.value = true;
    try {
        const response = await fetch(`/materials/search/api?query=${encodeURIComponent(searchMaterialQuery.value)}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) throw new Error("Response is not JSON");
        searchResults.value = await response.json();
    } catch { searchResults.value = []; alert('Gagal mencari material. Silakan coba lagi.'); }
    finally { isSearching.value = false; }
};

const selectMaterial = (material: Material) => {
    selectedMaterial.value = material; form.material_id = material.id;
    if (material.part_materials.length === 1) { form.part_material_id = material.part_materials[0].id; } else { form.part_material_id = null; }
    searchResults.value = []; searchMaterialQuery.value = '';
};

const handleFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);
    if (files.length === 0) return;
    if (files.length + form.foto.length > 5) { alert('Maksimal 5 foto'); target.value = ''; return; }
    isCompressingImage.value = true;
    const compressedFiles: File[] = [];
    const newPreviewImages: string[] = [];
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        compressionProgress.value = `Memproses gambar ${i + 1} dari ${files.length}...`;
        if (!file.type.startsWith('image/')) { alert(`File "${file.name}" bukan gambar yang valid`); continue; }
        try {
            const compressedFile = await compressImage(file);
            console.log(`Kompresi berhasil: ${file.name} | Asli: ${formatFileSize(file.size)} | Kompresi: ${formatFileSize(compressedFile.size)} | Hemat: ${Math.round((1 - compressedFile.size / file.size) * 100)}%`);
            compressedFiles.push(compressedFile);
            const reader = new FileReader();
            reader.onload = (e) => { newPreviewImages.push(e.target?.result as string); };
            reader.readAsDataURL(compressedFile);
        } catch (error) { console.error(`Gagal memproses ${file.name}:`, error); alert(`Gagal memproses gambar "${file.name}". Silakan coba lagi.`); }
    }
    form.foto = [...form.foto, ...compressedFiles].slice(0, 5);
    await new Promise(resolve => setTimeout(resolve, 100));
    previewImages.value = [...previewImages.value, ...newPreviewImages].slice(0, 5);
    isCompressingImage.value = false; compressionProgress.value = ''; target.value = '';
};

const handlePengembalianFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);
    if (files.length === 0) return;
    if (files.length + pengembalianForm.foto.length > 5) { alert('Maksimal 5 foto'); target.value = ''; return; }
    isCompressingImage.value = true;
    const compressedFiles: File[] = [];
    const newPreviewImages: string[] = [];
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        compressionProgress.value = `Memproses gambar ${i + 1} dari ${files.length}...`;
        if (!file.type.startsWith('image/')) { alert(`File "${file.name}" bukan gambar yang valid`); continue; }
        try {
            const compressedFile = await compressImage(file);
            console.log(`Kompresi berhasil: ${file.name} | Asli: ${formatFileSize(file.size)} | Kompresi: ${formatFileSize(compressedFile.size)} | Hemat: ${Math.round((1 - compressedFile.size / file.size) * 100)}%`);
            compressedFiles.push(compressedFile);
            const reader = new FileReader();
            reader.onload = (e) => { newPreviewImages.push(e.target?.result as string); };
            reader.readAsDataURL(compressedFile);
        } catch (error) { console.error(`Gagal memproses ${file.name}:`, error); alert(`Gagal memproses gambar "${file.name}". Silakan coba lagi.`); }
    }
    pengembalianForm.foto = [...pengembalianForm.foto, ...compressedFiles].slice(0, 5);
    await new Promise(resolve => setTimeout(resolve, 100));
    pengembalianPreviewImages.value = [...pengembalianPreviewImages.value, ...newPreviewImages].slice(0, 5);
    isCompressingImage.value = false; compressionProgress.value = ''; target.value = '';
};

const removeImage = (index: number) => { form.foto.splice(index, 1); previewImages.value.splice(index, 1); };
const removePengembalianImage = (index: number) => { pengembalianForm.foto.splice(index, 1); pengembalianPreviewImages.value.splice(index, 1); };
const submit = () => { form.post('/transaksi', { onSuccess: () => { closeModal(); } }); };

const openReturnSearchModal = () => { showReturnSearchModal.value = true; returnSearchQuery.value = ''; returnSearchResults.value = []; };
const closeReturnSearchModal = () => { showReturnSearchModal.value = false; returnSearchQuery.value = ''; returnSearchResults.value = []; };

const searchTransaksiForReturn = async () => {
    if (!returnSearchQuery.value || returnSearchQuery.value.length < 2) { returnSearchResults.value = []; return; }
    isSearchingReturn.value = true;
    try {
        const response = await fetch(`/transaksi/search-for-return?query=${encodeURIComponent(returnSearchQuery.value)}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) throw new Error("Response is not JSON");
        returnSearchResults.value = await response.json();
    } catch { returnSearchResults.value = []; alert('Gagal mencari transaksi. Silakan coba lagi.'); }
    finally { isSearchingReturn.value = false; }
};

const selectTransaksiForReturn = (item: Transaksi) => { closeReturnSearchModal(); openPengembalianModal(item); };

let searchTimer: ReturnType<typeof setTimeout> | null = null;
let returnSearchTimer: ReturnType<typeof setTimeout> | null = null;

watch(() => props.transaksi.data, () => {
    selectAll.value = selectedItems.value.length === props.transaksi.data.length && props.transaksi.data.length > 0;
}, { deep: true });

watch([searchQuery, filterShift, filterDateFrom, filterDateTo, filterHasReturn], () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { search(); }, 300);
}, { deep: true });

watch(searchMaterialQuery, () => {
    if (searchMaterialQuery.value.length >= 2) { searchMaterial(); } else { searchResults.value = []; }
});

watch(returnSearchQuery, () => {
    if (returnSearchTimer) clearTimeout(returnSearchTimer);
    if (returnSearchQuery.value.length >= 2) { returnSearchTimer = setTimeout(() => { searchTransaksiForReturn(); }, 300); }
    else { returnSearchResults.value = []; }
});
</script>

<template>
    <Head title="Transaksi Material" />
    <AppLayout :breadcrumbs="[{ title: 'Transaksi Material', href: '/transaksi' }]">
        <div class="p-6 space-y-6 bg-white dark:bg-gray-900 min-h-screen">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent flex items-center gap-3">
                        <Package class="w-8 h-8 text-blue-600" />
                        Transaksi Material
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola pengambilan dan pengembalian material</p>
                </div>
                <div class="flex gap-3">
                    <button @click="exportToExcel" title="Export ke Excel"
                        class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                        <Download class="w-4 h-4" />
                        <span class="hidden sm:inline">Export</span>
                    </button>
                    <button v-if="selectedItems.length > 0" @click="deleteMultiple"
                        class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-red-500 to-rose-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                        <Trash2 class="w-4 h-4" />
                        <span class="hidden sm:inline">Hapus ({{ selectedItems.length }})</span>
                    </button>
                    <button @click="openReturnSearchModal"
                        class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                        <Undo2 class="w-4 h-4" />
                        <span class="hidden sm:inline">Kembalikan</span>
                    </button>
                    <button @click="openModal"
                        class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl hover:shadow-lg transition-all duration-300 font-medium">
                        <Plus class="w-4 h-4" />
                        Pengambilan
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-gradient-to-br from--50 to-slate-100 dark:from-gray-800 dark:to-gray-700 rounded-2xl p-4 border border-gray-100 dark:border-gray-700 shadow-lg hover:shadow-xl transition-all duration-300">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Total Transaksi</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ statistics.total }}</p>
                </div>
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 rounded-2xl p-4 border border-orange-200 dark:border-orange-800 shadow-lg hover:shadow-xl transition-all duration-300">
                    <p class="text-xs font-semibold text-orange-600 dark:text-orange-300 uppercase flex items-center gap-1">
                        <Undo2 class="w-3.5 h-3.5" /> Pengembalian
                    </p>
                    <p class="text-2xl font-bold text-orange-700 dark:text-orange-200 mt-1">{{ statistics.with_return }}</p>
                </div>
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-4 border border-blue-200 dark:border-blue-800 shadow-lg hover:shadow-xl transition-all duration-300">
                    <p class="text-xs font-semibold text-blue-600 dark:text-blue-300 uppercase">Shift 1</p>
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-200 mt-1">{{ statistics.shift_1 }}</p>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl p-4 border border-green-200 dark:border-green-800 shadow-lg hover:shadow-xl transition-all duration-300">
                    <p class="text-xs font-semibold text-green-600 dark:text-green-300 uppercase">Shift 2</p>
                    <p class="text-2xl font-bold text-green-700 dark:text-green-200 mt-1">{{ statistics.shift_2 }}</p>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl p-4 border border-purple-200 dark:border-purple-800 shadow-lg hover:shadow-xl transition-all duration-300">
                    <p class="text-xs font-semibold text-purple-600 dark:text-purple-300 uppercase">Shift 3</p>
                    <p class="text-2xl font-bold text-purple-700 dark:text-purple-200 mt-1">{{ statistics.shift_3 }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-lg border border-gray-100 dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-4">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Pencarian</label>
                        <div class="relative">
                            <input v-model="searchQuery" type="text" placeholder="Cari transaksi, PIC, atau material..."
                                class="w-full rounded-xl border-2 border-gray-200 dark:border-gray-700 pl-10 pr-3 py-2.5 dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm" />
                            <Search class="absolute left-3 top-3 w-4 h-4 text-gray-400" />
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Shift</label>
                        <select v-model="filterShift"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm">
                            <option value="">Semua Shift</option>
                            <option :value="1">Shift 1</option>
                            <option :value="2">Shift 2</option>
                            <option :value="3">Shift 3</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Dari Tanggal</label>
                        <input v-model="filterDateFrom" type="date"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Sampai Tanggal</label>
                        <input v-model="filterDateTo" type="date"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Status</label>
                        <select v-model="filterHasReturn"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm">
                            <option value="">Semua Status</option>
                            <option value="1">Ada Pengembalian</option>
                            <option value="0">Tidak ada Pengembalian</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <span class="text-xs text-gray-500 dark:text-gray-400 self-center mr-2 font-semibold">Quick Filter:</span>
                    <button @click="setDatePreset('today')" :class="['px-3 py-1.5 text-xs rounded-xl font-medium transition-all duration-300',
                        datePreset === 'today' ? 'bg-blue-600 text-white shadow-md' : 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-800/40']">Hari Ini</button>
                    <button @click="setDatePreset('yesterday')" :class="['px-3 py-1.5 text-xs rounded-xl font-medium transition-all duration-300',
                        datePreset === 'yesterday' ? 'bg-blue-600 text-white shadow-md' : 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-800/40']">Kemarin</button>
                    <button @click="setDatePreset('last7')" :class="['px-3 py-1.5 text-xs rounded-xl font-medium transition-all duration-300',
                        datePreset === 'last7' ? 'bg-purple-600 text-white shadow-md' : 'bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-800/40']">7 Hari</button>
                    <button @click="setDatePreset('last30')" :class="['px-3 py-1.5 text-xs rounded-xl font-medium transition-all duration-300',
                        datePreset === 'last30' ? 'bg-indigo-600 text-white shadow-md' : 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-800/40']">30 Hari</button>
                    <button @click="setDatePreset('thisMonth')" :class="['px-3 py-1.5 text-xs rounded-xl font-medium transition-all duration-300',
                        datePreset === 'thisMonth' ? 'bg-indigo-600 text-white shadow-md' : 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-800/40']">Bulan Ini</button>
                    <button v-if="searchQuery || filterShift || filterDateFrom || filterDateTo || filterHasReturn"
                        @click="resetFilters" class="ml-auto px-4 py-1.5 text-xs bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium flex items-center gap-1">
                        <X class="w-3 h-3" /> Reset
                    </button>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-700 dark:to-gray-700">
                            <tr>
                                <th class="px-3 py-2.5 text-left">
                                    <input type="checkbox" v-model="selectAll" @change="toggleSelectAll"
                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                </th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">ID Transaksi</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Tanggal</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Shift</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">PIC</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Material</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">QTY</th>
                                <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Foto</th>
                                <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="item in transaksi.data" :key="item.id">
                                <tr :class="['hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors',
                                    selectedItems.includes(item.id) ? 'bg-blue-50 dark:bg-blue-900/10' : '',
                                    item.total_pengembalian && item.total_pengembalian > 0 ? 'border-l-4 border-orange-400' : '']">
                                    <td class="px-3 py-2.5">
                                        <input type="checkbox" :checked="selectedItems.includes(item.id)" @change="toggleSelectItem(item.id)"
                                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ item.transaksi_id }}</span>
                                            <span v-if="item.total_pengembalian && item.total_pengembalian > 0"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                                <Undo2 class="w-2.5 h-2.5" />
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ formatDate(item.tanggal) }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ formatTime(item.created_at) }}</div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-bold',
                                            item.shift === 1 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200' :
                                            item.shift === 2 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200' :
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200']">
                                            Shift {{ item.shift }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-1.5">
                                            <User class="w-3.5 h-3.5 text-gray-400" />
                                            <span class="text-xs font-semibold text-gray-900 dark:text-white">{{ item.pic }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ item.material.nama_material }}</div>
                                        <div class="text-xs text-gray-400">{{ item.material.material_id }}</div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">
                                                {{ formatQty(item.total_pengembalian && item.total_pengembalian > 0 ? getSisaQty(item) : item.qty) }} {{ item.material.satuan }}
                                            </span>
                                            <button v-if="!item.total_pengembalian || item.total_pengembalian === 0"
                                                @click="openPengembalianModal(item)"
                                                class="p-1 text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20 rounded-lg transition-colors" title="Input Pengembalian">
                                                <Undo2 class="w-3.5 h-3.5" />
                                            </button>
                                            <button v-if="item.total_pengembalian && item.total_pengembalian > 0"
                                                @click="toggleExpandRow(item.id)"
                                                class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                                <ChevronDown v-if="!expandedRows.includes(item.id)" class="w-3.5 h-3.5 text-gray-400" />
                                                <ChevronUp v-else class="w-3.5 h-3.5 text-gray-400" />
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span v-if="item.foto && item.foto.length > 0"
                                            class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-700 cursor-pointer"
                                            @click="openImageModal(item.foto.map(f => `/storage/${f}`), 0)">
                                            <Camera class="w-3 h-3" /> {{ item.foto.length }}
                                        </span>
                                        <span v-else class="text-xs text-gray-400">-</span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="viewDetail(item)"
                                                class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="Lihat Detail">
                                                <Eye class="w-4 h-4" />
                                            </button>
                                            <button @click="deleteTransaksi(item.id)"
                                                class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="expandedRows.includes(item.id) && item.total_pengembalian && item.total_pengembalian > 0">
                                    <td colspan="9" class="px-4 py-4 bg-orange-50 dark:bg-orange-900/10">
                                        <div class="space-y-3">
                                            <div class="flex items-center justify-between">
                                                <div class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase flex items-center gap-2">
                                                    <Undo2 class="w-4 h-4 text-orange-600" /> Detail Pengembalian
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700">
                                                        <Calendar class="w-3.5 h-3.5 text-purple-600" />
                                                        <span class="font-semibold">{{ item.tanggal_pengembalian_terakhir ? formatDate(item.tanggal_pengembalian_terakhir) : '-' }}</span>
                                                    </div>
                                                    <button @click="viewPengembalianHistory(item)"
                                                        class="text-xs px-3 py-1.5 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl hover:shadow-md transition-all duration-300 font-medium flex items-center gap-1">
                                                        <History class="w-3 h-3" /> Lihat Detail
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-3 border border-blue-200 dark:border-blue-800">
                                                    <div class="text-xs text-blue-600 dark:text-blue-300 font-semibold uppercase">Qty Pengambilan</div>
                                                    <div class="text-lg font-bold text-blue-700 dark:text-blue-200 mt-0.5">{{ formatQty(item.qty) }} {{ item.material.satuan }}</div>
                                                </div>
                                                <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 rounded-xl p-3 border border-orange-200 dark:border-orange-800">
                                                    <div class="text-xs text-orange-600 dark:text-orange-300 font-semibold uppercase">Total Dikembalikan</div>
                                                    <div class="text-lg font-bold text-orange-700 dark:text-orange-200 mt-0.5">{{ formatQty(item.total_pengembalian) }} {{ item.material.satuan }}</div>
                                                </div>
                                                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-3 border border-green-200 dark:border-green-800">
                                                    <div class="text-xs text-green-600 dark:text-green-300 font-semibold uppercase">Qty Terpakai</div>
                                                    <div class="text-lg font-bold text-green-700 dark:text-green-200 mt-0.5">{{ formatQty(getSisaQty(item)) }} {{ item.material.satuan }}</div>
                                                </div>
                                            </div>
                                            <button @click="openPengembalianModal(item)"
                                                class="w-full px-4 py-2 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl hover:shadow-md transition-all duration-300 flex items-center justify-center gap-2 text-sm font-medium"
                                                :disabled="getSisaQty(item) <= 0" :class="{ 'opacity-50 cursor-not-allowed': getSisaQty(item) <= 0 }">
                                                <Undo2 class="w-4 h-4" /> Input Pengembalian Lagi
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>

                        <tfoot v-if="transaksi.data.length > 0" class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-700 dark:to-gray-700">
                            <tr>
                                <td colspan="6" class="px-3 py-2 text-xs font-bold text-gray-700 dark:text-gray-300">Total: {{ transaksi.total }} transaksi</td>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div v-if="transaksi.data.length === 0" class="text-center py-16">
                    <Package class="w-20 h-20 mx-auto mb-4 text-gray-400 opacity-50" />
                    <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300">Tidak ada transaksi</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Mulai dengan menambahkan transaksi pengambilan material</p>
                </div>
            </div>

            <div v-if="transaksi.last_page > 1" class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                    Halaman {{ transaksi.current_page }} dari {{ transaksi.last_page }}
                </div>
                <div class="flex items-center gap-2">
                    <button @click="goToPage(transaksi.current_page - 1)" :disabled="transaksi.current_page === 1"
                        class="p-2 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <template v-for="(page, index) in getPaginationRange()" :key="index">
                        <button v-if="typeof page === 'number'" @click="goToPage(page)"
                            :class="['min-w-[36px] h-9 px-3 rounded-xl text-xs font-bold transition-all duration-300',
                                page === transaksi.current_page ? 'bg-gradient-to-r from-blue-500 to-indigo-500 text-white shadow-md' : 'border-2 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700']">
                            {{ page }}
                        </button>
                        <span v-else class="px-1 text-gray-400 text-xs">...</span>
                    </template>
                    <button @click="goToPage(transaksi.current_page + 1)" :disabled="transaksi.current_page === transaksi.last_page"
                        class="p-2 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                    Total {{ transaksi.total }} data
                </div>
            </div>
        </div>

        <div v-if="showDetailModal && selectedTransaksi" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Detail Transaksi</h2>
                    <button @click="closeDetailModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1"><Hash class="w-3.5 h-3.5" /> ID Transaksi</div>
                            <div class="font-bold text-lg text-gray-900 dark:text-white">{{ selectedTransaksi.transaksi_id }}</div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1"><Calendar class="w-3.5 h-3.5" /> Tanggal</div>
                            <div class="font-bold text-gray-900 dark:text-white">{{ formatDate(selectedTransaksi.tanggal) }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Shift</div>
                            <span :class="['inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold',
                                selectedTransaksi.shift === 1 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200' :
                                selectedTransaksi.shift === 2 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200' :
                                'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200']">
                                Shift {{ selectedTransaksi.shift }}
                            </span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1"><User class="w-3.5 h-3.5" /> PIC</div>
                            <div class="font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.pic }}</div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1"><User class="w-3.5 h-3.5" /> Dibuat Oleh</div>
                            <div class="font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.user?.name || '-' }}</div>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-3 flex items-center gap-2 uppercase"><Package class="w-4 h-4 text-blue-600" /> Informasi Material</h3>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div><div class="text-xs text-gray-500 dark:text-gray-400">ID Material</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.material.material_id }}</div></div>
                                <div><div class="text-xs text-gray-500 dark:text-gray-400">Nama Material</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.material.nama_material }}</div></div>
                                <div><div class="text-xs text-gray-500 dark:text-gray-400">Tipe Material</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.material.material_type }}</div></div>
                                <div><div class="text-xs text-gray-500 dark:text-gray-400">Satuan</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.material.satuan }}</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-3 uppercase">Quantity</h3>
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 text-center border border-blue-200 dark:border-blue-800">
                            <div class="text-4xl font-black text-blue-700 dark:text-blue-200">{{ formatQty(selectedTransaksi.qty) }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ selectedTransaksi.material.satuan }}</div>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Waktu Input</div>
                        <div class="text-xs font-bold text-gray-900 dark:text-white">{{ formatDateTime(selectedTransaksi.created_at) }}</div>
                    </div>
                    <div v-if="selectedTransaksi.foto && selectedTransaksi.foto.length > 0" class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-3 flex items-center gap-2 uppercase"><Camera class="w-4 h-4" /> Foto ({{ selectedTransaksi.foto.length }})</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div v-for="(foto, index) in selectedTransaksi.foto" :key="index"
                                @click="openImageModal(selectedTransaksi.foto.map(f => `/storage/${f}`), index)"
                                class="relative aspect-square rounded-xl overflow-hidden hover:opacity-80 transition-opacity cursor-pointer shadow-md">
                                <img :src="`/storage/${foto}`" :alt="`Foto ${index + 1}`" class="w-full h-full object-cover" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 p-6 border-t border-gray-200 dark:border-gray-700">
                    <button @click="closeDetailModal" class="flex-1 px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Tutup</button>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Input Pengambilan Material</h2>
                    <button @click="closeModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Cari Material</label>
                        <div class="relative">
                            <input v-model="searchMaterialQuery" type="text" placeholder="Ketik ID atau nama material (min 2 karakter)..."
                                class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm" />
                            <Search class="absolute right-3 top-3 w-5 h-5 text-gray-400" />
                        </div>
                        <div v-if="searchResults.length > 0" class="mt-2 border-2 border-gray-200 dark:border-gray-700 rounded-xl max-h-60 overflow-y-auto">
                            <button v-for="material in searchResults" :key="material.id" type="button" @click="selectMaterial(material)"
                                class="w-full text-left px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-900/20 border-b border-gray-100 dark:border-gray-700 last:border-b-0 transition-colors">
                                <div class="text-xs font-bold text-gray-900 dark:text-white">{{ material.material_id }} - {{ material.nama_material }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ material.material_type }} | {{ material.satuan }}</div>
                            </button>
                        </div>
                        <div v-if="selectedMaterial" class="mt-3 p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
                            <div class="flex items-start gap-3">
                                <Package class="w-5 h-5 text-blue-600 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedMaterial.material_id }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">{{ selectedMaterial.nama_material }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ selectedMaterial.material_type }} | {{ selectedMaterial.satuan }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-if="form.errors.material_id" class="mt-2 text-xs text-red-600 font-semibold">{{ form.errors.material_id }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tanggal</label>
                            <input v-model="form.tanggal" type="date" required
                                class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm" />
                            <div v-if="form.errors.tanggal" class="mt-1 text-xs text-red-600 font-semibold">{{ form.errors.tanggal }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Shift</label>
                            <select v-model="form.shift" required
                                class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm">
                                <option :value="1">Shift 1</option><option :value="2">Shift 2</option><option :value="3">Shift 3</option>
                            </select>
                            <div v-if="form.errors.shift" class="mt-1 text-xs text-red-600 font-semibold">{{ form.errors.shift }}</div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300"><User class="w-4 h-4 inline-block mr-1" /> PIC</label>
                        <input v-model="form.pic" type="text" placeholder="Nama orang yang mengambil material" required
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm" />
                        <div v-if="form.errors.pic" class="mt-1 text-xs text-red-600 font-semibold">{{ form.errors.pic }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Quantity <span v-if="selectedMaterial" class="text-gray-400 font-normal">({{ selectedMaterial.satuan }})</span></label>
                        <input v-model="form.qty" type="number" step="0.01" min="0.01" placeholder="0.00" required
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm" />
                        <div v-if="form.errors.qty" class="mt-1 text-xs text-red-600 font-semibold">{{ form.errors.qty }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Foto (Opsional)</label>
                        <div class="flex items-center gap-3">
                            <label :class="['cursor-pointer flex items-center gap-2 px-4 py-2.5 text-white rounded-xl font-medium transition-all duration-300',
                                isCompressingImage ? 'bg-gray-400 cursor-not-allowed' : 'bg-gradient-to-r from-blue-500 to-indigo-500 hover:shadow-md']">
                                <Loader2 v-if="isCompressingImage" class="w-4 h-4 animate-spin" />
                                <Camera v-else class="w-4 h-4" />
                                {{ isCompressingImage ? 'Memproses...' : 'Pilih Foto' }}
                                <input type="file" accept="image/*" multiple @change="handleFileChange" class="hidden" :disabled="form.foto.length >= 5 || isCompressingImage" />
                            </label>
                            <span class="text-xs text-gray-500 font-semibold">{{ form.foto.length }}/5 foto</span>
                        </div>
                        <div v-if="isCompressingImage" class="mt-2 text-xs text-blue-600 flex items-center gap-2 font-semibold"><Loader2 class="w-3 h-3 animate-spin" /> {{ compressionProgress }}</div>
                        <div v-if="previewImages.length > 0" class="mt-3 grid grid-cols-5 gap-2">
                            <div v-for="(image, index) in previewImages" :key="index" class="relative aspect-square">
                                <img :src="image" class="w-full h-full object-cover rounded-xl" />
                                <button type="button" @click="removeImage(index)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center hover:bg-red-600 transition-colors text-xs font-bold">×</button>
                            </div>
                        </div>
                        <div v-if="form.errors.foto" class="mt-2 text-xs text-red-600 font-semibold">{{ form.errors.foto }}</div>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" :disabled="form.processing || !selectedMaterial || isCompressingImage"
                            class="flex-1 px-6 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl hover:shadow-lg disabled:from-gray-400 disabled:to-gray-400 disabled:cursor-not-allowed transition-all duration-300 font-medium">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
                        </button>
                        <button type="button" @click="closeModal" :disabled="isCompressingImage"
                            class="px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Batal</button>
                    </div>
                </form>
            </div>
        </div>
        <div v-if="showReturnSearchModal" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Undo2 class="w-5 h-5 text-orange-600" /> Cari Transaksi untuk Pengembalian</h2>
                    <button @click="closeReturnSearchModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Cari berdasarkan Nama Material atau PIC</label>
                        <div class="relative">
                            <input v-model="returnSearchQuery" type="text" placeholder="Ketik untuk mencari (min 2 karakter)..." autofocus
                                class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all text-sm" />
                            <div class="absolute right-3 top-3">
                                <Loader2 v-if="isSearchingReturn" class="w-5 h-5 text-orange-500 animate-spin" />
                                <Search v-else class="w-5 h-5 text-gray-400" />
                            </div>
                        </div>
                    </div>
                    <div v-if="returnSearchQuery.length < 2 && returnSearchResults.length === 0" class="text-center py-10">
                        <Search class="w-12 h-12 mx-auto mb-3 text-gray-300 opacity-50" />
                        <p class="text-xs text-gray-400 mt-1">Pencarian mencakup ID transaksi, nama material, dan PIC</p>
                    </div>
                    <div v-if="returnSearchResults.length > 0" class="border-2 border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700">
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">{{ returnSearchResults.length }} hasil ditemukan</span>
                        </div>
                        <div class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                            <button v-for="item in returnSearchResults" :key="item.id" type="button" @click="selectTransaksiForReturn(item)"
                                class="w-full text-left px-4 py-3.5 hover:bg-orange-50 dark:hover:bg-orange-900/10 transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ item.transaksi_id }}</span>
                                            <span :class="['px-1.5 py-0.5 rounded text-xs font-bold',
                                                item.shift === 1 ? 'bg-blue-100 text-blue-700' : item.shift === 2 ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700']">
                                                Shift {{ item.shift }}
                                            </span>
                                            <span v-if="item.total_pengembalian && item.total_pengembalian > 0"
                                                class="px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded text-xs font-bold flex items-center gap-0.5">
                                                <Undo2 class="w-2.5 h-2.5" /> Sebagian
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5 truncate">{{ item.material.nama_material }} <span class="text-gray-400">({{ item.material.material_id }})</span></div>
                                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                            <span class="flex items-center gap-1"><User class="w-3 h-3" /> {{ item.pic }}</span>
                                            <span class="flex items-center gap-1"><Calendar class="w-3 h-3" /> {{ formatDate(item.tanggal) }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <template v-if="!item.total_pengembalian || item.total_pengembalian === 0">
                                            <div class="text-xs font-bold text-orange-600">{{ formatQty(item.qty) }} {{ item.material.satuan }}</div>
                                            <div class="text-xs text-gray-400">Tersedia</div>
                                        </template>
                                        <template v-else>
                                            <div class="text-xs font-bold text-orange-600">{{ formatQty(getSisaQty(item)) }} {{ item.material.satuan }}</div>
                                            <div class="text-xs text-gray-400">Sisa dari {{ formatQty(item.qty) }}</div>
                                        </template>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                    <div v-if="returnSearchQuery.length >= 2 && !isSearchingReturn && returnSearchResults.length === 0" class="text-center py-10">
                        <Package class="w-12 h-12 mx-auto mb-3 text-gray-300 opacity-50" />
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold">Tidak ditemukan transaksi yang sesuai</p>
                        <p class="text-xs text-gray-400 mt-1">Pastikan transaksi masih memiliki sisa qty untuk dikembalikan</p>
                    </div>
                </div>
                <div class="flex gap-3 p-6 border-t border-gray-200 dark:border-gray-700">
                    <button @click="closeReturnSearchModal" class="flex-1 px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Batal</button>
                </div>
            </div>
        </div>
        <div v-if="showPengembalianModal && selectedTransaksi" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Undo2 class="w-5 h-5 text-orange-600" /> Input Pengembalian Material</h2>
                    <button @click="closePengembalianModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitPengembalian" class="p-6 space-y-5">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <div class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-3">Transaksi Pengambilan</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><div class="text-xs text-gray-400">ID Transaksi</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.transaksi_id }}</div></div>
                            <div><div class="text-xs text-gray-400">PIC Pengambilan</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.pic }}</div></div>
                            <div><div class="text-xs text-gray-400">Material</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.material.nama_material }}</div></div>
                            <div><div class="text-xs text-gray-400">Qty Pengambilan</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ formatQty(selectedTransaksi.qty) }} {{ selectedTransaksi.material.satuan }}</div></div>
                            <div><div class="text-xs text-gray-400">Sisa Bisa Dikembalikan</div><div class="text-xs font-bold text-orange-600">{{ formatQty(selectedTransaksi.sisa_pengembalian || selectedTransaksi.qty) }} {{ selectedTransaksi.material.satuan }}</div></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Tanggal Pengembalian</label>
                        <input v-model="pengembalianForm.tanggal_pengembalian" type="date" required
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all text-sm" />
                        <div v-if="pengembalianForm.errors.tanggal_pengembalian" class="mt-1 text-xs text-red-600 font-semibold">{{ pengembalianForm.errors.tanggal_pengembalian }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300"><User class="w-4 h-4 inline-block mr-1" /> PIC Pengembalian</label>
                        <input v-model="pengembalianForm.pic" type="text" placeholder="Nama orang yang mengembalikan material" required
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all text-sm" />
                        <div v-if="pengembalianForm.errors.pic" class="mt-1 text-xs text-red-600 font-semibold">{{ pengembalianForm.errors.pic }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Quantity Dikembalikan ({{ selectedTransaksi.material.satuan }})</label>
                        <input v-model="pengembalianForm.qty_pengembalian" type="number" step="0.01" min="0.01" :max="selectedTransaksi.sisa_pengembalian || selectedTransaksi.qty" placeholder="0.00" required
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all text-sm" />
                        <div v-if="pengembalianForm.errors.qty_pengembalian" class="mt-1 text-xs text-red-600 font-semibold">{{ pengembalianForm.errors.qty_pengembalian }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Keterangan (Opsional)</label>
                        <textarea v-model="pengembalianForm.keterangan" rows="3" placeholder="Alasan pengembalian atau keterangan lainnya..."
                            class="w-full px-3 py-2.5 border-2 border-gray-200 dark:border-gray-700 rounded-xl dark:bg-gray-700 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all text-sm"></textarea>
                        <div v-if="pengembalianForm.errors.keterangan" class="mt-1 text-xs text-red-600 font-semibold">{{ pengembalianForm.errors.keterangan }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Foto (Opsional)</label>
                        <div class="flex items-center gap-3">
                            <label :class="['cursor-pointer flex items-center gap-2 px-4 py-2.5 text-white rounded-xl font-medium transition-all duration-300',
                                isCompressingImage ? 'bg-gray-400 cursor-not-allowed' : 'bg-gradient-to-r from-orange-500 to-amber-500 hover:shadow-md']">
                                <Loader2 v-if="isCompressingImage" class="w-4 h-4 animate-spin" /><Camera v-else class="w-4 h-4" />
                                {{ isCompressingImage ? 'Memproses...' : 'Pilih Foto' }}
                                <input type="file" accept="image/*" multiple @change="handlePengembalianFileChange" class="hidden" :disabled="pengembalianForm.foto.length >= 5 || isCompressingImage" />
                            </label>
                            <span class="text-xs text-gray-500 font-semibold">{{ pengembalianForm.foto.length }}/5 foto</span>
                        </div>
                        <div v-if="isCompressingImage" class="mt-2 text-xs text-orange-600 flex items-center gap-2 font-semibold"><Loader2 class="w-3 h-3 animate-spin" /> {{ compressionProgress }}</div>
                        <div v-if="pengembalianPreviewImages.length > 0" class="mt-3 grid grid-cols-5 gap-2">
                            <div v-for="(image, index) in pengembalianPreviewImages" :key="index" class="relative aspect-square">
                                <img :src="image" class="w-full h-full object-cover rounded-xl" />
                                <button type="button" @click="removePengembalianImage(index)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center hover:bg-red-600 transition-colors text-xs font-bold">×</button>
                            </div>
                        </div>
                        <div v-if="pengembalianForm.errors.foto" class="mt-2 text-xs text-red-600 font-semibold">{{ pengembalianForm.errors.foto }}</div>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" :disabled="pengembalianForm.processing || isCompressingImage"
                            class="flex-1 px-6 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl hover:shadow-lg disabled:from-gray-400 disabled:to-gray-400 disabled:cursor-not-allowed transition-all duration-300 font-medium">
                            {{ pengembalianForm.processing ? 'Menyimpan...' : 'Simpan Pengembalian' }}
                        </button>
                        <button type="button" @click="closePengembalianModal" :disabled="isCompressingImage"
                            class="px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Batal</button>
                    </div>
                </form>
            </div>
        </div>
        <div v-if="showPengembalianHistoryModal && selectedTransaksi" class="fixed inset-0 backdrop-blur-sm bg-white/30 dark:bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><History class="w-5 h-5 text-purple-600" /> Riwayat Pengembalian</h2>
                    <button @click="closePengembalianHistoryModal" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 space-y-6">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <div class="grid grid-cols-3 gap-4">
                            <div><div class="text-xs text-gray-400">ID Transaksi</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.transaksi_id }}</div></div>
                            <div><div class="text-xs text-gray-400">Qty Pengambilan</div><div class="text-xs font-bold text-gray-900 dark:text-white">{{ formatQty(selectedTransaksi.qty) }} {{ selectedTransaksi.material.satuan }}</div></div>
                            <div><div class="text-xs text-gray-400">Total Dikembalikan</div><div class="text-xs font-bold text-orange-600">{{ formatQty(selectedTransaksi.total_pengembalian || 0) }} {{ selectedTransaksi.material.satuan }}</div></div>
                        </div>
                    </div>
                    <div v-if="pengembalianHistory.length === 0" class="text-center py-12">
                        <Undo2 class="w-16 h-16 mx-auto mb-4 text-gray-300 opacity-50" />
                        <h3 class="text-lg font-bold text-gray-600 dark:text-gray-300">Belum ada pengembalian</h3>
                        <p class="text-xs text-gray-400 mt-1">Belum ada pengembalian untuk transaksi ini</p>
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="pengembalian in pengembalianHistory" :key="pengembalian.id"
                            class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:shadow-md transition-all duration-300">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <div class="text-xs font-bold text-gray-900 dark:text-white">{{ selectedTransaksi.material.nama_material }}</div>
                                    <div class="text-xs text-gray-400">{{ formatDate(pengembalian.tanggal_pengembalian) }}</div>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <User class="w-3 h-3 text-blue-600" />
                                        <span class="text-xs font-bold text-blue-600">{{ pengembalian.pic }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-black text-orange-600">{{ formatQty(pengembalian.qty_pengembalian) }} {{ selectedTransaksi.material.satuan }}</div>
                                    <div class="text-xs text-gray-400">Diinput: {{ pengembalian.user.name }}</div>
                                </div>
                            </div>
                            <div v-if="pengembalian.keterangan" class="text-xs text-gray-600 dark:text-gray-400 mb-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">{{ pengembalian.keterangan }}</div>
                            <div v-if="pengembalian.foto && pengembalian.foto.length > 0" class="grid grid-cols-5 gap-2">
                                <div v-for="(foto, index) in pengembalian.foto" :key="index"
                                    @click="openImageModal(pengembalian.foto.map(f => `/storage/${f}`), index)"
                                    class="relative aspect-square rounded-xl overflow-hidden hover:opacity-80 transition-opacity cursor-pointer shadow-sm">
                                    <img :src="`/storage/${foto}`" :alt="`Foto ${index + 1}`" class="w-full h-full object-cover" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 p-6 border-t border-gray-200 dark:border-gray-700">
                    <button @click="closePengembalianHistoryModal" class="flex-1 px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all duration-300 font-medium">Tutup</button>
                </div>
            </div>
        </div>
        <div v-if="showImageModal" class="fixed inset-0 backdrop-blur-sm bg-black/80 flex items-center justify-center z-50 p-4">
            <div class="relative w-full max-w-6xl h-full max-h-[90vh] flex items-center justify-center">
                <button @click="closeImageModal" class="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 text-white rounded-xl transition-colors z-10"><X class="w-6 h-6" /></button>
                <button v-if="currentImageIndex > 0" @click="prevImage" class="absolute left-4 p-3 bg-white/10 hover:bg-white/20 text-white rounded-xl transition-colors z-10"><ChevronLeft class="w-6 h-6" /></button>
                <div class="flex items-center justify-center w-full h-full">
                    <img :src="currentImageList[currentImageIndex]" class="max-w-full max-h-full object-contain rounded-2xl" :alt="`Foto ${currentImageIndex + 1}`" />
                </div>
                <button v-if="currentImageIndex < currentImageList.length - 1" @click="nextImage" class="absolute right-4 p-3 bg-white/10 hover:bg-white/20 text-white rounded-xl transition-colors z-10"><ChevronRight class="w-6 h-6" /></button>
                <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 px-4 py-2 bg-white/10 text-white rounded-xl text-xs font-semibold">{{ currentImageIndex + 1 }} / {{ currentImageList.length }}</div>
            </div>
        </div>
    </AppLayout>
</template>
