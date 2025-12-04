<!-- eslint-disable @typescript-eslint/no-unused-vars -->
<!-- eslint-disable prefer-const -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Calendar, Plus, Trash2, Edit2, X, Filter, ArrowUpDown, Upload, Check, Trash, Package, Search, Loader } from 'lucide-vue-next';
import axios from 'axios';

interface OutputData {
    id: number | null;
    type: string;
    penanggung_jawab: string;
    sap_no: string;
    product_unit: string;
    qty_day: number;
    stock_date: string;
    out_shift1: number;
    out_shift2: number;
    out_shift3: number;
    ng_shift1: number;
    ng_shift2: number;
    ng_shift3: number;
    total: number;
}

interface Material {
    id?: number;
    sap_no: string;
    material_name?: string;
    qty_per_unit?: number;
    qty_unit_reference?: number;
    bl_type?: string;
}

interface Props {
    outputData: OutputData[];
    selectedDate: string;
}

const props = defineProps<Props>();

const selectedDate = ref(props.selectedDate);
const outputData = ref<OutputData[]>(props.outputData);
const savingRows = ref<Set<number>>(new Set());

const filterType = ref('');
const filterPenanggungJawab = ref('');
const sortBy = ref<'none' | 'type' | 'penanggung_jawab'>('none');
const sortOrder = ref<'asc' | 'desc'>('asc');
const showFilterPanel = ref(false);

const showModal = ref(false);
const modalMode = ref<'add' | 'edit'>('add');
const editingOutput = ref<OutputData | null>(null);
const modalType = ref('');
const modalPenanggungJawab = ref('');

const showImportModal = ref(false);
const importFiles = ref<File[]>([]);
const importPreview = ref<any[]>([]);
const isProcessing = ref(false);
const importSummary = ref({ total: 0, matched: 0, unmatched: 0 });
const currentProcessingFile = ref<string>('');

const selectedRows = ref<Set<number>>(new Set());

const showBomModal = ref(false);
const selectedOutputForBom = ref<OutputData | null>(null);
const bomMaterials = ref<Material[]>([]);
const availableMaterials = ref<Material[]>([]);
const searchMaterial = ref('');
const showMaterialDropdown = ref(false);
const isLoadingBom = ref(false);
const isSavingBom = ref(false);

let saveTimeouts: { [key: number]: ReturnType<typeof setTimeout> } = {};

const changeDate = () => {
    router.get('/output', { date: selectedDate.value }, { preserveState: false });
};

const uniqueTypes = computed(() => {
    const types = new Set<string>();
    outputData.value.forEach(item => {
        if (item.type) types.add(item.type);
    });
    return Array.from(types).sort();
});

const uniquePenanggungJawab = computed(() => {
    const pjs = new Set<string>();
    outputData.value.forEach(item => {
        if (item.penanggung_jawab) pjs.add(item.penanggung_jawab);
    });
    return Array.from(pjs).sort();
});

const clearFilters = () => {
    filterType.value = '';
    filterPenanggungJawab.value = '';
    sortBy.value = 'none';
    sortOrder.value = 'asc';
};

const hasActiveFilters = computed(() => {
    return filterType.value !== '' || filterPenanggungJawab.value !== '' || sortBy.value !== 'none';
});

const isAllSelected = computed(() =>
    selectedRows.value.size > 0 && selectedRows.value.size === Object.values(groupedData.value).flat().length
);

const groupedData = computed(() => {
    let filteredData = outputData.value;
    if (filterType.value) {
        filteredData = filteredData.filter(item => item.type === filterType.value);
    }
    if (filterPenanggungJawab.value) {
        filteredData = filteredData.filter(item => item.penanggung_jawab === filterPenanggungJawab.value);
    }
    const groups: { [key: string]: OutputData[] } = {};
    const groupOrder: string[] = [];

    filteredData.forEach(item => {
        const groupKey = `${item.type || 'NO TYPE'}|||${item.penanggung_jawab || 'NO PJ'}`;

        if (!groups[groupKey]) {
            groups[groupKey] = [];
            groupOrder.push(groupKey);
        }
        groups[groupKey].push(item);
    });

    if (sortBy.value !== 'none') {
        groupOrder.sort((a, b) => {
            const [typeA, pjA] = a.split('|||');
            const [typeB, pjB] = b.split('|||');

            let compareValue = 0;

            if (sortBy.value === 'type') {
                compareValue = typeA.localeCompare(typeB);
            } else if (sortBy.value === 'penanggung_jawab') {
                compareValue = pjA.localeCompare(pjB);
            }

            return sortOrder.value === 'asc' ? compareValue : -compareValue;
        });
    }

    const orderedGroups: { [key: string]: OutputData[] } = {};
    groupOrder.forEach(groupKey => {
        orderedGroups[groupKey] = groups[groupKey];
    });

    return orderedGroups;
});

const toggleSort = (field: 'type' | 'penanggung_jawab') => {
    if (sortBy.value === field) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortOrder.value = 'asc';
    }
};

const calculateTotal = (output: OutputData) => {
    output.total = (output.out_shift1 || 0) + (output.out_shift2 || 0) + (output.out_shift3 || 0);
};

const saveRow = async (output: OutputData, outputIndex: number) => {
    calculateTotal(output);
    savingRows.value.add(outputIndex);

    try {
        const saveData = {
            id: output.id,
            type: output.type || '',
            penanggung_jawab: output.penanggung_jawab || '',
            sap_no: output.sap_no || '',
            product_unit: output.product_unit || '',
            qty_day: output.qty_day || 0,
            stock_date: output.stock_date,
            out_shift1: output.out_shift1 || 0,
            out_shift2: output.out_shift2 || 0,
            out_shift3: output.out_shift3 || 0,
            ng_shift1: output.ng_shift1 || 0,
            ng_shift2: output.ng_shift2 || 0,
            ng_shift3: output.ng_shift3 || 0,
        };

        const response = await axios.post('/stock/output/update', saveData);

        if (response.data.data && response.data.data.id) {
            output.id = response.data.data.id;
        }

        savingRows.value.delete(outputIndex);
        return true;
    } catch (error: any) {
        console.error('Save error:', error);
        savingRows.value.delete(outputIndex);
        return false;
    }
};

const autoSaveNumber = (output: OutputData, outputIndex: number) => {
    calculateTotal(output);

    if (saveTimeouts[outputIndex]) {
        clearTimeout(saveTimeouts[outputIndex]);
    }

    savingRows.value.add(outputIndex);

    saveTimeouts[outputIndex] = setTimeout(async () => {
        await saveRow(output, outputIndex);
    }, 500);
};

const saveOnBlur = (output: OutputData, outputIndex: number) => {
    if (saveTimeouts[outputIndex]) {
        clearTimeout(saveTimeouts[outputIndex]);
    }
    saveRow(output, outputIndex);
};

const openAddModal = () => {
    modalMode.value = 'add';
    editingOutput.value = null;
    modalType.value = '';
    modalPenanggungJawab.value = '';
    showModal.value = true;
};

const openEditModal = (output: OutputData) => {
    modalMode.value = 'edit';
    editingOutput.value = output;
    modalType.value = output.type;
    modalPenanggungJawab.value = output.penanggung_jawab;
    showModal.value = true;
};

const saveFromModal = async () => {
    if (modalMode.value === 'add') {
        try {
            const response = await axios.post('/stock/output/update', {
                id: null,
                type: modalType.value,
                penanggung_jawab: modalPenanggungJawab.value,
                sap_no: '',
                product_unit: '',
                qty_day: 0,
                stock_date: selectedDate.value,
                out_shift1: 0,
                out_shift2: 0,
                out_shift3: 0,
                ng_shift1: 0,
                ng_shift2: 0,
                ng_shift3: 0,
            });

            const newRow: OutputData = {
                id: response.data.data.id,
                type: modalType.value,
                penanggung_jawab: modalPenanggungJawab.value,
                sap_no: '',
                product_unit: '',
                qty_day: 0,
                stock_date: selectedDate.value,
                out_shift1: 0,
                out_shift2: 0,
                out_shift3: 0,
                ng_shift1: 0,
                ng_shift2: 0,
                ng_shift3: 0,
                total: 0
            };

            outputData.value.push(newRow);
            closeModal();
        } catch (error: any) {
            console.error('Add row error:', error);
            alert('Error adding row: ' + (error.response?.data?.message || error.message));
        }
    } else {
        if (!editingOutput.value) return;
        const clickedRowId = editingOutput.value.id;
        let rowsInGroup: OutputData[] = [];

        for (const [groupType, items] of Object.entries(groupedData.value)) {
            const foundInGroup = items.find(item => item.id === clickedRowId);
            if (foundInGroup) {
                rowsInGroup = items;
                break;
            }
        }

        if (rowsInGroup.length === 0) {
            alert('Error: Tidak ada row yang ditemukan dalam grup ini!');
            return;
        }

        savingRows.value.add(-1);

        try {
            const updatePromises = rowsInGroup.map(async (row) => {
                row.type = modalType.value;
                row.penanggung_jawab = modalPenanggungJawab.value;

                if (row.id) {
                    return await axios.post('/stock/output/update', {
                        id: row.id,
                        type: modalType.value,
                        penanggung_jawab: modalPenanggungJawab.value,
                        sap_no: row.sap_no,
                        product_unit: row.product_unit,
                        qty_day: row.qty_day,
                        stock_date: row.stock_date,
                        out_shift1: row.out_shift1,
                        out_shift2: row.out_shift2,
                        out_shift3: row.out_shift3,
                        ng_shift1: row.ng_shift1,
                        ng_shift2: row.ng_shift2,
                        ng_shift3: row.ng_shift3,
                    });
                }
            });

            await Promise.all(updatePromises);
            savingRows.value.delete(-1);
            closeModal();
        } catch (error: any) {
            alert('Error updating group: ' + (error.response?.data?.message || error.message));
            savingRows.value.delete(-1);
        }
    }
};

const closeModal = () => {
    showModal.value = false;
    editingOutput.value = null;
    modalType.value = '';
    modalPenanggungJawab.value = '';
};

const addRowToGroup = async (type: string, penanggungJawab: string) => {
    try {
        const response = await axios.post('/stock/output/update', {
            id: null,
            type: type,
            penanggung_jawab: penanggungJawab,
            sap_no: '',
            product_unit: '',
            qty_day: 0,
            stock_date: selectedDate.value,
            out_shift1: 0,
            out_shift2: 0,
            out_shift3: 0,
            ng_shift1: 0,
            ng_shift2: 0,
            ng_shift3: 0,
        });

        const newRow: OutputData = {
            id: response.data.data.id,
            type: type,
            penanggung_jawab: penanggungJawab,
            sap_no: '',
            product_unit: '',
            qty_day: 0,
            stock_date: selectedDate.value,
            out_shift1: 0,
            out_shift2: 0,
            out_shift3: 0,
            ng_shift1: 0,
            ng_shift2: 0,
            ng_shift3: 0,
            total: 0
        };

        outputData.value.push(newRow);
    } catch (error: any) {
        console.error('Add row error:', error);
        alert('Error adding row: ' + (error.response?.data?.message || error.message));
    }
};

const deleteRow = async (globalIndex: number) => {
    const output = outputData.value[globalIndex];

    if (!output.id) {
        outputData.value.splice(globalIndex, 1);
        return;
    }

    if (confirm('Hapus baris ini?')) {
        try {
            await axios.delete(`/stock/output/${output.id}`);
            outputData.value.splice(globalIndex, 1);
        } catch (error) {
            console.error('Delete error:', error);
        }
    }
};

const getGlobalIndex = (item: OutputData) => {
    return outputData.value.findIndex(i => i === item);
};

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedRows.value.clear();
    } else {
        selectedRows.value = new Set(
            Object.values(groupedData.value).flat()
                .map(item => item.id)
                .filter((id): id is number => id !== null)
        );
    }
};

const toggleSelectRow = (id: number | null) => {
    if (id === null) return;
    if (selectedRows.value.has(id)) {
        selectedRows.value.delete(id);
    } else {
        selectedRows.value.add(id);
    }
};

const deleteSelected = async () => {
    if (selectedRows.value.size === 0) {
        alert('⚠️ Pilih data yang ingin dihapus terlebih dahulu');
        return;
    }

    if (!confirm(`Hapus ${selectedRows.value.size} data yang dipilih?`)) {
        return;
    }

    try {
        const response = await axios.post('/stock/delete-multiple-output', {
            ids: Array.from(selectedRows.value)
        });

        if (response.data.success) {
            outputData.value = outputData.value.filter(item => !selectedRows.value.has(item.id!));
            selectedRows.value.clear();
            alert(`✅ ${response.data.message}`);
        }
    } catch (error: any) {
        console.error('Delete selected error:', error);
        alert('❌ Error: ' + (error.response?.data?.message || error.message));
    }
};

const deleteAllData = async () => {
    if (outputData.value.length === 0) {
        alert('⚠️ Tidak ada data untuk dihapus');
        return;
    }

    const count = outputData.value.length;

    if (!confirm(`⚠️ HAPUS SEMUA ${count} DATA OUTPUT pada tanggal ${selectedDate.value}?\n\n⚠️ PERHATIAN: Tindakan ini tidak bisa dibatalkan!`)) {
        return;
    }

    const confirmText = prompt(`Untuk konfirmasi, ketik: OUTPUT`);
    if (confirmText !== 'OUTPUT') {
        alert('❌ Konfirmasi dibatalkan');
        return;
    }

    try {
        const response = await axios.post('/stock/output/delete-all-by-date', {
            date: selectedDate.value,
            confirmation: 'OUTPUT'
        });

        if (response.data.success) {
            outputData.value = [];
            selectedRows.value.clear();
            alert(`✅ ${response.data.message}`);
        }
    } catch (error: any) {
        console.error('Delete all error:', error);
        alert('❌ Error: ' + (error.response?.data?.message || error.message));
    }
};

const openBomModal = async (output: OutputData) => {
    if (!output.id) {
        alert('⚠️ Simpan data output terlebih dahulu sebelum mengelola BOM');
        return;
    }

    selectedOutputForBom.value = output;
    showBomModal.value = true;
    await loadBomMaterials();
};

const loadBomMaterials = async () => {
    if (!selectedOutputForBom.value?.id) return;

    isLoadingBom.value = true;
    try {
        const response = await axios.get(`/stock/output/${selectedOutputForBom.value.id}/materials`);
        if (response.data.success) {
            bomMaterials.value = response.data.materials;
        }
    } catch (error) {
        console.error('Error loading BOM materials:', error);
        alert('Error loading materials');
    } finally {
        isLoadingBom.value = false;
    }
};

const searchAvailableMaterials = async () => {
    if (searchMaterial.value.length < 2) {
        availableMaterials.value = [];
        showMaterialDropdown.value = false;
        return;
    }

    try {
        const response = await axios.get('/stock/available-materials', {
            params: {
                date: selectedDate.value,
                search: searchMaterial.value
            }
        });

        if (response.data.success) {
            availableMaterials.value = response.data.materials;
            showMaterialDropdown.value = true;
        }
    } catch (error) {
        console.error('Error searching materials:', error);
    }
};

const selectMaterial = (material: Material) => {
    const exists = bomMaterials.value.find(m => m.sap_no === material.sap_no);
    if (exists) {
        alert('Material sudah ditambahkan!');
        return;
    }

    bomMaterials.value.push({
        sap_no: material.sap_no,
        material_name: material.material_name,
        qty_per_unit: material.qty_per_unit || 1,
        qty_unit_reference: material.qty_unit_reference,
        bl_type: material.bl_type
    });

    searchMaterial.value = '';
    availableMaterials.value = [];
    showMaterialDropdown.value = false;
};

const removeMaterial = (index: number) => {
    bomMaterials.value.splice(index, 1);
};

const saveBomMaterials = async () => {
    if (!selectedOutputForBom.value?.id) return;

    if (bomMaterials.value.length === 0) {
        alert('⚠️ Tambahkan minimal 1 material');
        return;
    }

    isSavingBom.value = true;

    try {
        const response = await axios.post(
            `/stock/output/${selectedOutputForBom.value.id}/materials`,
            {
                materials: bomMaterials.value.map(m => ({
                    sap_no: m.sap_no,
                    qty_per_unit: m.qty_per_unit || 1
                }))
            }
        );

        if (response.data.success) {
            alert(`✅ ${response.data.message}\n\nTotal: ${response.data.count} materials`);
            closeBomModal();
        }
    } catch (error: any) {
        console.error('Error saving BOM:', error);
        alert('❌ Error: ' + (error.response?.data?.message || error.message));
    } finally {
        isSavingBom.value = false;
    }
};

const closeBomModal = () => {
    showBomModal.value = false;
    selectedOutputForBom.value = null;
    bomMaterials.value = [];
    searchMaterial.value = '';
    availableMaterials.value = [];
    showMaterialDropdown.value = false;
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        const filesArray = Array.from(target.files).slice(0, 5);
        importFiles.value = filesArray;

        parseAllCSVFiles();
    }
};

const parseAllCSVFiles = async () => {
    if (importFiles.value.length === 0) return;

    isProcessing.value = true;
    importPreview.value = [];

    for (let fileIndex = 0; fileIndex < importFiles.value.length; fileIndex++) {
        const file = importFiles.value[fileIndex];
        currentProcessingFile.value = `Processing ${fileIndex + 1}/${importFiles.value.length}: ${file.name}`;
        await parseCSV(file);
        await new Promise(resolve => setTimeout(resolve, 100));
    }

    importSummary.value = {
        total: importPreview.value.length,
        matched: importPreview.value.filter(i => i.matched).length,
        unmatched: importPreview.value.filter(i => !i.matched).length
    };

    currentProcessingFile.value = '';
    isProcessing.value = false;
};

function parseCSVLine(line: string): string[] {
    const columns: string[] = [];
    let currentCell = '';
    let insideQuotes = false;

    for (let j = 0; j < line.length; j++) {
        const char = line[j];
        if (char === '"') {
            insideQuotes = !insideQuotes;
        } else if (char === ',' && !insideQuotes) {
            columns.push(currentCell.trim().replace(/"/g, ''));
            currentCell = '';
        } else {
            currentCell += char;
        }
    }
    columns.push(currentCell.trim().replace(/"/g, ''));
    return columns;
}

const parseCSV = (file: File): Promise<void> => {
    return new Promise((resolve) => {
        const reader = new FileReader();

        reader.onload = (e) => {
            try {
                const text = e.target?.result as string;
                const lines = text.split('\n');

                const grouped = new Map<string, any>();

                let headerRow = -1;
                let materialCol = -1;
                let descCol = -1;
                let qty1Col = -1;
                let qty2Col = -1;

                for (let i = 0; i < Math.min(15, lines.length); i++) {
                    const line = lines[i];
                    const cols = parseCSVLine(line);

                    for (let j = 0; j < cols.length; j++) {
                        const cell = cols[j].toLowerCase().trim();

                        if (cell === 'material number' || cell === 'material' || cell === 'part name') {
                            materialCol = j;
                            descCol = j + 1;
                            headerRow = i;
                        }

                        if (cell === 'qty' || (cell.includes('cycle') && cell.includes('qty'))) {
                            if (qty1Col === -1) {
                                qty1Col = j;
                            } else if (qty2Col === -1) {
                                qty2Col = j;
                            }
                        }
                    }

                    if (materialCol !== -1 && qty1Col !== -1) break;
                }

                if (materialCol === -1) {
                    console.warn('⚠️ Header tidak terdeteksi, gunakan pattern analysis');

                    for (let i = 0; i < Math.min(30, lines.length); i++) {
                        const cols = parseCSVLine(lines[i]);

                        for (let j = 0; j < Math.min(10, cols.length); j++) {
                            if (/^(A[0-9]|[0-9]{5,})/.test(cols[j])) {
                                materialCol = j;
                                descCol = j + 1;
                                headerRow = i - 1;
                                break;
                            }
                        }
                        if (materialCol !== -1) break;
                    }
                }

                if (qty1Col === -1) {
                    console.warn('⚠️ QTY column tidak terdeteksi dari header');

                    const dataStartRow = headerRow !== -1 ? headerRow + 2 : 5;
                    const qtyColumnCandidates = new Map<number, number>();

                    for (let i = dataStartRow; i < Math.min(dataStartRow + 10, lines.length); i++) {
                        const cols = parseCSVLine(lines[i]);

                        for (let j = 9; j < Math.min(20, cols.length); j++) {
                            const val = cols[j].trim();

                            if (!val || val === '' || val === '-') continue;
                            if (val.includes(':') || val.includes('/')) continue;

                            const parsed = parseInt(val.replace(/[,]/g, ''));

                            if (!isNaN(parsed) && parsed >= 0 && !val.includes('.')) {
                                const currentScore = qtyColumnCandidates.get(j) || 0;
                                qtyColumnCandidates.set(j, currentScore + 1);
                            }
                        }
                    }

                    const sortedCandidates = Array.from(qtyColumnCandidates.entries())
                        .sort((a, b) => b[1] - a[1]);

                    if (sortedCandidates.length >= 1) {
                        qty1Col = sortedCandidates[0][0];
                    }
                    if (sortedCandidates.length >= 2) {
                        qty2Col = sortedCandidates[1][0];
                    }

                    console.log('📊 QTY columns detected:', {
                        qty1Col,
                        qty2Col,
                        candidates: sortedCandidates.slice(0, 5)
                    });
                }

                console.log('🔍 Final detected columns:', {
                    file: file.name,
                    materialCol,
                    descCol,
                    qty1Col,
                    qty2Col,
                    headerRow
                });

                if (materialCol === -1 || qty1Col === -1) {
                    alert(`❌ Gagal mendeteksi struktur CSV di file: ${file.name}\n\nPastikan file mengandung:\n- Kolom Material Number/SAP NO\n- Kolom QTY (minimal 1)`);
                    resolve();
                    return;
                }

                const startRow = headerRow !== -1 ? headerRow + 2 : 0;

                for (let i = startRow; i < lines.length; i++) {
                    const line = lines[i].trim();
                    if (!line) continue;

                    const columns = parseCSVLine(line);

                    if (columns.length <= Math.max(materialCol, descCol, qty1Col, qty2Col || 0)) continue;

                    const materialNumber = columns[materialCol]?.trim();

                    if (!materialNumber ||
                        materialNumber.length < 5 ||
                        materialNumber === 'Material Number' ||
                        materialNumber === 'PART NAME' ||
                        materialNumber.toLowerCase().includes('shift') ||
                        materialNumber.toLowerCase().includes('job no') ||
                        materialNumber.toLowerCase().includes('proses') ||
                        materialNumber.toLowerCase().includes('daily') ||
                        materialNumber.toLowerCase().includes('weekly')) {
                        continue;
                    }

                    const qty1Raw = columns[qty1Col]?.trim() || '';
                    const qty2Raw = qty2Col !== -1 ? (columns[qty2Col]?.trim() || '') : '';

                    let qty1 = 0;
                    let qty2 = 0;

                    if (qty1Raw && !qty1Raw.includes(':') && !qty1Raw.includes('/')) {
                        const cleaned = qty1Raw.replace(/\s/g, '').replace(/,/g, '');
                        const parsed = parseInt(cleaned);
                        if (!isNaN(parsed) && parsed >= 0 && !qty1Raw.includes('.')) {
                            qty1 = parsed;
                        }
                    }

                    if (qty2Raw && !qty2Raw.includes(':') && !qty2Raw.includes('/')) {
                        const cleaned = qty2Raw.replace(/\s/g, '').replace(/,/g, '');
                        const parsed = parseInt(cleaned);
                        if (!isNaN(parsed) && parsed >= 0 && !qty2Raw.includes('.')) {
                            qty2 = parsed;
                        }
                    }

                    if (qty1 === 0 && qty2 === 0) continue;

                    const existingOutput = outputData.value.find(o =>
                        o.sap_no && materialNumber &&
                        o.sap_no.trim().toUpperCase() === materialNumber.trim().toUpperCase()
                    );

                    if (existingOutput) {
                        if (!grouped.has(materialNumber)) {
                            grouped.set(materialNumber, {
                                materialNumber,
                                shift1: 0,
                                shift2: 0,
                                shift3: 0,
                                matched: true,
                                existingId: existingOutput.id,
                                matchedProduct: {
                                    product_unit: existingOutput.product_unit,
                                    type: existingOutput.type,
                                    penanggung_jawab: existingOutput.penanggung_jawab
                                }
                            });
                        }

                        const existing = grouped.get(materialNumber)!;
                        const totalQty = qty1 + qty2;

                        if (totalQty > 0) {
                            if (existing.shift1 === 0) {
                                existing.shift1 = totalQty;
                            } else if (existing.shift2 === 0) {
                                existing.shift2 = totalQty;
                            } else if (existing.shift3 === 0) {
                                existing.shift3 = totalQty;
                            }
                        }
                    }
                }

                const preview = Array.from(grouped.values())
                    .filter(item => item.matched)
                    .map(item => ({
                        ...item,
                        total: item.shift1 + item.shift2 + item.shift3,
                        sourceFile: file.name
                    }));

                importPreview.value.push(...preview);

                resolve();
            } catch (error) {
                alert(`Error parsing ${file.name}: ` + error);
                resolve();
            }
        };

        reader.onerror = () => {
            resolve();
        };

        reader.readAsText(file);
    });
};


const confirmImport = async () => {
    if (importSummary.value.matched === 0) {
        alert('Tidak ada data yang match untuk diimport');
        return;
    }

    const hasNullId = importPreview.value.some(item => item.matched && item.existingId === null);
    if (hasNullId) {
        alert('⚠️ Ada data yang belum tersimpan ke database (ID: null).\n\nRefresh halaman (F5) atau klik cell untuk trigger auto-save, lalu upload CSV lagi');
        return;
    }

    if (!confirm(`Import ${importSummary.value.matched} data ke Output Products?`)) {
        return;
    }

    isProcessing.value = true;

    try {
        let updatedCount = 0;

        for (const item of importPreview.value) {
            if (item.matched && item.existingId) {
                const outputIndex = outputData.value.findIndex(o => o.id === item.existingId);

                if (outputIndex !== -1) {
                    console.log('Updating Output:', outputData.value[outputIndex].sap_no, 'Shifts:', item.shift1, item.shift2, item.shift3);

                    outputData.value[outputIndex].out_shift1 = item.shift1;
                    outputData.value[outputIndex].out_shift2 = item.shift2;
                    outputData.value[outputIndex].out_shift3 = item.shift3;
                    calculateTotal(outputData.value[outputIndex]);

                    try {
                        const response = await axios.post('/stock/output/update', {
                            id: outputData.value[outputIndex].id,
                            type: outputData.value[outputIndex].type,
                            penanggung_jawab: outputData.value[outputIndex].penanggung_jawab,
                            sap_no: outputData.value[outputIndex].sap_no,
                            product_unit: outputData.value[outputIndex].product_unit,
                            qty_day: outputData.value[outputIndex].qty_day,
                            stock_date: outputData.value[outputIndex].stock_date,
                            out_shift1: item.shift1,
                            out_shift2: item.shift2,
                            out_shift3: item.shift3,
                            ng_shift1: outputData.value[outputIndex].ng_shift1,
                            ng_shift2: outputData.value[outputIndex].ng_shift2,
                            ng_shift3: outputData.value[outputIndex].ng_shift3,
                        });

                        if (response.data.data && response.data.data.id) {
                            outputData.value[outputIndex].id = response.data.data.id;
                        }

                        updatedCount++;
                    } catch (error: any) {
                        console.error('Error updating output:', error.response?.data || error);
                    }
                }
            }
        }

        alert(`Berhasil import ${updatedCount} records ke Output Products!\n\nData telah disinkronkan ke Control Stock.`);
        closeImportModal();
    } catch (error) {
        alert('Error saat import data: ' + error);
    } finally {
        isProcessing.value = false;
    }
};

const closeImportModal = () => {
    showImportModal.value = false;
    importFiles.value = [];
    importPreview.value = [];
    importSummary.value = { total: 0, matched: 0, unmatched: 0 };
    currentProcessingFile.value = '';
};
</script>

<template>
    <Head title="Output Products" />
    <AppLayout :breadcrumbs="[{ title: 'Output Products', href: '/output' }]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold">Output Product</h1>
                <div class="flex gap-2 items-center">
                    <div v-if="selectedRows.size > 0" class="flex gap-2 mr-4 items-center">
                        <span class="text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1 rounded-full">
                            {{ selectedRows.size }} dipilih
                        </span>
                        <button
                            @click="deleteSelected"
                            class="flex items-center gap-1 px-3 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition shadow-md hover:shadow-lg"
                        >
                            <Trash2 class="w-4 h-4" />
                            Hapus Pilihan
                        </button>
                    </div>

                    <button
                        @click="showFilterPanel = !showFilterPanel"
                        class="flex items-center gap-1 px-3 py-2 text-sm rounded-md transition-colors"
                        :class="hasActiveFilters
                            ? 'bg-blue-600 text-white hover:bg-blue-700'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'"
                    >
                        <Filter class="w-4 h-4" />
                        Filter
                        <span v-if="hasActiveFilters" class="ml-1 bg-white text-blue-600 rounded-full px-1.5 py-0.5 text-xs font-bold">
                            {{ (filterType ? 1 : 0) + (filterPenanggungJawab ? 1 : 0) + (sortBy !== 'none' ? 1 : 0) }}
                        </span>
                    </button>

                    <button
                        @click="showImportModal = true"
                        class="flex items-center gap-1 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
                    >
                        <Upload class="w-4 h-4" />
                        Import BSTB
                    </button>

                    <button
                        @click="openAddModal"
                        class="flex items-center gap-1 px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700"
                    >
                        <Plus class="w-4 h-4" />
                        Add New Group
                    </button>

                    <button
                        @click="deleteAllData"
                        class="flex items-center gap-1 px-3 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 shadow-md hover:shadow-lg transition"
                    >
                        <Trash class="w-4 h-4" />
                        Delete All
                    </button>

                    <Calendar class="w-5 h-5 text-muted-foreground" />
                    <input
                        v-model="selectedDate"
                        type="date"
                        @change="changeDate"
                        class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                </div>
            </div>

            <div v-if="showFilterPanel" class="border border-sidebar-border rounded-lg p-4 bg-gray-50 dark:bg-sidebar-accent">
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <label class="block text-xs font-medium mb-1">Filter by TYPE</label>
                        <select
                            v-model="filterType"
                            class="w-full px-3 py-2 text-sm border border-sidebar-border rounded-md dark:bg-sidebar"
                        >
                            <option value="">All Types</option>
                            <option v-for="type in uniqueTypes" :key="type" :value="type">
                                {{ type }}
                            </option>
                        </select>
                    </div>

                    <div class="flex-1">
                        <label class="block text-xs font-medium mb-1">Filter by Penanggung Jawab</label>
                        <select
                            v-model="filterPenanggungJawab"
                            class="w-full px-3 py-2 text-sm border border-sidebar-border rounded-md dark:bg-sidebar"
                        >
                            <option value="">All Penanggung Jawab</option>
                            <option v-for="pj in uniquePenanggungJawab" :key="pj" :value="pj">
                                {{ pj }}
                            </option>
                        </select>
                    </div>

                    <div class="flex-1">
                        <label class="block text-xs font-medium mb-1">Sort By</label>
                        <div class="flex gap-2">
                            <button
                                @click="toggleSort('type')"
                                class="flex-1 flex items-center justify-center gap-1 px-3 py-2 text-sm rounded-md transition-colors"
                                :class="sortBy === 'type'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white dark:bg-sidebar border border-sidebar-border hover:bg-gray-100 dark:hover:bg-gray-800'"
                            >
                                TYPE
                                <ArrowUpDown v-if="sortBy === 'type'" class="w-3 h-3" :class="sortOrder === 'desc' ? 'rotate-180' : ''" />
                            </button>
                            <button
                                @click="toggleSort('penanggung_jawab')"
                                class="flex-1 flex items-center justify-center gap-1 px-3 py-2 text-sm rounded-md transition-colors"
                                :class="sortBy === 'penanggung_jawab'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white dark:bg-sidebar border border-sidebar-border hover:bg-gray-100 dark:hover:bg-gray-800'"
                            >
                                PJ
                                <ArrowUpDown v-if="sortBy === 'penanggung_jawab'" class="w-3 h-3" :class="sortOrder === 'desc' ? 'rotate-180' : ''" />
                            </button>
                        </div>
                    </div>

                    <button
                        @click="clearFilters"
                        class="px-4 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                        :disabled="!hasActiveFilters"
                        :class="{ 'opacity-50 cursor-not-allowed': !hasActiveFilters }"
                    >
                        Clear All
                    </button>
                </div>
            </div>

            <div class="border border-sidebar-border rounded-lg overflow-hidden bg-white dark:bg-sidebar">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead class="bg-gray-100 dark:bg-sidebar-accent sticky top-0">
                            <tr class="border-b-2 border-sidebar-border">
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border w-10" rowspan="2">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                        title="Select All"
                                    />
                                </th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border whitespace-nowrap" rowspan="2">NO</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border whitespace-nowrap" rowspan="2">TYPE</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border whitespace-nowrap" rowspan="2">PENANGGUNG<br>JAWAB</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border whitespace-nowrap" rowspan="2">SAP NO</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border whitespace-nowrap" rowspan="2">PRODUCT UNIT</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border whitespace-nowrap" rowspan="2">Qty/<br>Day</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border bg-red-50 dark:bg-red-900/20" colspan="4">OUT</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border bg-yellow-50 dark:bg-yellow-900/20" colspan="3">NG</th>
                                <th class="px-2 py-2 text-center font-semibold whitespace-nowrap" rowspan="2">Act</th>
                            </tr>
                            <tr class="border-b border-sidebar-border">
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-red-50 dark:bg-red-900/20 w-16">Shift 1</th>
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-red-50 dark:bg-red-900/20 w-16">Shift 2</th>
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-red-50 dark:bg-red-900/20 w-16">Shift 3</th>
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-red-50 dark:bg-red-900/20 w-20">Total</th>

                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-yellow-50 dark:bg-yellow-900/20 w-16">Shift 1</th>
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-yellow-50 dark:bg-yellow-900/20 w-16">Shift 2</th>
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-yellow-50 dark:bg-yellow-900/20 w-16">Shift 3</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(items, groupKey) in groupedData" :key="groupKey">
                                <tr
                                    v-for="(output, localIndex) in items"
                                    :key="output.id || `new-${getGlobalIndex(output)}`"
                                    class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/30"
                                    :class="{
                                        'bg-blue-50/50 dark:bg-blue-900/10': savingRows.has(getGlobalIndex(output)) || savingRows.has(-1),
                                        'bg-blue-100 dark:bg-blue-900/20': output.id && selectedRows.has(output.id),
                                        'border-t-2 border-t-gray-400': localIndex === 0 && groupKey !== 'NO TYPE|||NO PJ'
                                    }"
                                >
                                    <td class="px-2 py-1 text-center border-r border-sidebar-border">
                                        <input
                                            v-if="output.id"
                                            type="checkbox"
                                            :checked="selectedRows.has(output.id)"
                                            @change="toggleSelectRow(output.id)"
                                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                        />
                                    </td>

                                    <td class="px-2 py-1 text-center border-r border-sidebar-border text-xs whitespace-nowrap">
                                        {{ getGlobalIndex(output) + 1 }}
                                    </td>

                                    <td class="px-1 py-1 border-r border-sidebar-border text-center whitespace-nowrap"
                                        :class="{ 'bg-gray-50 dark:bg-sidebar-accent/50': localIndex === 0 }">
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="text-xs font-semibold px-1">{{ output.type || '(empty)' }}</span>
                                            <button
                                                v-if="localIndex === 0"
                                                @click="openEditModal(output)"
                                                class="p-0.5 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded"
                                                title="Edit TYPE & Penanggung Jawab (All rows in group)"
                                            >
                                                <Edit2 class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </td>

                                    <td class="px-1 py-1 border-r border-sidebar-border text-center"
                                        :class="{ 'bg-gray-50 dark:bg-sidebar-accent/50': localIndex === 0 }">
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="text-xs px-1">{{ output.penanggung_jawab || '(empty)' }}</span>
                                            <button
                                                v-if="localIndex === 0"
                                                @click="openEditModal(output)"
                                                class="p-0.5 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded flex-shrink-0"
                                                title="Edit TYPE & Penanggung Jawab (All rows in group)"
                                            >
                                                <Edit2 class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </td>

                                    <td class="px-1 py-1 border-r border-sidebar-border">
                                        <input
                                            v-model="output.sap_no"
                                            @blur="saveOnBlur(output, getGlobalIndex(output))"
                                            type="text"
                                            class="w-42 text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                        />
                                    </td>

                                    <td class="px-1 py-1 border-r border-sidebar-border w-full">
                                        <input
                                            v-model="output.product_unit"
                                            @blur="saveOnBlur(output, getGlobalIndex(output))"
                                            type="text"
                                            class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                        />
                                    </td>

                                    <td class="px-1 py-1 border-r border-sidebar-border">
                                        <input
                                            v-model.number="output.qty_day"
                                            @input="autoSaveNumber(output, getGlobalIndex(output))"
                                            type="number"
                                            min="0"
                                            class="w-20 text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                        />
                                    </td>

                                    <td class="px-1 py-1 border-r border-sidebar-border bg-red-50/50 dark:bg-red-900/10">
                                        <input
                                            v-model.number="output.out_shift1"
                                            @input="autoSaveNumber(output, getGlobalIndex(output))"
                                            type="number"
                                            min="0"
                                            class="w-20 text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-red-500 bg-transparent"
                                        />
                                    </td>
                                    <td class="px-1 py-1 border-r border-sidebar-border bg-red-50/50 dark:bg-red-900/10">
                                        <input
                                            v-model.number="output.out_shift2"
                                            @input="autoSaveNumber(output, getGlobalIndex(output))"
                                            type="number"
                                            min="0"
                                            class="w-20 text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-red-500 bg-transparent"
                                        />
                                    </td>
                                    <td class="px-1 py-1 border-r border-sidebar-border bg-red-50/50 dark:bg-red-900/10">
                                        <input
                                            v-model.number="output.out_shift3"
                                            @input="autoSaveNumber(output, getGlobalIndex(output))"
                                            type="number"
                                            min="0"
                                            class="w-20 text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-red-500 bg-transparent"
                                        />
                                    </td>
                                    <td class="px-2 py-1 text-center text-xs font-bold border-r border-sidebar-border bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 whitespace-nowrap">
                                        {{ output.total.toLocaleString() }}
                                    </td>

                                    <td class="px-1 py-1 border-r border-sidebar-border bg-yellow-50/50 dark:bg-yellow-900/10">
                                        <input
                                            v-model.number="output.ng_shift1"
                                            @input="autoSaveNumber(output, getGlobalIndex(output))"
                                            type="number"
                                            min="0"
                                            class="w-20 text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-yellow-500 bg-transparent"
                                        />
                                    </td>
                                    <td class="px-1 py-1 border-r border-sidebar-border bg-yellow-50/50 dark:bg-yellow-900/10">
                                        <input
                                            v-model.number="output.ng_shift2"
                                            @input="autoSaveNumber(output, getGlobalIndex(output))"
                                            type="number"
                                            min="0"
                                            class="w-20 text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-yellow-500 bg-transparent"
                                        />
                                    </td>
                                    <td class="px-1 py-1 border-r border-sidebar-border bg-yellow-50/50 dark:bg-yellow-900/10">
                                        <input
                                            v-model.number="output.ng_shift3"
                                            @input="autoSaveNumber(output, getGlobalIndex(output))"
                                            type="number"
                                            min="0"
                                            class="w-20 text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-yellow-500 bg-transparent"
                                        />
                                    </td>

                                    <td class="px-2 py-1 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1">
                                            <button
                                                @click="openBomModal(output)"
                                                class="p-1 text-purple-600 hover:bg-purple-100 dark:hover:bg-purple-900 rounded"
                                                title="Bill of Materials"
                                                :disabled="!output.id"
                                                :class="{ 'opacity-50 cursor-not-allowed': !output.id }"
                                            >
                                                <Package class="w-3 h-3" />
                                            </button>
                                            <button
                                                v-if="localIndex === items.length - 1"
                                                @click="addRowToGroup(output.type, output.penanggung_jawab)"
                                                class="p-1 text-green-600 hover:bg-green-100 dark:hover:bg-green-900 rounded"
                                                title="Add row to this group"
                                            >
                                                <Plus class="w-3 h-3" />
                                            </button>
                                            <button
                                                @click="deleteRow(getGlobalIndex(output))"
                                                class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                                title="Delete"
                                            >
                                                <Trash2 class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-xs text-muted-foreground flex justify-between items-center">
                <span>
                    Showing: {{ Object.values(groupedData).flat().length }} items
                    <span v-if="hasActiveFilters" class="text-blue-600 dark:text-blue-400">
                        (filtered from {{ outputData.length }} total)
                    </span>
                </span>
                <span v-if="savingRows.size > 0" class="text-blue-600 dark:text-blue-400 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 bg-blue-600 rounded-full animate-pulse"></span>
                    Saving & Syncing to Control Stock...
                </span>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            @click.self="closeModal"
        >
            <div class="bg-white dark:bg-sidebar rounded-lg shadow-xl p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">
                        {{ modalMode === 'add' ? 'Add New Group' : 'Edit TYPE & Penanggung Jawab' }}
                    </h3>
                    <button @click="closeModal" class="text-gray-500 hover:text-gray-700">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="modalMode === 'edit'" class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-md">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        ⚠️ Perubahan akan diterapkan ke <strong>semua baris</strong> dalam grup ini
                    </p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">TYPE</label>
                        <input
                            v-model="modalType"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 dark:bg-sidebar"
                            placeholder="Masukkan TYPE (contoh: DSS, D26, DT4)..."
                            autofocus
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Penanggung Jawab</label>
                        <input
                            v-model="modalPenanggungJawab"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 dark:bg-sidebar"
                            placeholder="Masukkan nama penanggung jawab..."
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button
                        @click="closeModal"
                        class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveFromModal"
                        class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        :disabled="savingRows.has(-1)"
                    >
                        {{ modalMode === 'add' ? 'Create Group' : 'Update All Rows' }}
                    </button>
                </div>
            </div>
        </div>
        <div
            v-if="showBomModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            @click.self="closeBomModal"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-semibold flex items-center gap-2">
                            <Package class="w-5 h-5 text-purple-600" />
                            Bill of Materials
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Product: <span class="font-semibold text-blue-600 dark:text-blue-400">{{ selectedOutputForBom?.product_unit }}</span>
                        </p>
                    </div>
                    <button @click="closeBomModal" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div v-if="isLoadingBom" class="text-center py-8">
                        <Loader class="w-8 h-8 animate-spin mx-auto text-blue-600" />
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Loading materials...</p>
                    </div>

                    <div v-else class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                        <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                            <Search class="w-4 h-4" />
                            Add Material from Control Stock
                        </h3>
                        <div class="relative">
                            <input
                                v-model="searchMaterial"
                                @input="searchAvailableMaterials"
                                @focus="() => { if (availableMaterials.length > 0) showMaterialDropdown = true }"
                                type="text"
                                placeholder="Search by SAP NO or Material Name..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 dark:bg-gray-800"
                            />

                            <div
                                v-if="showMaterialDropdown && availableMaterials.length > 0"
                                class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto"
                            >
                                <button
                                    v-for="(material, index) in availableMaterials"
                                    :key="index"
                                    @click="selectMaterial(material)"
                                    class="w-full px-3 py-2 text-left hover:bg-blue-50 dark:hover:bg-blue-900/20 border-b border-gray-100 dark:border-gray-700 last:border-0"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ material.sap_no }}
                                            </div>
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ material.material_name }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs font-semibold text-purple-600 dark:text-purple-400">
                                                {{ material.bl_type }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                Qty/unit: {{ material.qty_per_unit }}
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </div>
                            </div>
                    </div>

                    <div v-if="bomMaterials.length > 0" class="border border-purple-200 dark:border-purple-800 rounded-lg overflow-hidden">
                        <div class="bg-purple-100 dark:bg-purple-900/30 px-3 py-2">
                            <h3 class="text-sm font-semibold text-purple-700 dark:text-purple-300">
                                Materials Required ({{ bomMaterials.length }})
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold">SAP NO</th>
                                        <th class="px-3 py-2 text-left font-semibold">Material Name</th>
                                        <th class="px-3 py-2 text-center font-semibold">BL Type</th>
                                        <th class="px-3 py-2 text-center font-semibold">Qty/Unit</th>
                                        <th class="px-3 py-2 text-center font-semibold text-xs text-gray-500">Ref</th>
                                        <th class="px-3 py-2 text-center font-semibold w-16">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(material, index) in bomMaterials"
                                        :key="index"
                                        class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        <td class="px-3 py-2 font-mono text-xs font-semibold">
                                            {{ material.sap_no }}
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            {{ material.material_name || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="px-2 py-1 rounded text-xs font-semibold" :class="material.bl_type === 'BL1' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'">
                                                {{ material.bl_type }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input
                                                v-model.number="material.qty_per_unit"
                                                type="number"
                                                min="1"
                                                class="w-20 text-center text-xs rounded border border-gray-300 dark:border-gray-600 px-2 py-1 focus:ring-2 focus:ring-purple-500"
                                            />
                                        </td>
                                         <td class="px-3 py-2 text-center text-xs text-gray-500">
                                            {{ material.qty_unit_reference || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button
                                                @click="removeMaterial(index)"
                                                class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                                title="Remove"
                                            >
                                                <Trash2 class="w-3 h-3" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else class="text-center py-12 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                        <Package class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                        <p class="text-sm text-gray-600 dark:text-gray-400">No materials added yet</p>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">Search and add materials from Control Stock</p>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        <p v-if="bomMaterials.length > 0">
                            <span class="font-semibold text-purple-600 dark:text-purple-400">{{ bomMaterials.length }} materials</span> will be saved
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="closeBomModal"
                            class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveBomMaterials"
                            :disabled="bomMaterials.length === 0 || isSavingBom"
                            class="px-4 py-2 text-sm bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            <Loader v-if="isSavingBom" class="w-4 h-4 animate-spin" />
                            <Check v-else class="w-4 h-4" />
                            Save BOM
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showImportModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-semibold">Import Data BSTB (CSV) - Output Products</h2>
                        <p class="text-xs text-gray-500 mt-1">Support multiple file upload (max 5 files)</p>
                    </div>
                    <button @click="closeImportModal" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div class="space-y-2">
                        <label class="flex-1 flex items-center justify-center px-4 py-8 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-500 dark:hover:border-blue-400 transition">
                            <div class="text-center">
                                <Upload class="w-12 h-12 mx-auto mb-2 text-gray-400" />
                                <span class="text-sm font-medium">
                                    {{ importFiles.length > 0
                                        ? `${importFiles.length} file(s) selected`
                                        : 'Click to upload CSV files (max 5 files)'
                                    }}
                                </span>
                                <p class="text-xs text-gray-500 mt-1">Format: CSV | Support multiple files</p>
                                <p v-if="importFiles.length > 0" class="text-xs text-blue-600 dark:text-blue-400 mt-2 font-semibold max-w-md mx-auto truncate">
                                    {{ importFiles.map(f => f.name).join(', ') }}
                                </p>
                            </div>
                            <input type="file" accept=".csv" multiple @change="handleFileSelect" class="hidden" />
                        </label>

                        <div v-if="importFiles.length > 0" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border border-blue-200 dark:border-blue-800">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-xs font-semibold text-blue-700 dark:text-blue-300">Selected Files ({{ importFiles.length }}/5)</h4>
                            </div>
                            <ul class="space-y-1">
                                <li v-for="(file, index) in importFiles" :key="index" class="flex items-center gap-2 text-xs text-blue-600 dark:text-blue-400">
                                    <span class="w-5 h-5 flex items-center justify-center bg-blue-200 dark:bg-blue-800 rounded-full text-xs font-bold">{{ index + 1 }}</span>
                                    <span class="flex-1 truncate">{{ file.name }}</span>
                                    <span class="text-gray-500">{{ (file.size / 1024).toFixed(1) }} KB</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="importPreview.length > 0" class="border-2 border-green-200 dark:border-green-800 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-green-600 dark:text-green-400 mb-3">📊 Summary</h3>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded">
                                <div class="text-xl font-bold text-green-600 dark:text-green-400">{{ importSummary.matched }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Materials Matched</div>
                            </div>
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-2 rounded">
                                <div class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ importFiles.length }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Files Processed</div>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/20 p-2 rounded">
                                <div class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ importSummary.matched }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Ready to Import</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="importPreview.length > 0" class="border border-green-200 dark:border-green-800 rounded-lg overflow-hidden">
                        <div class="bg-green-100 dark:bg-green-900/30 px-3 py-2">
                            <h3 class="text-sm font-semibold text-green-700 dark:text-green-300">Preview - Matched Data ({{ importPreview.length }} items)</h3>
                        </div>
                        <div class="overflow-x-auto max-h-80">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-100 dark:bg-gray-700 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-center font-semibold w-16">ID</th>
                                        <th class="px-3 py-2 text-left font-semibold">SAP NO</th>
                                        <th class="px-3 py-2 text-left font-semibold">Source File</th>
                                        <th class="px-3 py-2 text-left font-semibold">Product Unit</th>
                                        <th class="px-3 py-2 text-center font-semibold w-20">Shift 1</th>
                                        <th class="px-3 py-2 text-center font-semibold w-20">Shift 2</th>
                                        <th class="px-3 py-2 text-center font-semibold w-20">Shift 3</th>
                                        <th class="px-3 py-2 text-center font-semibold w-20">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in importPreview"
                                        :key="index"
                                        class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 bg-green-50 dark:bg-green-900/10"
                                    >
                                        <td class="px-3 py-2 text-center">
                                            <span class="inline-block px-2 py-1 bg-blue-100 dark:bg-blue-900/30 rounded font-mono text-xs font-bold text-blue-700 dark:text-blue-300">
                                                #{{ item.existingId }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">
                                                {{ item.materialNumber }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="text-xs text-gray-500 italic truncate max-w-[200px]" :title="item.sourceFile">
                                                📄 {{ item.sourceFile }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ item.matchedProduct.product_unit }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="font-semibold" :class="item.shift1 > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                                {{ item.shift1.toLocaleString() }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="font-semibold" :class="item.shift2 > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                                {{ item.shift2.toLocaleString() }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="font-semibold" :class="item.shift3 > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                                {{ item.shift3.toLocaleString() }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="font-bold text-blue-600 dark:text-blue-400">
                                                {{ item.total.toLocaleString() }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-if="isProcessing" class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <p class="mt-2 text-sm font-medium text-blue-600 dark:text-blue-400">{{ currentProcessingFile || 'Processing CSV...' }}</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        <p v-if="importSummary.matched > 0">
                            <span class="font-semibold text-green-600 dark:text-green-400">{{ importSummary.matched }} materials</span> siap diimport
                            <span v-if="importFiles.length > 1" class="text-blue-600 dark:text-blue-400">
                                dari {{ importFiles.length }} file(s)
                            </span>
                        </p>
                        <p v-else-if="importFiles.length > 0" class="text-yellow-600 dark:text-yellow-400">
                            Tidak ada data yang match dengan SAP NO di database
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="closeImportModal"
                            class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmImport"
                            :disabled="importSummary.matched === 0 || isProcessing"
                            class="px-4 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            <Check class="w-4 h-4" />
                            Import {{ importSummary.matched }} Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
table {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    opacity: 1;
}

input[type="number"] {
    -moz-appearance: textfield;
}

input {
    background: transparent;
}

input:focus {
    background: white;
}

.dark input:focus {
    background: rgb(24, 24, 27);
}
</style>
