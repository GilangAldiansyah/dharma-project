<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
    size?: 'sm' | 'md' | 'lg' | 'xl';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md'
});

const sizeClasses = computed(() => {
    const sizes = {
        sm: 'w-6 h-6',
        md: 'w-8 h-8',
        lg: 'w-12 h-12',
        xl: 'w-16 h-16'
    };
    return sizes[props.size];
});
</script>

<template>
    <img
        src="/images/dharma-polimetal.png"
        alt="Dharma Logo"
        :class="[sizeClasses, className]"
        v-bind="$attrs"
    />
</template>
