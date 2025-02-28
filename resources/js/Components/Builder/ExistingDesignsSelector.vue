<script setup>
import { ref, onMounted, computed } from 'vue';
import { Dialog, DialogPanel } from '@headlessui/vue';
import { Plus, ArrowLeft, Loader } from 'lucide-vue-next';
import axios from 'axios';
import DesignActionModal from './DesignActionModal.vue'; // Import the new modal component

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true
    },
    selectedProduct: {
        type: Object,
        required: true
    }
});

// Add new emit events for editing original and creating copy
const emit = defineEmits(['update:modelValue', 'selectExisting', 'createNew', 'back', 'editOriginal', 'createCopy']);

const isLoading = ref(true);
const existingDesigns = ref([]);
const error = ref(null);
const showActionModal = ref(false);
const selectedDesign = ref(null);

// Fetch designs for the selected product
onMounted(async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/designs', {
            params: { product_id: props.selectedProduct.id }
        });
        // Get designs for the selected product
        existingDesigns.value = response.data.data;
    } catch (err) {
        console.error('Error fetching designs:', err);
        error.value = 'Failed to load your existing designs. Please try again.';
    } finally {
        isLoading.value = false;
    }
});

// Create a new design with the selected product
const createNewDesign = () => {
    emit('createNew', props.selectedProduct);
    emit('update:modelValue', false);
};

// Select an existing design to open action modal
const handleDesignClick = (design) => {
    selectedDesign.value = design;
    showActionModal.value = true;
};

// Handle edit original action
const handleEditOriginal = (design) => {
    console.log('Edit original design:', design.id, design.name);
    emit('editOriginal', design);
    emit('update:modelValue', false);
};

// Handle create copy action
const handleCreateCopy = (design) => {
    console.log('Create copy of design:', design.id, design.name);
    emit('createCopy', design);
    emit('update:modelValue', false);
};

// Handle back button click - emit 'back' event
const handleBackClick = () => {
    emit('back');
};

// Format date for display
const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
};

// Computed property to check if there are existing designs
const hasExistingDesigns = computed(() => existingDesigns.value.length > 0);
</script>

<template>
    <Dialog 
        :open="modelValue" 
        @close="$emit('update:modelValue', false)"
        class="relative z-50"
    >
        <div class="fixed inset-0 bg-black/30" aria-hidden="true" />
        <div class="fixed inset-0 flex w-screen items-center justify-center p-4">
            <DialogPanel class="w-full max-w-3xl rounded-lg bg-white max-h-[90vh] flex flex-col">
                <div class="px-4 py-5 sm:p-6 flex-1 overflow-auto">
                    <!-- Header with back button -->
                    <div class="flex items-center mb-6">
                        <button 
                            @click="handleBackClick()" 
                            class="mr-3 p-1 rounded-full hover:bg-gray-100"
                        >
                            <ArrowLeft class="size-5 text-gray-600" />
                        </button>
                        <h3 class="text-lg font-semibold">
                            {{ selectedProduct.name }} Designs
                        </h3>
                    </div>

                    <!-- Loading state -->
                    <div v-if="isLoading" class="flex justify-center items-center py-8">
                        <Loader class="size-8 text-blue-500 animate-spin" />
                        <span class="ml-3 text-gray-600">Loading your designs...</span>
                    </div>

                    <!-- Error state -->
                    <div v-else-if="error" class="bg-red-50 text-red-700 p-4 rounded-lg">
                        {{ error }}
                    </div>

                    <!-- Content: Existing designs or create new -->
                    <div v-else>
                        <!-- Create new design card (always shown first) -->
                        <div class="mb-6">
                            <button 
                                @click="createNewDesign" 
                                class="w-full border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 transition-colors text-center"
                            >
                                <div class="flex flex-col items-center justify-center py-8">
                                    <div class="bg-blue-100 rounded-full p-3 mb-3">
                                        <Plus class="size-8 text-blue-600" />
                                    </div>
                                    <h4 class="font-medium text-gray-900 mb-1">Create New Design</h4>
                                    <p class="text-sm text-gray-500">
                                        Start with a blank {{ selectedProduct.name }}
                                    </p>
                                </div>
                            </button>
                        </div>

                        <!-- Existing designs section -->
                        <div v-if="hasExistingDesigns">
                            <h4 class="font-medium text-gray-700 mb-3">Or continue with an existing design:</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div
                                    v-for="design in existingDesigns"
                                    :key="design.id"
                                    class="border rounded-lg overflow-hidden hover:border-blue-500 cursor-pointer transition-colors"
                                    @click="handleDesignClick(design)"
                                >
                                    <div class="aspect-square bg-gray-100 relative">
                                        <img
                                            v-if="design.thumbnail"
                                            :src="`/storage/${design.thumbnail}`"
                                            :alt="design.name"
                                            class="w-full h-full object-cover"
                                        />
                                        <div v-else class="w-full h-full flex items-center justify-center bg-gray-200">
                                            <span class="text-gray-400 text-sm">No preview</span>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <h5 class="font-medium text-gray-900 truncate">{{ design.name }}</h5>
                                        <p class="text-xs text-gray-500">
                                            Last edited: {{ formatDate(design.updated_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- No existing designs message -->
                        <div v-else class="mt-4 text-gray-600 text-center py-4">
                            <p>You don't have any existing designs for this product.</p>
                        </div>
                    </div>
                </div>
            </DialogPanel>
        </div>
    </Dialog>

    <!-- Design Action Modal -->
    <DesignActionModal
        v-if="selectedDesign"
        v-model="showActionModal"
        :design="selectedDesign"
        @edit-original="handleEditOriginal"
        @create-copy="handleCreateCopy"
    />
</template>