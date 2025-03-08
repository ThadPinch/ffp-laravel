<script setup>
import { ref, onMounted, watch } from 'vue';
import { X, ChevronLeft, Clock, RefreshCw } from 'lucide-vue-next';
import axios from 'axios';
import { format } from 'date-fns';

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true
    },
    designId: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['update:modelValue', 'restore']);

const versions = ref([]);
const isLoading = ref(true);
const error = ref(null);

// Fetch versions when component is mounted or designId changes
const fetchVersions = async () => {
    if (!props.designId) return;
    
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await axios.get(`/api/designs/${props.designId}/versions`);
        versions.value = response.data;
    } catch (err) {
        console.error('Error fetching versions:', err);
        error.value = 'Failed to load version history. Please try again.';
    } finally {
        isLoading.value = false;
    }
};

// Format date for display
const formatDate = (dateString) => {
    const date = new Date(dateString);
    return format(date, 'MMM d, yyyy h:mm a');
};

// Restore a specific version
const restoreVersion = (version) => {
    if (confirm('Are you sure you want to restore this version? Any unsaved changes will be lost.')) {
        emit('restore', version);
    }
};

// Watch for visibility changes
watch(() => props.modelValue, (newValue) => {
    if (newValue) {
        fetchVersions();
    }
});

onMounted(() => {
    if (props.modelValue) {
        fetchVersions();
        // log the id
        console.log('this is design id: ', props.designId);
    }
});
</script>

<template>
    <div 
        v-if="modelValue"
        class="fixed inset-0 z-50 flex"
    >
        <!-- Overlay -->
        <div 
            class="fixed inset-0 bg-black bg-opacity-30" 
            @click="$emit('update:modelValue', false)"
        ></div>
        
        <!-- Sidebar -->
        <div class="relative w-full max-w-md ml-auto bg-white h-full overflow-auto shadow-xl flex flex-col">
            <!-- Header -->
            <div class="p-4 border-b flex items-center justify-between">
                <div class="flex items-center">
                    <Clock class="mr-2 size-5" />
                    <h2 class="text-lg font-medium">Version History</h2>
                </div>
                <button 
                    @click="$emit('update:modelValue', false)"
                    class="p-1 rounded-full hover:bg-gray-100"
                >
                    <X class="size-5" />
                </button>
            </div>
            
            <!-- Content -->
            <div class="flex-1 overflow-auto">
                <div v-if="isLoading" class="p-8 text-center text-gray-500">
                    <div class="animate-spin size-8 border-4 border-gray-300 border-t-blue-500 rounded-full mx-auto mb-4"></div>
                    <p>Loading version history...</p>
                </div>
                
                <div v-else-if="error" class="p-8 text-center text-red-500">
                    <p>{{ error }}</p>
                    <button 
                        @click="fetchVersions"
                        class="mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 inline-flex items-center"
                    >
                        <RefreshCw class="mr-2 size-4" />
                        Try Again
                    </button>
                </div>
                
                <div v-else-if="versions.length === 0" class="p-8 text-center text-gray-500">
                    <p>No version history available.</p>
                </div>
                
                <div v-else class="divide-y">
                    <div 
                        v-for="version in versions" 
                        :key="version.id"
                        class="p-4 hover:bg-gray-50 transition-colors"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-medium">{{ formatDate(version.created_at) }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ version.comment || 'No description' }}
                                </p>
                            </div>
                            <button 
                                @click="restoreVersion(version)"
                                class="px-3 py-1 text-sm border rounded text-gray-700 hover:bg-gray-100"
                            >
                                Restore
                            </button>
                        </div>
                        
                        <!-- Thumbnail preview if available -->
                        <div v-if="version.thumbnail" class="mt-2">
                            <img 
                                :src="`/storage/${version.thumbnail}`" 
                                :alt="`Version ${version.id} thumbnail`"
                                class="w-full h-32 object-contain border rounded"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>