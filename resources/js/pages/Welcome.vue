<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Package, AlertTriangle, Wrench, ArrowRight, CheckCircle2, Bot, ClipboardList, Activity, TrendingUp } from 'lucide-vue-next';

type ColorType = 'blue' | 'orange' | 'green' | 'red' | 'black' | 'violet';

interface System {
    id: string;
    title: string;
    description: string;
    icon: string;
    color: ColorType;
    route: string;
    features: string[];
    stats?: {
        users: string;
        uptime: string;
    };
}

interface Props {
    systems: System[];
}

defineProps<Props>();

const hoveredCard = ref<string | null>(null);
const selectedSystem = ref<string | null>(null);

const iconComponents: Record<string, any> = {
    Package,
    AlertTriangle,
    Wrench,
    Bot,
    ClipboardList,
    Activity,
    TrendingUp
};

const colorSchemes: Record<ColorType, {
    gradient: string;
    glow: string;
    cardBg: string;
    headerBg: string;
    text: string;
    badge: string;
    buttonBg: string;
    buttonHover: string;
}> = {
    blue: {
        gradient: 'from-blue-500 to-blue-600',
        glow: 'shadow-blue-500/30',
        cardBg: 'bg-blue-50',
        headerBg: 'bg-gradient-to-br from-blue-100 to-blue-50',
        text: 'text-blue-600',
        badge: 'bg-blue-200 text-blue-800 border-blue-300',
        buttonBg: 'bg-blue-600',
        buttonHover: 'hover:bg-blue-700'
    },
    orange: {
        gradient: 'from-orange-500 to-orange-600',
        glow: 'shadow-orange-500/30',
        cardBg: 'bg-orange-50',
        headerBg: 'bg-gradient-to-br from-orange-100 to-orange-50',
        text: 'text-orange-600',
        badge: 'bg-orange-200 text-orange-800 border-orange-300',
        buttonBg: 'bg-orange-600',
        buttonHover: 'hover:bg-orange-700'
    },
    green: {
        gradient: 'from-green-500 to-green-600',
        glow: 'shadow-green-500/30',
        cardBg: 'bg-green-50',
        headerBg: 'bg-gradient-to-br from-green-100 to-green-50',
        text: 'text-green-600',
        badge: 'bg-green-200 text-green-800 border-green-300',
        buttonBg: 'bg-green-600',
        buttonHover: 'hover:bg-green-700'
    },
    red: {
        gradient: 'from-red-500 to-red-600',
        glow: 'shadow-red-500/30',
        cardBg: 'bg-red-50',
        headerBg: 'bg-gradient-to-br from-red-100 to-red-50',
        text: 'text-red-600',
        badge: 'bg-red-200 text-red-800 border-red-300',
        buttonBg: 'bg-red-600',
        buttonHover: 'hover:bg-red-700'
    },
    black: {
        gradient: 'from-gray-800 to-black',
        glow: 'shadow-gray-800/30',
        cardBg: 'bg-gray-100',
        headerBg: 'bg-gradient-to-br from-gray-300 to-gray-200',
        text: 'text-gray-900',
        badge: 'bg-gray-300 text-gray-900 border-gray-400',
        buttonBg: 'bg-gray-800',
        buttonHover: 'hover:bg-black'
    },
    violet: {
        gradient: 'from-violet-500 to-violet-600',
        glow: 'shadow-violet-500/30',
        cardBg: 'bg-violet-50',
        headerBg: 'bg-gradient-to-br from-violet-100 to-violet-50',
        text: 'text-violet-600',
        badge: 'bg-violet-200 text-violet-800 border-violet-300',
        buttonBg: 'bg-violet-600',
        buttonHover: 'hover:bg-violet-700'
    }
};
</script>
<template>
    <Head title="Pilih Sistem" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-100 relative overflow-hidden">
        <div class="absolute inset-0 overflow-hidden pointer-events-none opacity-40">
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-200 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-purple-200 rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 w-96 h-96 bg-cyan-200 rounded-full blur-3xl"></div>
        </div>

        <div class="container mx-auto px-4 py-12 relative z-10">
            <div class="text-center mb-12">
                <h1 class="text-5xl md:text-6xl font-bold text-slate-900 mb-4 px-4">
                    Welcome
                </h1>
                <h3 class="text-2xl md:text-2xl text-slate-900 mb-4 px-4">
                    4 Wheel Departement System
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6 max-w-7xl mx-auto mb-12">
                <div
                    v-for="system in systems"
                    :key="system.id"
                    :class="[
                        'group relative transition-all duration-300 h-full',
                        hoveredCard === system.id ? 'scale-105 z-10' : 'scale-100',
                        selectedSystem === system.id ? 'ring-4 ring-slate-400/50' : ''
                    ]"
                    @mouseenter="hoveredCard = system.id"
                    @mouseleave="hoveredCard = null"
                    @click="selectedSystem = system.id"
                >
                    <div :class="[
                        'absolute -inset-0.5 bg-gradient-to-r rounded-2xl blur opacity-0 group-hover:opacity-60 transition duration-300',
                        colorSchemes[system.color].gradient
                    ]"></div>

                    <div class="relative bg-white rounded-2xl border-2 border-slate-200 overflow-hidden hover:border-slate-300 transition-all duration-300 shadow-lg hover:shadow-xl h-full flex flex-col">
                        <div :class="[
                            'p-6 relative overflow-hidden',
                            colorSchemes[system.color].headerBg
                        ]">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-white/30 rounded-full -mr-16 -mt-16"></div>

                            <div class="flex items-start justify-between mb-4 relative z-10">
                                <div :class="[
                                    'w-16 h-16 rounded-xl bg-gradient-to-br flex items-center justify-center shadow-md transition-all duration-300',
                                    colorSchemes[system.color].gradient,
                                    hoveredCard === system.id ? colorSchemes[system.color].glow + ' shadow-xl rotate-6 scale-110' : ''
                                ]">
                                    <component :is="iconComponents[system.icon]" class="w-8 h-8 text-white" />
                                </div>
                                <span :class="[
                                    'px-3 py-1 rounded-full text-xs font-semibold border',
                                    colorSchemes[system.color].badge
                                ]">
                                    Active
                                </span>
                            </div>

                            <h3 class="text-2xl font-bold text-slate-900 mb-2">
                                {{ system.title }}
                            </h3>
                            <p class="text-slate-700 text-sm leading-relaxed">
                                {{ system.description }}
                            </p>

                            <div v-if="system.stats" class="flex items-center gap-4 mt-4 pt-4 border-t border-slate-300/50">
                                <div>
                                    <p class="text-xs text-slate-600 font-medium">Active Users</p>
                                    <p class="text-sm font-bold text-slate-900">{{ system.stats.users }}</p>
                                </div>
                                <div class="w-px h-8 bg-slate-300/50"></div>
                                <div>
                                    <p class="text-xs text-slate-600 font-medium">Uptime</p>
                                    <p class="text-sm font-bold text-slate-900">{{ system.stats.uptime }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 flex-1 flex flex-col">
                            <div class="space-y-3 mb-6 flex-1">
                                <div
                                    v-for="(feature, idx) in system.features"
                                    :key="idx"
                                    :class="[
                                        'flex items-start gap-3 text-sm text-slate-700 transition-all duration-300',
                                        hoveredCard === system.id ? 'translate-x-2' : ''
                                    ]"
                                    :style="{ transitionDelay: `${idx * 50}ms` }"
                                >
                                    <CheckCircle2 :class="[
                                        'w-4 h-4 mt-0.5 flex-shrink-0',
                                        colorSchemes[system.color].text
                                    ]" />
                                    <span class="leading-relaxed">{{ feature }}</span>
                                </div>
                            </div>

                            <Link
                                :href="system.route"
                                :class="[
                                    'w-full py-3 px-4 rounded-xl text-white font-semibold flex items-center justify-center gap-2 transition-all duration-300 shadow-md hover:shadow-lg',
                                    colorSchemes[system.color].buttonBg,
                                    colorSchemes[system.color].buttonHover,
                                    hoveredCard === system.id ? colorSchemes[system.color].glow : ''
                                ]"
                            >
                                <span>Masuk ke Sistem</span>
                                <ArrowRight :class="[
                                    'w-4 h-4 transition-transform duration-300',
                                    hoveredCard === system.id ? 'translate-x-2' : ''
                                ]" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center space-y-4">
                <div class="flex items-center justify-center gap-2 text-slate-600 text-sm">
                    <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                    <span class="font-medium">All systems operational</span>
                </div>
                <p class="text-sm text-slate-500">
                    Copyright © 2025 | 4 Wheel Departement - All Rights Reserved
                </p>
            </div>
        </div>
    </div>
</template>
