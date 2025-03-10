<script setup>
import { computed } from 'vue';
import { LayoutGrid, MessageSquare, X } from 'lucide-vue-next';
import ElementsPanel from './ElementsPanel.vue';
import ChatPanel from './ChatPanel.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    activeTab: {
        type: String,
        required: true
    },
    // Add these new props
    elements: {
        type: Array,
        default: () => []
    },
    product: {
        type: Object,
        default: () => ({})
    },
    designId: {
        type: [Number, String],
        default: null
    }
});

const emit = defineEmits(['update:isOpen', 'update:activeTab', 'update:elements']);

const tabs = [
    { id: 'elements', icon: LayoutGrid, label: 'Elements' },
    { id: 'chat', icon: MessageSquare, label: 'Chat' }
];

const sidebarClasses = computed(() => {
    return {
        'translate-x-0': props.isOpen,
        '-translate-x-full': !props.isOpen,
        'sm:translate-x-0': true
    };
});

// Check if we have the necessary data for chat
const chatConfigured = computed(() => {
    return props.designId && props.product && Object.keys(props.product).length > 0;
});
</script>

<template>
    <div 
        class="fixed sm:relative h-full bg-white border-r border-gray-200 transform transition-transform duration-200 ease-in-out z-30"
        :class="sidebarClasses"
    >
        <!-- Header with tabs -->
        <div class="h-14 border-b border-gray-200 flex items-center px-4">
            <div class="flex space-x-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="emit('update:activeTab', tab.id)"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
                    :class="{
                        'bg-gray-100 text-gray-900': activeTab === tab.id,
                        'text-gray-500 hover:text-gray-700 hover:bg-gray-50': activeTab !== tab.id
                    }"
                >
                    <component :is="tab.icon" class="size-5 inline-block mr-2" />
                    {{ tab.label }}
                </button>
            </div>
            
            <!-- Mobile close button -->
            <button 
                @click="emit('update:isOpen', false)"
                class="sm:hidden ml-auto p-2 text-gray-500 hover:text-gray-700"
            >
                <X class="size-5" />
            </button>
        </div>
        
        <!-- Panel content -->
        <div class="h-[calc(100%-3.5rem)] overflow-y-auto">
            <ElementsPanel v-if="activeTab === 'elements'" />
            <template v-else-if="activeTab === 'chat'">
                <!-- Show message if chat can't be configured -->
                <div v-if="!chatConfigured" class="p-4 text-center text-gray-500">
                    <p>Please save your design first to use the chat feature.</p>
                </div>
                <ChatPanel 
                    v-else
                    :elements="elements"
                    :product="product"
                    :design-id="designId"
                    @update:elements="$emit('update:elements', $event)"
                />
            </template>
        </div>
    </div>
</template>