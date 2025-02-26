<script setup>
import { ref, useForm, computed, onMounted, watch } from 'vue';
// import { useForm } from '@inertiajs/inertia-vue3';
import axios from 'axios';
import BuilderLayout from '@/Layouts/BuilderLayout.vue';
import BuilderSidebar from '@/Components/Builder/BuilderSidebar.vue';
import BuilderCanvas from '@/Components/Builder/BuilderCanvas.vue';
import ProductSelector from '@/Components/Builder/ProductSelector.vue';
import ChatPanel from '@/Components/Builder/ChatPanel.vue';
import VersionHistory from '@/Components/Builder/VersionHistory.vue';
import { Menu, Save, ChevronLeft } from 'lucide-vue-next';

const props = defineProps({
    products: {
        type: Array,
        required: true
    },
    selectedProductId: {
        type: Number,
        default: null
    },
    design: {
        type: Object,
        default: null
    }
});

const isEditing = computed(() => !!props.design);
const designName = ref(isEditing.value ? props.design.name : 'Untitled Design');
const isSidebarOpen = ref(false);
const activeTab = ref('elements');
const showProductSelector = ref(!props.selectedProductId && !isEditing.value);
const selectedProduct = ref(null);
const elements = ref([]);
const isSaving = ref(false);
const saveStatus = ref(''); // '', 'saving', 'saved', 'error'
const showVersions = ref(false);

// Handle product selection or load existing product
onMounted(() => {
    if (isEditing.value) {
        // Load existing design
        selectedProduct.value = props.products.find(p => p.id === props.design.product_id);
        elements.value = props.design.elements;
    } else if (props.selectedProductId) {
        // New design with pre-selected product
        selectedProduct.value = props.products.find(p => p.id === props.selectedProductId);
    }
});

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

const setActiveTab = (tab) => {
    activeTab.value = tab;
    if (!isSidebarOpen.value) {
        isSidebarOpen.value = true;
    }
};

const handleProductSelect = (product) => {
    selectedProduct.value = product;
    showProductSelector.value = false;
};

// Save design to server
const saveDesign = async (shouldNavigate = false) => {
    isSaving.value = true;
    saveStatus.value = 'saving';
    
    try {
        // Prepare design data
        const designData = {
            product_id: selectedProduct.value.id,
            elements: elements.value,
            name: designName.value
        };
        
        let response;
        
        if (isEditing.value) {
            // Update existing design
            response = await axios.put(`/api/designs/${props.design.id}`, designData);
        } else {
            // Create new design
            response = await axios.post('/api/designs', designData);
        }
        
        saveStatus.value = 'saved';
        
        // Navigate to dashboard if requested
        if (shouldNavigate) {
            window.location.href = '/dashboard';
        }
        
        // Reset status after a delay
        setTimeout(() => {
            if (saveStatus.value === 'saved') {
                saveStatus.value = '';
            }
        }, 3000);
        
        return response.data;
    } catch (error) {
        console.error('Error saving design:', error);
        saveStatus.value = 'error';
    } finally {
        isSaving.value = false;
    }
};

// Auto-save on elements change with debounce
let saveTimeout = null;
watch(elements, () => {
    if (saveTimeout) clearTimeout(saveTimeout);
    
    saveTimeout = setTimeout(() => {
        if (selectedProduct.value) {
            saveDesign();
        }
    }, 5000); // Auto-save after 5 seconds of inactivity
}, { deep: true });

// Handle version restore
const handleRestoreVersion = async (version) => {
    try {
        const response = await axios.post(`/api/designs/${props.design.id}/versions/${version.id}/restore`);
        elements.value = response.data.elements;
        saveStatus.value = 'saved';
        showVersions.value = false;
        
        setTimeout(() => {
            if (saveStatus.value === 'saved') {
                saveStatus.value = '';
            }
        }, 3000);
    } catch (error) {
        console.error('Error restoring version:', error);
        saveStatus.value = 'error';
    }
};
</script>

<template>
    <BuilderLayout>
        <!-- Product Selector Modal -->
        <ProductSelector
            v-model="showProductSelector"
            :products="products"
            @select="handleProductSelect"
        />
        
        <!-- Version History Sidebar -->
        <VersionHistory
            v-if="isEditing"
            v-model="showVersions"
            :design-id="design.id"
            @restore="handleRestoreVersion"
        />

        <template v-if="selectedProduct">
            <!-- Header with controls -->
            <div class="bg-white border-b border-gray-200 py-2 px-4 flex items-center justify-between">
                <div class="flex items-center">
                    <a href="/dashboard" class="mr-4 text-gray-500 hover:text-gray-700">
                        <ChevronLeft class="size-5" />
                    </a>
                    <input
                        v-model="designName"
                        type="text"
                        class="border-0 focus:ring-0 text-lg font-medium"
                        placeholder="Untitled Design"
                    />
                </div>
                
                <div class="flex items-center space-x-2">
                    <span v-if="saveStatus" class="text-sm mr-2">
                        <span v-if="saveStatus === 'saving'" class="text-gray-500">Saving...</span>
                        <span v-else-if="saveStatus === 'saved'" class="text-green-600">Saved</span>
                        <span v-else-if="saveStatus === 'error'" class="text-red-600">Error saving</span>
                    </span>
                    
                    <button 
                        v-if="isEditing"
                        @click="showVersions = !showVersions"
                        class="px-3 py-1 text-sm border rounded text-gray-700 hover:bg-gray-50"
                    >
                        Version History
                    </button>
                    
                    <button 
                        @click="saveDesign(true)"
                        class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center"
                        :disabled="isSaving"
                    >
                        <Save class="size-4 mr-1" />
                        <span>{{ isEditing ? 'Save' : 'Save & Exit' }}</span>
                    </button>
                </div>
            </div>

            <!-- Mobile Sidebar Toggle -->
            <button 
                @click="toggleSidebar"
                class="fixed z-40 bottom-4 right-4 sm:hidden bg-white p-3 rounded-full shadow-lg border border-gray-200"
            >
                <Menu class="size-6" />
            </button>

            <div class="flex h-[calc(100vh-4rem-44px)]">
                <!-- Sidebar -->
                <BuilderSidebar
                    v-model:is-open="isSidebarOpen"
                    v-model:active-tab="activeTab"
                    class="w-full sm:w-80 flex-shrink-0"
                >
                    <!-- Dynamic sidebar content based on active tab -->
                    <template v-if="activeTab === 'chat'">
                        <ChatPanel 
                            :elements="elements"
                            :product="selectedProduct"
                            @update:elements="elements = $event"
                        />
                    </template>
                </BuilderSidebar>

                <!-- Main Canvas Area -->
                <div class="flex-grow overflow-hidden bg-gray-50">
                    <BuilderCanvas 
                        :product="selectedProduct"
                        v-model="elements"
                    />
                </div>
            </div>
        </template>
    </BuilderLayout>
</template>