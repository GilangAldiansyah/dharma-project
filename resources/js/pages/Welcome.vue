<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Package, AlertTriangle, Wrench, CheckCircle2, Bot, ClipboardList, Activity, TrendingUp, Layers } from 'lucide-vue-next';
import AiChat from '@/components/AiChat.vue';
type ColorType = 'blue' | 'orange' | 'green' | 'red' | 'black' | 'violet' | 'indigo' | 'emerald';

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

const iconComponents: Record<string, any> = {
    Package,
    AlertTriangle,
    Wrench,
    Bot,
    ClipboardList,
    Activity,
    TrendingUp,
    Layers
};

const colorSchemes: Record<ColorType, {
    gradient: string;
    glow: string;
    cardBg: string;
    text: string;
    iconBg: string;
    buttonBg: string;
    buttonHover: string;
    badge: string;
}> = {
    blue: {
        gradient: 'from-blue-600 to-blue-500',
        glow: 'shadow-blue-500/30',
        cardBg: 'bg-gradient-to-br from-blue-50 to-blue-100/50',
        text: 'text-blue-600',
        iconBg: 'bg-blue-600',
        buttonBg: 'bg-blue-600',
        buttonHover: 'hover:bg-blue-700',
        badge: 'bg-blue-100 text-blue-700'
    },
    orange: {
        gradient: 'from-orange-600 to-orange-500',
        glow: 'shadow-orange-500/30',
        cardBg: 'bg-gradient-to-br from-orange-50 to-orange-100/50',
        text: 'text-orange-600',
        iconBg: 'bg-orange-600',
        buttonBg: 'bg-orange-600',
        buttonHover: 'hover:bg-orange-700',
        badge: 'bg-orange-100 text-orange-700'
    },
    green: {
        gradient: 'from-green-600 to-green-500',
        glow: 'shadow-green-500/30',
        cardBg: 'bg-gradient-to-br from-green-50 to-green-100/50',
        text: 'text-green-600',
        iconBg: 'bg-green-600',
        buttonBg: 'bg-green-600',
        buttonHover: 'hover:bg-green-700',
        badge: 'bg-green-100 text-green-700'
    },
    red: {
        gradient: 'from-red-600 to-red-500',
        glow: 'shadow-red-500/30',
        cardBg: 'bg-gradient-to-br from-red-50 to-red-100/50',
        text: 'text-red-600',
        iconBg: 'bg-red-600',
        buttonBg: 'bg-red-600',
        buttonHover: 'hover:bg-red-700',
        badge: 'bg-red-100 text-red-700'
    },
    black: {
        gradient: 'from-gray-800 to-gray-700',
        glow: 'shadow-gray-700/30',
        cardBg: 'bg-gradient-to-br from-gray-50 to-gray-100/50',
        text: 'text-gray-700',
        iconBg: 'bg-gray-800',
        buttonBg: 'bg-gray-800',
        buttonHover: 'hover:bg-gray-900',
        badge: 'bg-gray-100 text-gray-700'
    },
    violet: {
        gradient: 'from-violet-600 to-violet-500',
        glow: 'shadow-violet-500/30',
        cardBg: 'bg-gradient-to-br from-violet-50 to-violet-100/50',
        text: 'text-violet-600',
        iconBg: 'bg-violet-600',
        buttonBg: 'bg-violet-600',
        buttonHover: 'hover:bg-violet-700',
        badge: 'bg-violet-100 text-violet-700'
    },
    indigo: {
        gradient: 'from-indigo-600 to-indigo-500',
        glow: 'shadow-indigo-500/30',
        cardBg: 'bg-gradient-to-br from-indigo-50 to-indigo-100/50',
        text: 'text-indigo-600',
        iconBg: 'bg-indigo-600',
        buttonBg: 'bg-indigo-600',
        buttonHover: 'hover:bg-indigo-700',
        badge: 'bg-indigo-100 text-indigo-700'
    },
    emerald: {
        gradient: 'from-emerald-600 to-emerald-500',
        glow: 'shadow-emerald-500/30',
        cardBg: 'bg-gradient-to-br from-emerald-50 to-emerald-100/50',
        text: 'text-emerald-600',
        iconBg: 'bg-emerald-600',
        buttonBg: 'bg-emerald-600',
        buttonHover: 'hover:bg-emerald-700',
        badge: 'bg-emerald-100 text-emerald-700'
    }
};
</script>

<template>
    <Head title="Pilih Sistem" />

    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100 relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern opacity-[0.02]"></div>

        <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-gradient-to-br from-blue-400/20 to-purple-400/20 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-gradient-to-tr from-cyan-400/20 to-blue-400/20 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>

        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12 relative z-10">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm rounded-full shadow-sm border border-gray-200 mb-6">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm font-medium text-gray-700">All Systems Operational</span>
                    </div>

                    <h1 class="text-5xl md:text-6xl font-bold mb-4 bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 bg-clip-text text-transparent">
                        Welcome
                    </h1>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        4W Departement - PT Dharma Polimetal Tbk
                    </p>
                </div>

                <div class="flex flex-wrap justify-center gap-6 mb-12">
                    <Link
                        v-for="system in systems"
                        :key="system.id"
                        :href="system.route"
                        :class="[
                            'group relative transition-all duration-500 w-full sm:w-[calc(50%-12px)] md:w-[calc(33.333%-16px)] lg:w-[calc(25%-18px)] min-w-[280px] max-w-[340px]',
                            hoveredCard === system.id ? 'scale-[1.02] z-10' : 'scale-100'
                        ]"
                        @mouseenter="hoveredCard = system.id"
                        @mouseleave="hoveredCard = null"
                    >
                        <div :class="[
                            'absolute -inset-px bg-gradient-to-r rounded-2xl opacity-0 group-hover:opacity-100 transition-all duration-500 blur-sm',
                            colorSchemes[system.color].gradient
                        ]"></div>

                        <div class="relative bg-white rounded-2xl overflow-hidden transition-all duration-500 shadow-lg hover:shadow-2xl border border-gray-200 h-full flex flex-col">
                            <div :class="[
                                'p-6 transition-all duration-500',
                                colorSchemes[system.color].cardBg
                            ]">
                                <div class="flex items-start justify-between mb-4">
                                    <div :class="[
                                        'w-14 h-14 rounded-xl bg-gradient-to-br flex items-center justify-center shadow-lg transition-all duration-500 group-hover:scale-110 group-hover:rotate-3',
                                        colorSchemes[system.color].gradient,
                                        hoveredCard === system.id ? colorSchemes[system.color].glow : ''
                                    ]">
                                        <component :is="iconComponents[system.icon]" class="w-7 h-7 text-white" />
                                    </div>

                                    <div :class="[
                                        'px-3 py-1 rounded-full text-xs font-semibold transition-all duration-300',
                                        colorSchemes[system.color].badge
                                    ]">
                                        Active
                                    </div>
                                </div>

                                <h3 class="text-xl font-bold text-gray-900 mb-2">
                                    {{ system.title }}
                                </h3>
                                <p class="text-sm text-gray-600 leading-relaxed line-clamp-2">
                                    {{ system.description }}
                                </p>
                            </div>

                            <div class="p-6 pt-4 flex-1 flex flex-col bg-white">
                                <div class="space-y-2.5 mb-5 flex-1">
                                    <div
                                        v-for="(feature, idx) in system.features.slice(0, 3)"
                                        :key="idx"
                                        :class="[
                                            'flex items-center gap-2.5 text-sm text-gray-700 transition-all duration-300 group/item',
                                            hoveredCard === system.id ? 'translate-x-1' : ''
                                        ]"
                                        :style="{ transitionDelay: `${idx * 50}ms` }"
                                    >
                                        <div :class="[
                                            'w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 transition-all duration-300',
                                            colorSchemes[system.color].badge
                                        ]">
                                            <CheckCircle2 :class="[
                                                'w-3.5 h-3.5',
                                                colorSchemes[system.color].text
                                            ]" />
                                        </div>
                                        <span class="leading-tight">{{ feature }}</span>
                                    </div>

                                    <div v-if="system.features.length > 3" :class="[
                                        'flex items-center gap-2.5 text-sm pl-7.5 transition-all duration-300',
                                        colorSchemes[system.color].text
                                    ]">
                                        <span class="font-medium">+{{ system.features.length - 3 }} more</span>
                                    </div>
                                </div>

                                <div :class="[
                                    'group/btn relative overflow-hidden w-full py-3.5 px-5 rounded-xl text-white font-semibold flex items-center justify-center gap-2 transition-all duration-300 shadow-md hover:shadow-xl mt-auto',
                                    colorSchemes[system.color].buttonBg,
                                    colorSchemes[system.color].buttonHover
                                ]">
                                    <div class="absolute inset-0 bg-white/20 translate-y-full group-hover/btn:translate-y-0 transition-transform duration-300"></div>
                                    <span class="relative z-10">Masuk</span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                <div class="text-center">
                    <div class="inline-flex flex-col items-center gap-3 px-8 py-4 bg-white/60 backdrop-blur-sm rounded-2xl border border-gray-200 shadow-sm">
                        <p class="text-sm text-gray-600 font-medium">
                            4 Wheel Department System
                        </p>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span>©</span>
                            <span>2025 All Rights Reserved</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <AiChat/>
</template>

<style scoped>
.bg-grid-pattern {
    background-image:
        linear-gradient(to right, rgb(0 0 0 / 0.05) 1px, transparent 1px),
        linear-gradient(to bottom, rgb(0 0 0 / 0.05) 1px, transparent 1px);
    background-size: 24px 24px;
}
</style>
