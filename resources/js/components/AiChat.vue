<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue';
import axios from 'axios';
import * as XLSX from 'xlsx';

interface Message {
    role: 'user' | 'ai';
    text: string;
    time: string;
    type?: string;
}

interface Alert {
    level: 'danger' | 'warning' | 'info';
    icon: string;
    message: string;
    action: string;
}

interface HistoryItem {
    role: 'user' | 'assistant';
    content: string;
}

const HELPDESK = [
    { name: 'IT Support', number: '62089670971581', display: '0896-7097-1581' },
];

const QUICK_ACTIONS = [
    { label: '📊 Summary Hari Ini',   msg: 'Tampilkan summary hari ini' },
    { label: '🤖 Status Robot',       msg: 'Tampilkan status semua robot' },
    { label: '⚠️ NG Reports',         msg: 'Tampilkan laporan NG terbaru' },
    { label: '🔧 Maintenance',        msg: 'Tampilkan laporan maintenance terbaru' },
    { label: '📦 Transaksi Material', msg: 'Tampilkan transaksi material terbaru' },
    { label: '📈 OEE',                msg: 'Tampilkan data OEE terbaru' },
];

const EXPORT_OPTIONS = [
    { label: 'Robot / ESP32', type: 'robot' },
    { label: 'NG Reports',    type: 'ng' },
    { label: 'Maintenance',   type: 'maintenance' },
    { label: 'OEE',           type: 'oee' },
    { label: 'Material',      type: 'stock' },
    { label: 'Output',        type: 'output' },
];

const isOpen          = ref(false);
const isLoading       = ref(false);
const showExport      = ref(false);
const showHelpdesk    = ref(false);
const showAlerts      = ref(false);
const showQuickActions = ref(false);
const isListening     = ref(false);
const input           = ref('');
const chatBody        = ref<HTMLElement | null>(null);
const alerts          = ref<Alert[]>([]);
const followUps       = ref<string[]>([]);
const chatHistory     = ref<HistoryItem[]>([]);

const messages = ref<Message[]>([
    {
        role: 'ai',
        text: 'Halo! Saya asisten AI 4W Department PT Dharma Polimetal. Ada yang bisa saya bantu?',
        time: now(),
    },
]);

let recognition: any = null;

onMounted(() => {
    fetchAlerts();
    initVoice();
});

function now() {
    return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function formatText(text: string): string {
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/^• (.+)$/gm, '<span class="flex gap-1.5 mb-0.5"><span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-current flex-shrink-0 opacity-50"></span><span>$1</span></span>')
        .replace(/\n/g, '<br/>');
}

async function fetchAlerts() {
    try {
        const { data } = await axios.get('/ai/alerts');
        alerts.value = data.alerts ?? [];
    } catch {}
}

function initVoice() {
    const SR = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
    if (!SR) return;
    recognition = new SR();
    recognition.lang = 'id-ID';
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.onresult = (e: any) => { input.value = e.results[0][0].transcript; isListening.value = false; };
    recognition.onend  = () => { isListening.value = false; };
    recognition.onerror = () => { isListening.value = false; };
}

function toggleVoice() {
    if (!recognition) return;
    if (isListening.value) { recognition.stop(); isListening.value = false; }
    else { recognition.start(); isListening.value = true; }
}

async function send(customMsg?: string) {
    const text = (customMsg ?? input.value).trim();
    if (!text || isLoading.value) return;

    messages.value.push({ role: 'user', text, time: now() });
    input.value      = '';
    followUps.value  = [];
    isLoading.value  = true;
    showExport.value      = false;
    showHelpdesk.value    = false;
    showAlerts.value      = false;
    showQuickActions.value = false;
    await scrollToBottom();

    try {
        const { data } = await axios.post('/ai/chat', { message: text, history: chatHistory.value });
        const aiText = data.reply ?? 'Maaf, tidak ada respons.';
        messages.value.push({ role: 'ai', text: aiText, time: now(), type: data.type });
        followUps.value = data.follow_ups ?? [];
        chatHistory.value.push({ role: 'user', content: text });
        chatHistory.value.push({ role: 'assistant', content: aiText });
        if (chatHistory.value.length > 20) chatHistory.value = chatHistory.value.slice(-20);
    } catch (err: any) {
        const detail = err.response?.data?.detail ?? err.message ?? 'Unknown error';
        messages.value.push({ role: 'ai', text: `❌ Gagal terhubung ke AI.\n${detail}`, time: now() });
    } finally {
        isLoading.value = false;
        await scrollToBottom();
    }
}

async function exportData(type: string) {
    try {
        const { data } = await axios.get('/ai/export-data', { params: { type } });
        if (!data.rows || data.rows.length === 0) {
            messages.value.push({ role: 'ai', text: `⚠️ Tidak ada data untuk diexport (${type}).`, time: now() });
            showExport.value = false;
            return;
        }
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(data.rows);
        const colWidths = (data.rows[0] as string[]).map((_: string, ci: number) => ({
            wch: Math.max(...data.rows.map((row: string[]) => String(row[ci] ?? '').length), 10),
        }));
        ws['!cols'] = colWidths;
        XLSX.utils.book_append_sheet(wb, ws, data.sheetName ?? type);
        XLSX.writeFile(wb, `${type}_${new Date().toISOString().slice(0, 10)}.xlsx`);
        messages.value.push({ role: 'ai', text: `✅ Export *${data.sheetName}* berhasil diunduh.`, time: now() });
    } catch {
        messages.value.push({ role: 'ai', text: '❌ Gagal mengexport data.', time: now() });
    }
    showExport.value = false;
    await scrollToBottom();
}

async function scrollToBottom() {
    await nextTick();
    if (chatBody.value) chatBody.value.scrollTop = chatBody.value.scrollHeight;
}

function handleKey(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
}

function clearChat() {
    messages.value        = [{ role: 'ai', text: 'Chat direset. Ada yang bisa saya bantu?', time: now() }];
    chatHistory.value     = [];
    followUps.value       = [];
    showExport.value      = false;
    showHelpdesk.value    = false;
    showAlerts.value      = false;
    showQuickActions.value = false;
    fetchAlerts();
}

function waLink(number: string, name: string) {
    const msg = encodeURIComponent(`Halo ${name}, saya butuh bantuan terkait sistem 4W Department.`);
    return `https://wa.me/${number}?text=${msg}`;
}

const dangerCount = () => alerts.value.filter(a => a.level === 'danger').length;
const totalAlerts = () => alerts.value.length;

const alertBorderColor = (level: string) => ({
    danger:  'border-l-red-500',
    warning: 'border-l-amber-400',
    info:    'border-l-blue-400',
}[level] ?? 'border-l-gray-400');

const alertTextColor = (level: string) => ({
    danger:  'text-red-400',
    warning: 'text-amber-400',
    info:    'text-blue-400',
}[level] ?? 'text-gray-400');
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">

        <Transition name="chat">
            <div v-if="isOpen" class="chat-window w-[390px] rounded-2xl shadow-2xl flex flex-col overflow-hidden" style="height:600px">

                <div class="chat-header flex items-center gap-3 px-4 py-3 flex-shrink-0">
                    <div class="relative flex-shrink-0">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                        </div>
                        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-[#111] bg-emerald-400"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white leading-tight tracking-tight">AI 4W Department</p>
                        <p class="text-[10px] text-gray-500 leading-tight mt-0.5">PT Dharma Polimetal · Online</p>
                    </div>
                    <div class="flex items-center gap-0.5">
                        <button v-if="alerts.length > 0"
                            @click="showAlerts = !showAlerts; showHelpdesk = false; showExport = false; showQuickActions = false"
                            :class="['header-btn relative', showAlerts ? 'active' : '']"
                            title="Alerts">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <span v-if="dangerCount() > 0" class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 text-white text-[8px] font-bold">{{ dangerCount() }}</span>
                        </button>
                        <button @click="showHelpdesk = !showHelpdesk; showExport = false; showAlerts = false; showQuickActions = false"
                            :class="['header-btn', showHelpdesk ? 'active' : '']"
                            title="Helpdesk WA">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.72 12 19.79 19.79 0 0 1 1.65 3.18 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.5a16 16 0 0 0 6 6l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button @click="showExport = !showExport; showHelpdesk = false; showAlerts = false; showQuickActions = false"
                            :class="['header-btn', showExport ? 'active' : '']"
                            title="Export Excel">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </button>
                        <button @click="clearChat" class="header-btn" title="Reset Chat">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        </button>
                        <button @click="isOpen = false" class="header-btn" title="Tutup">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </div>

                <Transition name="panel">
                    <div v-if="showAlerts" class="chat-panel flex-shrink-0 overflow-hidden">
                        <div class="px-3 py-2.5 space-y-1.5 max-h-48 overflow-y-auto">
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-widest mb-2">🔔 {{ totalAlerts() }} Alert Aktif</p>
                            <button v-for="(alert, i) in alerts" :key="i"
                                @click="send(alert.action); showAlerts = false"
                                :class="['w-full flex items-center gap-2.5 rounded-lg border-l-4 bg-white/5 hover:bg-white/[0.08] px-3 py-2 text-left transition-colors', alertBorderColor(alert.level)]">
                                <span class="text-base flex-shrink-0">{{ alert.icon }}</span>
                                <span :class="['text-xs font-medium flex-1', alertTextColor(alert.level)]">{{ alert.message }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 text-gray-600"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                    </div>
                </Transition>

                <Transition name="panel">
                    <div v-if="showHelpdesk" class="chat-panel flex-shrink-0">
                        <div class="px-4 py-3 space-y-2.5">
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-widest">Helpdesk via WhatsApp</p>
                            <div v-for="h in HELPDESK" :key="h.number" class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-200">{{ h.name }}</p>
                                    <p class="text-[11px] text-gray-500">{{ h.display }}</p>
                                </div>
                                <a :href="waLink(h.number, h.name)" target="_blank" rel="noopener noreferrer"
                                    class="flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 px-2.5 py-1 text-xs font-semibold text-white transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                                    Chat
                                </a>
                            </div>
                            <p class="text-[10px] text-gray-600">Jam operasional: Senin–Jumat, 07.00–16.00 WIB</p>
                        </div>
                    </div>
                </Transition>

                <Transition name="panel">
                    <div v-if="showExport" class="chat-panel flex-shrink-0">
                        <div class="px-4 py-3">
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-widest mb-2.5">Export ke Excel (.xlsx)</p>
                            <div class="grid grid-cols-3 gap-1.5">
                                <button v-for="opt in EXPORT_OPTIONS" :key="opt.type" @click="exportData(opt.type)"
                                    class="export-btn">
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>

                <div ref="chatBody" class="chat-body flex-1 overflow-y-auto p-4 space-y-4 scroll-smooth">
                    <div v-for="(msg, i) in messages" :key="i" :class="['flex gap-2.5', msg.role === 'user' ? 'flex-row-reverse' : 'flex-row']">
                        <div v-if="msg.role === 'ai'" class="chat-avatar flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                        </div>
                        <div :class="['max-w-[80%] flex flex-col gap-1', msg.role === 'user' ? 'items-end' : 'items-start']">
                            <div :class="['rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed', msg.role === 'user' ? 'chat-bubble-user rounded-tr-sm' : 'chat-bubble-ai rounded-tl-sm']"
                                v-html="msg.role === 'ai' ? formatText(msg.text) : msg.text"/>
                            <span class="text-[10px] text-gray-600 px-1">{{ msg.time }}</span>
                        </div>
                    </div>

                    <div v-if="isLoading" class="flex gap-2.5">
                        <div class="chat-avatar flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                        </div>
                        <div class="chat-bubble-ai rounded-2xl rounded-tl-sm px-4 py-3">
                            <div class="flex gap-1 items-center h-4">
                                <span class="typing-dot" style="animation-delay:0ms"/>
                                <span class="typing-dot" style="animation-delay:150ms"/>
                                <span class="typing-dot" style="animation-delay:300ms"/>
                            </div>
                        </div>
                    </div>

                    <Transition name="panel">
                        <div v-if="followUps.length > 0 && !isLoading" class="space-y-1.5">
                            <p class="text-[10px] text-gray-600 px-1 uppercase tracking-widest font-medium">Lanjutkan dengan</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="fu in followUps" :key="fu" @click="send(fu)"
                                    class="followup-btn">
                                    {{ fu }}
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <div v-if="messages.length <= 1 && !isLoading" class="space-y-1.5">
                        <button @click="showQuickActions = !showQuickActions"
                            class="flex items-center gap-1.5 text-[10px] text-gray-500 px-1 uppercase tracking-widest font-medium hover:text-gray-400 transition-colors">
                            <span>Aksi Cepat</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" :class="['transition-transform duration-200', showQuickActions ? 'rotate-180' : '']"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <Transition name="panel">
                            <div v-if="showQuickActions" class="quick-actions-dropdown">
                                <button v-for="qa in QUICK_ACTIONS" :key="qa.label" @click="send(qa.msg)"
                                    class="quick-action-item">
                                    <span>{{ qa.label }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600 flex-shrink-0"><polyline points="9 18 15 12 9 6"/></svg>
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <div class="chat-input-area flex-shrink-0 px-3 pt-2 pb-3">
                    <div class="input-wrapper flex items-end gap-2">
                        <div class="flex-1 flex items-end gap-1.5 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 focus-within:border-white/20 focus-within:bg-white/[0.06] transition-all">
                            <textarea v-model="input" @keydown="handleKey" placeholder="Ketik pertanyaan..." rows="1"
                                class="flex-1 resize-none bg-transparent text-sm text-gray-100 outline-none placeholder:text-gray-600 max-h-24"
                                style="field-sizing: content"/>
                            <button v-if="recognition" @click="toggleVoice"
                                :class="['flex-shrink-0 flex h-6 w-6 items-center justify-center rounded-md transition-all', isListening ? 'bg-red-500/20 text-red-400 animate-pulse' : 'text-gray-600 hover:text-gray-400']"
                                title="Voice input">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
                            </button>
                        </div>
                        <button @click="send()" :disabled="!input.trim() || isLoading"
                            :style="{
                                all: 'unset',
                                display: 'flex',
                                flexShrink: '0',
                                alignItems: 'center',
                                justifyContent: 'center',
                                height: '38px',
                                width: '38px',
                                borderRadius: '12px',
                                backgroundColor: '#ffffff',
                                color: '#111111',
                                cursor: (!input.trim() || isLoading) ? 'not-allowed' : 'pointer',
                                opacity: (!input.trim() || isLoading) ? '0.3' : '1',
                                transition: 'opacity 0.2s',
                                boxSizing: 'border-box',
                            }">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#111111" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <button @click="isOpen = !isOpen"
            class="chat-toggle relative flex h-[52px] w-[52px] items-center justify-center rounded-2xl shadow-xl hover:opacity-90 active:scale-95 transition-all duration-200 select-none">
            <Transition name="icon" mode="out-in">
                <span v-if="!isOpen" key="open">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                </span>
                <svg v-else key="close" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </Transition>
            <span v-if="!isOpen" class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full border-2 border-gray-950 bg-emerald-400"/>
            <span v-if="!isOpen && dangerCount() > 0"
                class="absolute -top-1.5 -left-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-white text-[9px] font-bold border-2 border-gray-950">
                {{ dangerCount() }}
            </span>
        </button>
    </div>
</template>

<style scoped>
.chat-window {
    background: #161616;
    border: 1px solid rgba(255,255,255,0.07);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

.chat-header {
    background: #0f0f0f;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.chat-panel {
    background: #131313;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.chat-body {
    background: #161616;
}

.chat-avatar {
    background: #2a2a2a;
}

.chat-bubble-ai {
    background: #212121;
    color: #d4d4d4;
    border: 1px solid rgba(255,255,255,0.05);
}

.chat-bubble-user {
    background: #ffffff;
    color: #111111;
    font-weight: 450;
}

.chat-input-area {
    background: #0f0f0f;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.chat-toggle {
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.1);
}

.header-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 28px;
    width: 28px;
    border-radius: 8px;
    color: rgba(255,255,255,0.35);
    transition: all 0.15s ease;
    position: relative;
}

.header-btn:hover {
    background: rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.8);
}

.header-btn.active {
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.9);
}

.send-btn {
    background: #ffffff !important;
    color: #111111 !important;
}

.send-btn:not(:disabled):hover {
    background: #e0e0e0 !important;
}

.send-btn:disabled {
    background: #ffffff !important;
    color: #111111 !important;
    opacity: 0.25;
}

.export-btn {
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    padding: 6px 8px;
    font-size: 11px;
    font-weight: 500;
    color: #c8c8c8;
    transition: all 0.15s ease;
    text-align: center;
}

.export-btn:hover {
    background: rgba(255,255,255,0.08);
    border-color: rgba(255,255,255,0.12);
    color: #fff;
}

.followup-btn {
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    padding: 6px 10px;
    font-size: 11.5px;
    color: #b0b0b0;
    transition: all 0.15s ease;
    text-align: left;
}

.followup-btn:hover {
    background: rgba(255,255,255,0.08);
    color: #e8e8e8;
    border-color: rgba(255,255,255,0.12);
}

.quick-actions-dropdown {
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.08);
    background: #1c1c1c;
    overflow: hidden;
}

.quick-action-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 8px 12px;
    font-size: 12.5px;
    color: #c0c0c0;
    text-align: left;
    transition: all 0.12s ease;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.quick-action-item:last-child {
    border-bottom: none;
}

.quick-action-item:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
}

.typing-dot {
    display: inline-block;
    height: 6px;
    width: 6px;
    border-radius: 50%;
    background: #555;
    animation: bounce 1.2s ease infinite;
}

@keyframes bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-4px); }
}

.chat-body::-webkit-scrollbar { width: 3px; }
.chat-body::-webkit-scrollbar-track { background: transparent; }
.chat-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 2px; }
.chat-body::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.15); }

.chat-enter-active, .chat-leave-active { transition: all 0.25s cubic-bezier(0.4,0,0.2,1); }
.chat-enter-from, .chat-leave-to { opacity: 0; transform: translateY(10px) scale(0.98); }
.icon-enter-active, .icon-leave-active { transition: all 0.15s ease; }
.icon-enter-from, .icon-leave-to { opacity: 0; transform: scale(0.6) rotate(30deg); }
.panel-enter-active, .panel-leave-active { transition: all 0.2s ease; overflow: hidden; }
.panel-enter-from, .panel-leave-to { opacity: 0; max-height: 0; }
.panel-enter-to, .panel-leave-from { opacity: 1; max-height: 300px; }
</style>
