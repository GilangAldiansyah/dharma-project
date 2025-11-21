<!-- eslint-disable prefer-const -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Plus, Trash2, Upload, X, Check, Trash, AlertTriangle } from 'lucide-vue-next';
import axios from 'axios';

interface StockData {
    id: number | null;
    sap_finish: string;
    id_sap: string;
    material_name: string;
    part_no: string;
    part_name: string;
    type: string;
    qty_unit: number;
    qty_day: number;
    stock_date: string;
    stock_awal: number;
    produksi_shift1: number;
    produksi_shift2: number;
    produksi_shift3: number;
    out_shift1: number;
    out_shift2: number;
    out_shift3: number;
    ng_shift1: number;
    ng_shift2: number;
    ng_shift3: number;
    total_produksi: number;
    total_out: number;
    soh: number;
    bl_type: 'BL1' | 'BL2';
}

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
    ng_shift1: number;
    ng_shift2: number;
    total: number;
}

interface Props {
    stockData: StockData[];
    stockDataBL2: StockData[];
    outputData: OutputData[];
    selectedDate: string;
}

const props = defineProps<Props>();

const selectedDate = ref(props.selectedDate);
const stockData = ref<StockData[]>(props.stockData);
const stockDataBL2 = ref<StockData[]>(props.stockDataBL2 || []);
const savingRows = ref<Set<number>>(new Set());

// Tab state
const activeTab = ref<'BL1' | 'BL2'>('BL1');

// Selection state for delete
const selectedRows = ref<Set<number>>(new Set());

// Computed current data based on active tab
const currentStockData = computed(() =>
    activeTab.value === 'BL1' ? stockData.value : stockDataBL2.value
);

// Check if all rows selected
const isAllSelected = computed(() =>
    selectedRows.value.size > 0 && selectedRows.value.size === currentStockData.value.length
);

const negativeStockCount = computed(() => {
    return currentStockData.value.filter(stock => stock.stock_awal < 0 || stock.soh < 0).length;
});

// ==================== MULTIPLE FILES IMPORT ====================
const showImportModal = ref(false);
const importFiles = ref<File[]>([]); // ✅ Array untuk multiple files
const importPreviewBL1 = ref<any[]>([]);
const importPreviewBL2 = ref<any[]>([]);
const isProcessing = ref(false);
const importSummaryBL1 = ref({ total: 0, matched: 0, unmatched: 0 });
const importSummaryBL2 = ref({ total: 0, matched: 0, unmatched: 0 });

const changeDate = () => {
    router.get('/stock', { date: selectedDate.value }, { preserveState: false });
};

let saveTimeouts: { [key: number]: ReturnType<typeof setTimeout> } = {};

const calculateTotals = (stock: StockData) => {
    stock.total_produksi = (stock.produksi_shift1 || 0) + (stock.produksi_shift2 || 0) + (stock.produksi_shift3 || 0);
    stock.total_out = (stock.out_shift1 || 0) + (stock.out_shift2 || 0) + (stock.out_shift3 || 0);
    stock.soh = (stock.stock_awal || 0) + stock.total_produksi - stock.total_out;
};

const autoSave = (stock: StockData, index: number) => {
    calculateTotals(stock);

    if (saveTimeouts[index]) {
        clearTimeout(saveTimeouts[index]);
    }

    savingRows.value.add(index);

    saveTimeouts[index] = setTimeout(async () => {
        try {
            const saveData = {
                id: stock.id,
                sap_finish: stock.sap_finish || '',
                id_sap: stock.id_sap || '',
                material_name: stock.material_name || '',
                part_no: stock.part_no || '',
                part_name: stock.part_name || '',
                type: stock.type || '',
                qty_unit: stock.qty_unit || 0,
                qty_day: stock.qty_day || 0,
                stock_date: stock.stock_date,
                stock_awal: stock.stock_awal || 0,
                produksi_shift1: stock.produksi_shift1 || 0,
                produksi_shift2: stock.produksi_shift2 || 0,
                produksi_shift3: stock.produksi_shift3 || 0,
                out_shift1: stock.out_shift1 || 0,
                out_shift2: stock.out_shift2 || 0,
                out_shift3: stock.out_shift3 || 0,
                ng_shift1: stock.ng_shift1 || 0,
                ng_shift2: stock.ng_shift2 || 0,
                ng_shift3: stock.ng_shift3 || 0,
                bl_type: activeTab.value
            };

            const response = await axios.post('/stock/update', saveData);

            if (response.data.data && response.data.data.id) {
                stock.id = response.data.data.id;
            }

            savingRows.value.delete(index);
        } catch (error: any) {
            console.error('Save error:', error);
            console.error('Response:', error.response?.data);
            savingRows.value.delete(index);
            alert('Error saving data: ' + (error.response?.data?.message || error.message));
        }
    }, 800);
};

const addNewRow = async () => {
    try {
        const response = await axios.post('/stock/update', {
            id: null,
            sap_finish: '',
            id_sap: '',
            material_name: '',
            part_no: '',
            part_name: '',
            stock_date: selectedDate.value,
            stock_awal: 0,
            produksi_shift1: 0,
            produksi_shift2: 0,
            produksi_shift3: 0,
            out_shift1: 0,
            out_shift2: 0,
            out_shift3: 0,
            ng_shift1: 0,
            ng_shift2: 0,
            ng_shift3: 0,
            bl_type: activeTab.value
        });

        const newRow: StockData = {
            id: response.data.data.id,
            sap_finish: '',
            id_sap: '',
            material_name: '',
            part_no: '',
            part_name: '',
            stock_date: selectedDate.value,
            stock_awal: 0,
            produksi_shift1: 0,
            produksi_shift2: 0,
            produksi_shift3: 0,
            out_shift1: 0,
            out_shift2: 0,
            out_shift3: 0,
            ng_shift1: 0,
            ng_shift2: 0,
            ng_shift3: 0,
            total_produksi: 0,
            total_out: 0,
            soh: 0,
            type: '',
            qty_unit: 0,
            qty_day: 0,
            bl_type: activeTab.value
        };

        if (activeTab.value === 'BL1') {
            stockData.value.push(newRow);
        } else {
            stockDataBL2.value.push(newRow);
        }
    } catch (error: any) {
        console.error('Add row error:', error);
        alert('Error adding row: ' + (error.response?.data?.message || error.message));
    }
};

const deleteRow = async (index: number) => {
    const stock = currentStockData.value[index];

    if (!stock.id) {
        if (activeTab.value === 'BL1') {
            stockData.value.splice(index, 1);
        } else {
            stockDataBL2.value.splice(index, 1);
        }
        return;
    }

    if (confirm('Hapus baris ini?')) {
        try {
            await axios.delete(`/stock/${stock.id}`);
            if (activeTab.value === 'BL1') {
                stockData.value.splice(index, 1);
            } else {
                stockDataBL2.value.splice(index, 1);
            }
        } catch (error) {
            console.error('Delete error:', error);
        }
    }
};

// ==================== DELETE FUNCTIONS ====================

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedRows.value.clear();
    } else {
        selectedRows.value = new Set(
            currentStockData.value
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

    if (!confirm(`Hapus ${selectedRows.value.size} data yang dipilih dari ${activeTab.value}?`)) {
        return;
    }

    try {
        const response = await axios.post('/stock/delete-multiple', {
            ids: Array.from(selectedRows.value),
            bl_type: activeTab.value
        });

        if (response.data.success) {
            if (activeTab.value === 'BL1') {
                stockData.value = stockData.value.filter(item => !selectedRows.value.has(item.id!));
            } else {
                stockDataBL2.value = stockDataBL2.value.filter(item => !selectedRows.value.has(item.id!));
            }

            selectedRows.value.clear();
            alert(`✅ ${response.data.message}`);
        }
    } catch (error: any) {
        console.error('Delete selected error:', error);
        alert('❌ Error: ' + (error.response?.data?.message || error.message));
    }
};

const deleteAllData = async () => {
    if (currentStockData.value.length === 0) {
        alert('⚠️ Tidak ada data untuk dihapus');
        return;
    }

    const count = currentStockData.value.length;

    if (!confirm(`⚠️ HAPUS SEMUA ${count} DATA ${activeTab.value} pada tanggal ${selectedDate.value}?\n\n⚠️ PERHATIAN: Tindakan ini tidak bisa dibatalkan!`)) {
        return;
    }

    const confirmText = prompt(`Untuk konfirmasi, ketik: ${activeTab.value}`);
    if (confirmText !== activeTab.value) {
        alert('❌ Konfirmasi dibatalkan');
        return;
    }

    try {
        const response = await axios.post('/stock/delete-all-by-date', {
            date: selectedDate.value,
            bl_type: activeTab.value,
            confirmation: activeTab.value
        });

        if (response.data.success) {
            if (activeTab.value === 'BL1') {
                stockData.value = [];
            } else {
                stockDataBL2.value = [];
            }

            selectedRows.value.clear();
            alert(`✅ ${response.data.message}`);
        }
    } catch (error: any) {
        console.error('Delete all error:', error);
        alert('❌ Error: ' + (error.response?.data?.message || error.message));
    }
};

// ==================== MULTIPLE FILES HANDLER ====================
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        // ✅ Convert FileList to Array (max 5 files)
        const filesArray = Array.from(target.files).slice(0, 5);
        importFiles.value = filesArray;

        // ✅ Parse semua files
        parseMultipleCSVs(filesArray);
    }
};

const removeFile = (index: number) => {
    importFiles.value.splice(index, 1);
    if (importFiles.value.length === 0) {
        importPreviewBL1.value = [];
        importPreviewBL2.value = [];
        importSummaryBL1.value = { total: 0, matched: 0, unmatched: 0 };
        importSummaryBL2.value = { total: 0, matched: 0, unmatched: 0 };
    } else {
        parseMultipleCSVs(importFiles.value);
    }
};

const parseMultipleCSVs = async (files: File[]) => {
    isProcessing.value = true;

    // Reset preview data
    const combinedGroupedBL1 = new Map<string, any>();
    const combinedGroupedBL2 = new Map<string, any>();

    try {
        // ✅ Parse semua file secara sequential
        for (const file of files) {
            await parseSingleCSV(file, combinedGroupedBL1, combinedGroupedBL2);
        }

        // Convert Map to Array
        const previewBL1 = Array.from(combinedGroupedBL1.values()).map(item => ({
            ...item,
            total: item.shift1 + item.shift2 + item.shift3
        }));

        const previewBL2 = Array.from(combinedGroupedBL2.values()).map(item => ({
            ...item,
            total: item.shift1 + item.shift2 + item.shift3
        }));

        importPreviewBL1.value = previewBL1;
        importPreviewBL2.value = previewBL2;

        importSummaryBL1.value = {
            total: previewBL1.length,
            matched: previewBL1.filter(p => p.matched).length,
            unmatched: previewBL1.filter(p => !p.matched).length
        };

        importSummaryBL2.value = {
            total: previewBL2.length,
            matched: previewBL2.filter(p => p.matched).length,
            unmatched: previewBL2.filter(p => !p.matched).length
        };

        console.log('✅ All files parsed:', {
            files: files.length,
            BL1: previewBL1.length,
            BL2: previewBL2.length,
            totalMatched: importSummaryBL1.value.matched + importSummaryBL2.value.matched
        });

    } catch (error) {
        console.error('❌ Parse error:', error);
        alert('Error parsing CSV files: ' + error);
    } finally {
        isProcessing.value = false;
    }
};

// LANJUTAN DARI BAGIAN 1...

const parseSingleCSV = (file: File, groupedBL1: Map<string, any>, groupedBL2: Map<string, any>): Promise<void> => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = (e) => {
            try {
                const text = e.target?.result as string;
                const lines = text.split('\n');

                // ✅ STRATEGI 1: Cari header dengan pattern yang lebih spesifik
                let headerRow = -1;
                let materialCol = -1;
                let descCol = -1;
                let qty1Col = -1;
                let qty2Col = -1;

                // Scan 15 baris pertama untuk header
                for (let i = 0; i < Math.min(15, lines.length); i++) {
                    const line = lines[i];
                    const cols = parseCSVLine(line);

                    // Cari kolom Material Number
                    for (let j = 0; j < cols.length; j++) {
                        const cell = cols[j].toLowerCase().trim();

                        // Deteksi Material Number column
                        if (cell === 'material number' || cell === 'material' || cell === 'part name') {
                            materialCol = j;
                            descCol = j + 1;
                            headerRow = i;
                        }

                        // Deteksi QTY columns
                        if (cell === 'qty' || cell.includes('cycle') && cell.includes('qty')) {
                            if (qty1Col === -1) {
                                qty1Col = j;
                            } else if (qty2Col === -1) {
                                qty2Col = j;
                            }
                        }
                    }

                    if (materialCol !== -1 && qty1Col !== -1) break;
                }

                // ✅ STRATEGI 2: Fallback - Analisis struktur data
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

                // ✅ STRATEGI 3: Deteksi QTY columns berdasarkan POSISI dan TYPE
                if (qty1Col === -1) {
                    console.warn('⚠️ QTY column tidak terdeteksi dari header');

                    const dataStartRow = headerRow !== -1 ? headerRow + 2 : 5;
                    const qtyColumnCandidates = new Map<number, number>();

                    for (let i = dataStartRow; i < Math.min(dataStartRow + 10, lines.length); i++) {
                        const cols = parseCSVLine(lines[i]);

                        for (let j = 9; j < Math.min(20, cols.length); j++) {
                            const val = cols[j].trim();

                            if (!val || val === '' || val === '-') continue;
                            if (val.includes(':')) continue;
                            if (val.includes('/') || val.includes('-') && val.length > 5) continue;

                            const parsed = parseInt(val.replace(/[,\.]/g, ''));

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
                    materialCol,
                    descCol,
                    qty1Col,
                    qty2Col,
                    headerRow,
                    file: file.name
                });

                // ✅ VALIDASI
                if (materialCol === -1 || qty1Col === -1) {
                    console.error('❌ Failed to detect columns in file:', file.name);
                    resolve(); // Continue to next file
                    return;
                }

                // ✅ PARSING DATA
                const startRow = headerRow !== -1 ? headerRow + 2 : 0;

                for (let i = startRow; i < lines.length; i++) {
                    const line = lines[i].trim();
                    if (!line) continue;

                    const columns = parseCSVLine(line);

                    if (columns.length <= Math.max(materialCol, descCol, qty1Col, qty2Col || 0)) continue;

                    const materialNumber = columns[materialCol]?.trim();
                    const materialDesc = columns[descCol]?.trim() || '';

                    // ✅ FILTER
                    if (!materialNumber ||
                        materialNumber === 'Material Number' ||
                        materialNumber === 'PART NAME' ||
                        materialNumber.toLowerCase().includes('shift') ||
                        materialNumber.toLowerCase().includes('proses') ||
                        materialNumber.toLowerCase().includes('daily') ||
                        materialNumber.toLowerCase().includes('weekly')) {
                        continue;
                    }

                    // ✅ EXTRACT QTY
                    const qty1Raw = columns[qty1Col]?.trim() || '';
                    const qty2Raw = qty2Col !== -1 ? (columns[qty2Col]?.trim() || '') : '';

                    let qty1 = 0;
                    let qty2 = 0;

                    if (qty1Raw && !qty1Raw.includes(':') && !qty1Raw.includes('/')) {
                        const parsed = parseInt(qty1Raw.replace(/[,]/g, ''));
                        if (!isNaN(parsed) && parsed >= 0 && !qty1Raw.includes('.')) {
                            qty1 = parsed;
                        }
                    }

                    if (qty2Raw && !qty2Raw.includes(':') && !qty2Raw.includes('/')) {
                        const parsed = parseInt(qty2Raw.replace(/[,]/g, ''));
                        if (!isNaN(parsed) && parsed >= 0 && !qty2Raw.includes('.')) {
                            qty2 = parsed;
                        }
                    }

                    if (qty1 === 0 && qty2 === 0) continue;

                    console.log(`✅ Valid row: Material=${materialNumber}, Shift1=${qty1}, Shift2=${qty2}`);

                    // ✅ MATCHING LOGIC
                    const existingStockBL1 = stockData.value.find(s =>
                        s.bl_type === 'BL1' && (
                            s.id_sap === materialNumber ||
                            s.sap_finish === materialNumber ||
                            (materialDesc && s.material_name &&
                             s.material_name.toLowerCase().includes(materialDesc.toLowerCase().substring(0, 20)))
                        )
                    );

                    const existingStockBL2 = stockDataBL2.value.find(s =>
                        s.bl_type === 'BL2' && (
                            s.id_sap === materialNumber ||
                            s.sap_finish === materialNumber ||
                            (materialDesc && s.material_name &&
                             s.material_name.toLowerCase().includes(materialDesc.toLowerCase().substring(0, 20)))
                        )
                    );

                    // Grouping BL1
                    if (existingStockBL1) {
                        if (!groupedBL1.has(materialNumber)) {
                            groupedBL1.set(materialNumber, {
                                materialNumber,
                                materialDesc: materialDesc || '-',
                                shift1: qty1 || qty2,
                                shift2: 0,
                                shift3: 0,
                                matched: true,
                                existingId: existingStockBL1.id,
                                bl_type: 'BL1'
                            });
                        } else {
                            const existing = groupedBL1.get(materialNumber)!;
                            if (existing.shift1 > 0 && existing.shift2 === 0) {
                                existing.shift2 = qty1 || qty2;
                            } else if (existing.shift2 > 0 && existing.shift3 === 0) {
                                existing.shift3 = qty1 || qty2;
                            }
                        }
                    }

                    // Grouping BL2
                    if (existingStockBL2) {
                        if (!groupedBL2.has(materialNumber)) {
                            groupedBL2.set(materialNumber, {
                                materialNumber,
                                materialDesc: materialDesc || '-',
                                shift1: qty1 || qty2,
                                shift2: 0,
                                shift3: 0,
                                matched: true,
                                existingId: existingStockBL2.id,
                                bl_type: 'BL2'
                            });
                        } else {
                            const existing = groupedBL2.get(materialNumber)!;
                            if (existing.shift1 > 0 && existing.shift2 === 0) {
                                existing.shift2 = qty1 || qty2;
                            } else if (existing.shift2 > 0 && existing.shift3 === 0) {
                                existing.shift3 = qty1 || qty2;
                            }
                        }
                    }
                }

                resolve();
            } catch (error) {
                console.error('❌ Parse error in file:', file.name, error);
                reject(error);
            }
        };

        reader.onerror = () => reject(new Error('Failed to read file: ' + file.name));
        reader.readAsText(file);
    });
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

const confirmImport = async () => {
    const totalMatched = importSummaryBL1.value.matched + importSummaryBL2.value.matched;

    if (totalMatched === 0) {
        alert('Tidak ada data yang match untuk diimport');
        return;
    }

    const hasNullIdBL1 = importPreviewBL1.value.some(item => item.matched && item.existingId === null);
    const hasNullIdBL2 = importPreviewBL2.value.some(item => item.matched && item.existingId === null);

    if (hasNullIdBL1 || hasNullIdBL2) {
        alert('⚠️ Ada data yang belum tersimpan ke database (ID: null).\n\nSilakan:\n1. Klik pada cell mana saja untuk trigger auto-save, ATAU\n2. Refresh halaman ini (F5) agar data tersimpan\n3. Lalu upload CSV lagi');
        return;
    }

    if (!confirm(`Import ${importSummaryBL1.value.matched} data ke BL1 dan ${importSummaryBL2.value.matched} data ke BL2?`)) {
        return;
    }

    isProcessing.value = true;

    try {
        let updatedCountBL1 = 0;
        let updatedCountBL2 = 0;

        for (const item of importPreviewBL1.value) {
            if (item.matched && item.existingId && item.bl_type === 'BL1') {
                const stockIndex = stockData.value.findIndex(s =>
                    s.id === item.existingId && s.bl_type === 'BL1'
                );

                if (stockIndex !== -1) {
                    console.log('Updating BL1:', stockData.value[stockIndex].id_sap, 'Shifts:', item.shift1, item.shift2, item.shift3);

                    stockData.value[stockIndex].produksi_shift1 = item.shift1;
                    stockData.value[stockIndex].produksi_shift2 = item.shift2;
                    stockData.value[stockIndex].produksi_shift3 = item.shift3;
                    calculateTotals(stockData.value[stockIndex]);

                    try {
                        const response = await axios.post('/stock/update', {
                            id: stockData.value[stockIndex].id,
                            bl_type: 'BL1',
                            sap_finish: stockData.value[stockIndex].sap_finish,
                            id_sap: stockData.value[stockIndex].id_sap,
                            material_name: stockData.value[stockIndex].material_name,
                            part_no: stockData.value[stockIndex].part_no,
                            part_name: stockData.value[stockIndex].part_name,
                            type: stockData.value[stockIndex].type,
                            qty_unit: stockData.value[stockIndex].qty_unit,
                            qty_day: stockData.value[stockIndex].qty_day,
                            stock_date: stockData.value[stockIndex].stock_date,
                            stock_awal: stockData.value[stockIndex].stock_awal,
                            produksi_shift1: item.shift1,
                            produksi_shift2: item.shift2,
                            produksi_shift3: item.shift3,
                            out_shift1: stockData.value[stockIndex].out_shift1,
                            out_shift2: stockData.value[stockIndex].out_shift2,
                            out_shift3: stockData.value[stockIndex].out_shift3,
                            ng_shift1: stockData.value[stockIndex].ng_shift1,
                            ng_shift2: stockData.value[stockIndex].ng_shift2,
                            ng_shift3: stockData.value[stockIndex].ng_shift3,
                        });

                        if (response.data.data && response.data.data.id) {
                            stockData.value[stockIndex].id = response.data.data.id;
                        }

                        updatedCountBL1++;
                    } catch (error: any) {
                        console.error('Error updating BL1:', error.response?.data || error);
                    }
                }
            }
        }

        for (const item of importPreviewBL2.value) {
            if (item.matched && item.existingId && item.bl_type === 'BL2') {
                const stockIndex = stockDataBL2.value.findIndex(s =>
                    s.id === item.existingId && s.bl_type === 'BL2'
                );

                if (stockIndex !== -1) {
                    console.log('Updating BL2:', stockDataBL2.value[stockIndex].id_sap, 'Shifts:', item.shift1, item.shift2, item.shift3);

                    stockDataBL2.value[stockIndex].produksi_shift1 = item.shift1;
                    stockDataBL2.value[stockIndex].produksi_shift2 = item.shift2;
                    stockDataBL2.value[stockIndex].produksi_shift3 = item.shift3;
                    calculateTotals(stockDataBL2.value[stockIndex]);

                    try {
                        const response = await axios.post('/stock/update', {
                            id: stockDataBL2.value[stockIndex].id,
                            bl_type: 'BL2',
                            sap_finish: stockDataBL2.value[stockIndex].sap_finish,
                            id_sap: stockDataBL2.value[stockIndex].id_sap,
                            material_name: stockDataBL2.value[stockIndex].material_name,
                            part_no: stockDataBL2.value[stockIndex].part_no,
                            part_name: stockDataBL2.value[stockIndex].part_name,
                            type: stockDataBL2.value[stockIndex].type,
                            qty_unit: stockDataBL2.value[stockIndex].qty_unit,
                            qty_day: stockDataBL2.value[stockIndex].qty_day,
                            stock_date: stockDataBL2.value[stockIndex].stock_date,
                            stock_awal: stockDataBL2.value[stockIndex].stock_awal,
                            produksi_shift1: item.shift1,
                            produksi_shift2: item.shift2,
                            produksi_shift3: item.shift3,
                            out_shift1: stockDataBL2.value[stockIndex].out_shift1,
                            out_shift2: stockDataBL2.value[stockIndex].out_shift2,
                            out_shift3: stockDataBL2.value[stockIndex].out_shift3,
                            ng_shift1: stockDataBL2.value[stockIndex].ng_shift1,
                            ng_shift2: stockDataBL2.value[stockIndex].ng_shift2,
                            ng_shift3: stockDataBL2.value[stockIndex].ng_shift3,
                        });

                        if (response.data.data && response.data.data.id) {
                            stockDataBL2.value[stockIndex].id = response.data.data.id;
                        }

                        updatedCountBL2++;
                    } catch (error: any) {
                        console.error('Error updating BL2:', error.response?.data || error);
                    }
                }
            }
        }

        alert(`Berhasil import:\n- BL1: ${updatedCountBL1} records\n- BL2: ${updatedCountBL2} records\nTotal: ${updatedCountBL1 + updatedCountBL2} records updated`);
        closeImportModal();
    } catch (error) {
        console.error('Import error:', error);
        alert('Error saat import data: ' + error);
    } finally {
        isProcessing.value = false;
    }
};

const closeImportModal = () => {
    showImportModal.value = false;
    importFiles.value = [];
    importPreviewBL1.value = [];
    importPreviewBL2.value = [];
    importSummaryBL1.value = { total: 0, matched: 0, unmatched: 0 };
    importSummaryBL2.value = { total: 0, matched: 0, unmatched: 0 };
};

</script>

<template>
    <Head title="Stock Control BL1 & BL2" />
    <AppLayout :breadcrumbs="[{ title: 'Stock Control BL1 & BL2', href: '/stock' }]">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold">Control Stock BL</h1>
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
                        @click="showImportModal = true"
                        class="flex items-center gap-1 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
                    >
                        <Upload class="w-4 h-4" />
                        Import BSTB
                    </button>
                    <button
                        @click="addNewRow"
                        class="flex items-center gap-1 px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700"
                    >
                        <Plus class="w-4 h-4" />
                        Add Row
                    </button>
                    <button
                        @click="deleteAllData"
                        class="flex items-center gap-1 px-3 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 shadow-md hover:shadow-lg transition"
                        title="Hapus semua data pada tanggal ini"
                    >
                        <Trash class="w-4 h-4" />
                        Delete All
                    </button>
                    <input
                        v-model="selectedDate"
                        type="date"
                        @change="changeDate"
                        class="rounded-md border border-sidebar-border px-3 py-2 dark:bg-sidebar"
                    />
                </div>
            </div>

            <div class="border-b border-sidebar-border">
                <div class="flex gap-1">
                    <button
                        @click="activeTab = 'BL1'; selectedRows.clear()"
                        :class="[
                            'px-6 py-3 text-sm font-medium rounded-t-lg transition-colors',
                            activeTab === 'BL1'
                                ? 'bg-white dark:bg-sidebar border-t-2 border-x border-blue-500 text-blue-600 dark:text-blue-400'
                                : 'bg-gray-50 dark:bg-sidebar-accent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                        ]"
                    >
                        BL1
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                            {{ stockData.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'BL2'; selectedRows.clear()"
                        :class="[
                            'px-6 py-3 text-sm font-medium rounded-t-lg transition-colors',
                            activeTab === 'BL2'
                                ? 'bg-white dark:bg-sidebar border-t-2 border-x border-purple-500 text-purple-600 dark:text-purple-400'
                                : 'bg-gray-50 dark:bg-sidebar-accent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                        ]"
                    >
                        BL2
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">
                            {{ stockDataBL2.length }}
                        </span>
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
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border w-12" rowspan="2">NO</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border" rowspan="2">SAP<br>FINISH</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border" rowspan="2">ID SAP</th>
                                <th class="px-2 py-2 text-left font-semibold border-r border-sidebar-border" rowspan="2">Material Name</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border" rowspan="2">Part NO</th>
                                <th class="px-2 py-2 text-left font-semibold border-r border-sidebar-border" rowspan="2">Part Name</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border w-16" rowspan="2">TYPE</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border w-16" rowspan="2">Qty/<br>unit</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border w-16" rowspan="2">Qty/<br>Day</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border w-20" rowspan="2">Stock<br>Awal</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border bg-green-50 dark:bg-green-900/20" colspan="4">Produksi {{ activeTab }}</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border bg-red-50 dark:bg-red-900/20" colspan="4">OUT</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border bg-yellow-50 dark:bg-yellow-900/20" colspan="3">NG Single</th>
                                <th class="px-2 py-2 text-center font-semibold border-r border-sidebar-border w-20" rowspan="2">SOH</th>
                                <th class="px-2 py-2 text-center font-semibold w-12" rowspan="2">Act</th>
                            </tr>
                            <tr class="border-b border-sidebar-border">
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-green-50 dark:bg-green-900/20 w-16">Shift 1</th>
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-green-50 dark:bg-green-900/20 w-16">Shift 2</th>
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-green-50 dark:bg-green-900/20 w-16">Shift 3</th>
                                <th class="px-2 py-1 text-center text-xs border-r border-sidebar-border bg-green-50 dark:bg-green-900/20 w-20">Total</th>

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
                            <tr
                                v-for="(stock, index) in currentStockData"
                                :key="stock.id || `new-${index}`"
                                class="border-b border-sidebar-border hover:bg-gray-50 dark:hover:bg-sidebar-accent/30 transition-colors"
                                :class="{
                                    'bg-blue-50/50 dark:bg-blue-900/10': savingRows.has(index),
                                    'bg-blue-100 dark:bg-blue-900/20': stock.id && selectedRows.has(stock.id)
                                }"
                            >
                                <td class="px-2 py-1 text-center border-r border-sidebar-border">
                                    <input
                                        v-if="stock.id"
                                        type="checkbox"
                                        :checked="selectedRows.has(stock.id)"
                                        @change="toggleSelectRow(stock.id)"
                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    />
                                </td>
                                <td class="px-2 py-1 text-center border-r border-sidebar-border">{{ index + 1 }}</td>

                                <td class="px-1 py-1 border-r border-sidebar-border">
                                    <input
                                        v-model="stock.sap_finish"
                                        @input="autoSave(stock, index)"
                                        type="text"
                                        class="w-full min-w-[140px] text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border">
                                    <input
                                        v-model="stock.id_sap"
                                        @input="autoSave(stock, index)"
                                        type="text"
                                        class="w-full min-w-[145px] text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border">
                                    <input
                                        v-model="stock.material_name"
                                        @input="autoSave(stock, index)"
                                        type="text"
                                        class="w-full min-w-[180px] text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border">
                                    <input
                                        v-model="stock.part_no"
                                        @input="autoSave(stock, index)"
                                        type="text"
                                        class="w-full min-w-[78px] text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border">
                                    <input
                                        v-model="stock.part_name"
                                        @input="autoSave(stock, index)"
                                        type="text"
                                        class="w-full min-w-[180px] text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                    />
                                </td>

                                <td class="px-1 py-1 border-r border-sidebar-border">
                                    <input
                                        v-model="stock.type"
                                        @input="autoSave(stock, index)"
                                        type="text"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                    />
                                </td>

                                <td class="px-1 py-1 border-r border-sidebar-border">
                                    <input
                                        v-model.number="stock.qty_unit"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                    />
                                </td>

                                <td class="px-1 py-1 border-r border-sidebar-border">
                                    <input
                                        v-model.number="stock.qty_day"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-blue-500 dark:bg-sidebar"
                                    />
                                </td>

                                <td class="px-1 py-1 border-r border-sidebar-border relative">
                                    <input
                                        v-model.number="stock.stock_awal"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 dark:bg-sidebar"
                                        :class="{
                                            'focus:ring-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200 font-semibold': stock.stock_awal < 0,
                                            'focus:ring-blue-500': stock.stock_awal >= 0
                                        }"
                                        :title="stock.stock_awal < 0 ? '⚠️ Stock Awal Minus - Perlu Koreksi Manual' : ''"
                                    />
                                    <AlertTriangle
                                        v-if="stock.stock_awal < 0"
                                        class="w-3 h-3 text-yellow-600 absolute top-1 right-1 pointer-events-none"
                                    />
                                </td>

                                <td class="px-1 py-1 border-r border-sidebar-border bg-green-50/50 dark:bg-green-900/10">
                                    <input
                                        v-model.number="stock.produksi_shift1"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-green-500 bg-transparent"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border bg-green-50/50 dark:bg-green-900/10">
                                    <input
                                        v-model.number="stock.produksi_shift2"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-green-500 bg-transparent"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border bg-green-50/50 dark:bg-green-900/10">
                                    <input
                                        v-model.number="stock.produksi_shift3"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-green-500 bg-transparent"
                                    />
                                </td>
                                <td class="px-2 py-1 text-center text-xs font-semibold border-r border-sidebar-border bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-300">
                                    {{ stock.total_produksi.toLocaleString() }}
                                </td>

                                <td class="px-1 py-1 border-r border-sidebar-border bg-red-50/50 dark:bg-red-900/10">
                                    <input
                                        v-model.number="stock.out_shift1"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-red-500 bg-transparent"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border bg-red-50/50 dark:bg-red-900/10">
                                    <input
                                        v-model.number="stock.out_shift2"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-red-500 bg-transparent"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border bg-red-50/50 dark:bg-red-900/10">
                                    <input
                                        v-model.number="stock.out_shift3"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-red-500 bg-transparent"
                                    />
                                </td>
                                <td class="px-2 py-1 text-center text-xs font-semibold border-r border-sidebar-border bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-300">
                                    {{ stock.total_out.toLocaleString() }}
                                </td>

                                <td class="px-1 py-1 border-r border-sidebar-border bg-yellow-50/50 dark:bg-yellow-900/10">
                                    <input
                                        v-model.number="stock.ng_shift1"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-yellow-500 bg-transparent"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border bg-yellow-50/50 dark:bg-yellow-900/10">
                                    <input
                                        v-model.number="stock.ng_shift2"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-yellow-500 bg-transparent"
                                    />
                                </td>
                                <td class="px-1 py-1 border-r border-sidebar-border bg-yellow-50/50 dark:bg-yellow-900/10">
                                    <input
                                        v-model.number="stock.ng_shift3"
                                        @input="autoSave(stock, index)"
                                        type="number"
                                        min="0"
                                        class="w-full text-center text-xs rounded border-0 px-1 py-0.5 focus:ring-1 focus:ring-yellow-500 bg-transparent"
                                    />
                                </td>

                                <td class="px-2 py-1 text-center text-xs font-bold border-r border-sidebar-border relative"
                                    :class="{
                                        'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300': stock.soh < 0,
                                        'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300': stock.soh >= 0
                                    }"
                                    :title="stock.soh < 0 ? '⚠️ SOH Minus - Perlu Koreksi' : ''"
                                >
                                    {{ stock.soh.toLocaleString() }}
                                    <AlertTriangle
                                        v-if="stock.soh < 0"
                                        class="w-3 h-3 text-red-600 absolute top-1 right-1"
                                    />
                                </td>

                                <td class="px-2 py-1 text-center">
                                    <button
                                        @click="deleteRow(index)"
                                        class="p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded"
                                        title="Delete"
                                    >
                                        <Trash2 class="w-3 h-3" />
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="currentStockData.length === 0">
                                <td colspan="24" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="text-4xl">📦</span>
                                        <span class="text-sm">Tidak ada data untuk {{ activeTab }} pada {{ selectedDate }}</span>
                                        <button
                                            @click="addNewRow"
                                            class="mt-2 flex items-center gap-1 px-3 py-1.5 bg-green-600 text-white text-xs rounded-md hover:bg-green-700"
                                        >
                                            <Plus class="w-3 h-3" />
                                            Tambah Data
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-xs text-muted-foreground flex justify-between items-center">
                <span v-if="savingRows.size > 0" class="text-blue-600 dark:text-blue-400">Auto-saving...</span>
                <span v-else>{{ activeTab }}: {{ currentStockData.length }} items</span>

                <div class="flex gap-4">
                    <span v-if="negativeStockCount > 0" class="text-yellow-600 dark:text-yellow-400 font-medium flex items-center gap-1">
                        <AlertTriangle class="w-3 h-3" />
                        {{ negativeStockCount }} stock minus
                    </span>
                    <span v-if="selectedRows.size > 0" class="text-blue-600 dark:text-blue-400 font-medium">
                        {{ selectedRows.size }} data dipilih
                    </span>
                </div>
            </div>

            <!-- ==================== IMPORT MODAL WITH MULTIPLE FILES ==================== -->
            <div v-if="showImportModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] flex flex-col">
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold">Import Data BSTB (Max 5 Files)</h2>
                        <button @click="closeImportModal" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        <!-- ✅ MULTIPLE FILE UPLOAD AREA -->
                        <div class="space-y-2">
                            <label class="flex-1 flex items-center justify-center px-4 py-8 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-500 dark:hover:border-blue-400 transition">
                                <div class="text-center">
                                    <Upload class="w-12 h-12 mx-auto mb-2 text-gray-400" />
                                    <span class="text-sm font-medium">
                                        Click to upload CSV files (Max 5 files)
                                    </span>
                                    <p class="text-xs text-gray-500 mt-1">Format: CSV | Multiple selection allowed</p>
                                </div>
                                <input type="file" accept=".csv" multiple @change="handleFileSelect" class="hidden" />
                            </label>

                            <!-- ✅ DISPLAY SELECTED FILES -->
                            <div v-if="importFiles.length > 0" class="space-y-2">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Selected Files ({{ importFiles.length }}/5):</p>
                                <div class="grid grid-cols-1 gap-2">
                                    <div
                                        v-for="(file, index) in importFiles"
                                        :key="index"
                                        class="flex items-center justify-between p-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Check class="w-4 h-4 text-green-600" />
                                            <span class="text-xs font-mono">{{ file.name }}</span>
                                            <span class="text-xs text-gray-500">({{ (file.size / 1024).toFixed(1) }} KB)</span>
                                        </div>
                                        <button
                                            @click="removeFile(index)"
                                            class="p-1 hover:bg-red-100 dark:hover:bg-red-900/30 rounded"
                                            title="Remove file"
                                        >
                                            <X class="w-4 h-4 text-red-600" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SUMMARY -->
                        <div v-if="importPreviewBL1.length > 0 || importPreviewBL2.length > 0" class="grid grid-cols-2 gap-4">
                            <div class="border-2 border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <h3 class="text-sm font-semibold text-blue-600 dark:text-blue-400 mb-3">BL1 Summary</h3>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 p-2 rounded">
                                        <div class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ importSummaryBL1.total }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Total</div>
                                    </div>
                                    <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded">
                                        <div class="text-xl font-bold text-green-600 dark:text-green-400">{{ importSummaryBL1.matched }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Matched</div>
                                    </div>
                                    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-2 rounded">
                                        <div class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ importSummaryBL1.unmatched }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Unmatched</div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-2 border-purple-200 dark:border-purple-800 rounded-lg p-4">
                                <h3 class="text-sm font-semibold text-purple-600 dark:text-purple-400 mb-3">BL2 Summary</h3>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="bg-purple-50 dark:bg-purple-900/20 p-2 rounded">
                                        <div class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ importSummaryBL2.total }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Total</div>
                                    </div>
                                    <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded">
                                        <div class="text-xl font-bold text-green-600 dark:text-green-400">{{ importSummaryBL2.matched }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Matched</div>
                                    </div>
                                    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-2 rounded">
                                        <div class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ importSummaryBL2.unmatched }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">Unmatched</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PREVIEW TABLES -->
                        <div v-if="importPreviewBL1.length > 0 || importPreviewBL2.length > 0" class="grid grid-cols-2 gap-4">
                            <div v-if="importPreviewBL1.length > 0" class="border border-blue-200 dark:border-blue-800 rounded-lg overflow-hidden">
                                <div class="bg-blue-100 dark:bg-blue-900/30 px-3 py-2">
                                    <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-300">BL1 Preview</h3>
                                </div>
                                <div class="overflow-x-auto max-h-80">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-100 dark:bg-gray-700 sticky top-0">
                                            <tr>
                                                <th class="px-2 py-2 text-left font-semibold">Status</th>
                                                <th class="px-2 py-2 text-left font-semibold">Material</th>
                                                <th class="px-2 py-2 text-center font-semibold">S1</th>
                                                <th class="px-2 py-2 text-center font-semibold">S2</th>
                                                <th class="px-2 py-2 text-center font-semibold">S3</th>
                                                <th class="px-2 py-2 text-center font-semibold">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(item, index) in importPreviewBL1"
                                                :key="index"
                                                class="border-t border-gray-200 dark:border-gray-700"
                                                :class="{
                                                    'bg-green-50 dark:bg-green-900/10': item.matched,
                                                    'bg-yellow-50 dark:bg-yellow-900/10': !item.matched
                                                }"
                                            >
                                                <td class="px-2 py-2">
                                                    <Check v-if="item.matched" class="w-4 h-4 text-green-600" />
                                                    <X v-else class="w-4 h-4 text-yellow-600" />
                                                </td>
                                                <td class="px-2 py-2 font-mono text-xs">{{ item.materialNumber }}</td>
                                                <td class="px-2 py-2 text-center font-semibold">{{ item.shift1 }}</td>
                                                <td class="px-2 py-2 text-center font-semibold">{{ item.shift2 }}</td>
                                                <td class="px-2 py-2 text-center font-semibold">{{ item.shift3 }}</td>
                                                <td class="px-2 py-2 text-center font-bold text-blue-600">{{ item.total }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div v-if="importPreviewBL2.length > 0" class="border border-purple-200 dark:border-purple-800 rounded-lg overflow-hidden">
                                <div class="bg-purple-100 dark:bg-purple-900/30 px-3 py-2">
                                    <h3 class="text-sm font-semibold text-purple-700 dark:text-purple-300">BL2 Preview</h3>
                                </div>
                                <div class="overflow-x-auto max-h-80">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-100 dark:bg-gray-700 sticky top-0">
                                            <tr>
                                                <th class="px-2 py-2 text-left font-semibold">Status</th>
                                                <th class="px-2 py-2 text-left font-semibold">Material</th>
                                                <th class="px-2 py-2 text-center font-semibold">S1</th>
                                                <th class="px-2 py-2 text-center font-semibold">S2</th>
                                                <th class="px-2 py-2 text-center font-semibold">S3</th>
                                                <th class="px-2 py-2 text-center font-semibold">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(item, index) in importPreviewBL2"
                                                :key="index"
                                                class="border-t border-gray-200 dark:border-gray-700"
                                                :class="{
                                                    'bg-green-50 dark:bg-green-900/10': item.matched,
                                                    'bg-yellow-50 dark:bg-yellow-900/10': !item.matched
                                                }"
                                            >
                                                <td class="px-2 py-2">
                                                    <Check v-if="item.matched" class="w-4 h-4 text-green-600" />
                                                    <X v-else class="w-4 h-4 text-yellow-600" />
                                                </td>
                                                <td class="px-2 py-2 font-mono text-xs">{{ item.materialNumber }}</td>
                                                <td class="px-2 py-2 text-center font-semibold">{{ item.shift1 }}</td>
                                                <td class="px-2 py-2 text-center font-semibold">{{ item.shift2 }}</td>
                                                <td class="px-2 py-2 text-center font-semibold">{{ item.shift3 }}</td>
                                                <td class="px-2 py-2 text-center font-bold text-purple-600">{{ item.total }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div v-if="isProcessing" class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Processing {{ importFiles.length }} file(s)...</p>
                        </div>

                        <!-- DEBUG INFO -->
                        <div v-if="importPreviewBL1.length > 0 || importPreviewBL2.length > 0" class="text-xs bg-gray-50 dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700">
                            <p class="font-semibold mb-2 text-gray-700 dark:text-gray-300">📊 Debug Info:</p>
                            <div class="space-y-1 text-gray-600 dark:text-gray-400">
                                <p>Files uploaded: <span class="font-semibold text-blue-600">{{ importFiles.length }}</span></p>
                                <p>Total data di BL1: <span class="font-semibold">{{ stockData.length }}</span> (ID null: <span class="font-semibold text-yellow-600">{{ stockData.filter(s => s.id === null).length }}</span>)</p>
                                <p>Total data di BL2: <span class="font-semibold">{{ stockDataBL2.length }}</span> (ID null: <span class="font-semibold text-yellow-600">{{ stockDataBL2.filter(s => s.id === null).length }}</span>)</p>
                                <p>Match dengan ID null - BL1: <span class="font-semibold text-yellow-600">{{ importPreviewBL1.filter(i => i.matched && i.existingId === null).length }}</span></p>
                                <p>Match dengan ID null - BL2: <span class="font-semibold text-yellow-600">{{ importPreviewBL2.filter(i => i.matched && i.existingId === null).length }}</span></p>
                            </div>
                            <div v-if="stockData.filter(s => s.id === null).length > 0 || stockDataBL2.filter(s => s.id === null).length > 0 || importPreviewBL1.filter(i => i.matched && i.existingId === null).length > 0 || importPreviewBL2.filter(i => i.matched && i.existingId === null).length > 0"
                                 class="mt-3 p-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
                                <p class="font-semibold text-yellow-700 dark:text-yellow-400">⚠️ Data Belum Tersimpan</p>
                                <p class="mt-1 text-yellow-600 dark:text-yellow-300">Ada data dengan ID null yang belum tersimpan ke database.</p>
                                <p class="mt-1 text-yellow-600 dark:text-yellow-300 font-semibold">Solusi: Refresh halaman (F5) atau klik pada cell mana saja untuk trigger auto-save, lalu upload CSV lagi.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            <p v-if="importSummaryBL1.matched > 0 || importSummaryBL2.matched > 0">
                                <span class="font-semibold text-blue-600 dark:text-blue-400">BL1: {{ importSummaryBL1.matched }} data</span> |
                                <span class="font-semibold text-purple-600 dark:text-purple-400">BL2: {{ importSummaryBL2.matched }} data</span>
                                | <span class="font-semibold">{{ importFiles.length }} file(s)</span>
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
                                :disabled="(importSummaryBL1.matched + importSummaryBL2.matched) === 0 || isProcessing"
                                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Import {{ importSummaryBL1.matched + importSummaryBL2.matched }} Data
                            </button>
                        </div>
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

input[type="number"]:hover::-webkit-inner-spin-button,
input[type="number"]:hover::-webkit-outer-spin-button {
    opacity: 1;
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
