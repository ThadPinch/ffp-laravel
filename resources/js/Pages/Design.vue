<!-- design.vue (updated) -->

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import BuilderLayout from '@/Layouts/BuilderLayout.vue';
import BuilderSidebar from '@/Components/Builder/BuilderSidebar.vue';
import BuilderCanvas from '@/Components/Builder/BuilderCanvas.vue';
import ProductSelector from '@/Components/Builder/ProductSelector.vue';
import ExistingDesignsSelector from '@/Components/Builder/ExistingDesignsSelector.vue';
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

// Using ref instead of computed to allow manual setting when loading existing design
const isEditing = ref(!!props.design);
const designName = ref(isEditing.value ? props.design.name : 'Untitled Design');
const isSidebarOpen = ref(false);
const activeTab = ref('elements');
const showProductSelector = ref(!props.selectedProductId && !isEditing.value);
const showExistingDesignsSelector = ref(false);
const selectedProduct = ref(null);
const elements = ref([]);
const isSaving = ref(false);
const saveStatus = ref(''); // '', 'saving', 'saved', 'error'
const showVersions = ref(false);
const designId = ref(isEditing.value ? props.design.id : null);

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
    
    // Show existing designs selector after product selection
    showExistingDesignsSelector.value = true;
};

const handleDesignSelectorBack = () => {
    // Hide the existing designs selector
    showExistingDesignsSelector.value = false;
    
    // Show the product selector again
    showProductSelector.value = true;
    
    // Reset selected product
    selectedProduct.value = null;
};

const handleCreateNewDesign = (product) => {
    // Start with a blank design for the selected product
    selectedProduct.value = product;
    elements.value = [];
    designId.value = null;
    designName.value = 'Untitled Design';
};

// Load and edit the original design
const handleEditOriginal = async (design) => {
    try {
        console.log('Loading original design with ID:', design.id);
        
        // Set basic info first
        designId.value = design.id;
        designName.value = design.name;
        isEditing.value = true;
        
        // Cancel any pending auto-save
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }
        
        // Fetch the complete design data to ensure we have everything we need
        const response = await axios.get(`/api/designs/${design.id}`);
        const fullDesign = response.data;
        
        console.log('Successfully fetched design data');
        
        // Set the selected product
        selectedProduct.value = props.products.find(p => p.id === fullDesign.product_id);
        
        // Set elements last to prevent unwanted auto-save triggers
        elements.value = fullDesign.elements || [];
        
        console.log('Original design loaded successfully:', designId.value);
    } catch (error) {
        console.error('Error loading design:', error);
        // Show error notification
        saveStatus.value = 'error';
        setTimeout(() => {
            saveStatus.value = '';
        }, 3000);
    }
};

// Create a copy of the design and edit the copy
const handleCreateCopy = async (design) => {
    try {
        console.log('Creating a copy of design ID:', design.id);
        
        // Fetch the complete design data to ensure we have everything we need
        const response = await axios.get(`/api/designs/${design.id}`);
        const fullDesign = response.data;
        
        // Set up a new design based on the existing one
        selectedProduct.value = props.products.find(p => p.id === fullDesign.product_id);
        elements.value = fullDesign.elements || [];
        designName.value = `${fullDesign.name} (Copy)`;
        designId.value = null; // This will force a new design to be created
        isEditing.value = false;
        
        // Auto-save the new copy soon after creating it
        setTimeout(() => {
            saveDesign();
        }, 1000);
        
        console.log('Design copy created');
    } catch (error) {
        console.error('Error creating copy of design:', error);
        saveStatus.value = 'error';
        setTimeout(() => {
            saveStatus.value = '';
        }, 3000);
    }
};

// Handle selecting existing design (deprecated - just for backwards compatibility)
const handleSelectExistingDesign = async (design) => {
    // This old method now redirects to edit the original by default
    // for backwards compatibility
    await handleEditOriginal(design);
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
        
        console.log('Saving design. Design ID:', designId.value);
        
        if (designId.value) {
            // Update existing design
            console.log('Updating existing design:', designId.value);
            response = await axios.put(`/api/designs/${designId.value}`, designData);
        } else {
            // Create new design
            console.log('Creating new design');
            response = await axios.post('/api/designs', designData);
            // Set the design ID after creation for new designs
            designId.value = response.data.id;
            isEditing.value = true; // Now we're editing an existing design
            console.log('Created design with ID:', designId.value);
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
        if (selectedProduct.value && elements.value.length > 0) {
            // Log the current state before saving
            console.log('Auto-saving design. Current state:', {
                designId: designId.value,
                designName: designName.value,
                elementsCount: elements.value.length
            });
            
            saveDesign().then(result => {
                // Ensure we have a design ID for chat functionality
                if (result && result.id) {
                    if (designId.value !== result.id) {
                        console.log('Design ID updated from', designId.value, 'to', result.id);
                        designId.value = result.id;
                    }
                }
            });
        }
    }, 5000); // Auto-save after 5 seconds of inactivity
}, { deep: true });

// Handle version restore
const handleRestoreVersion = async (version) => {
    try {
        // const response = await axios.post(`/api/designs/${props.design.id}/versions/${version.id}/restore`);
        const response = await axios.post(`/api/designs/${props.designId}/versions/${version.id}/restore`);
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
        
        <!-- Existing Designs Selector Modal -->
        <ExistingDesignsSelector
            v-if="selectedProduct && !isEditing"
            v-model="showExistingDesignsSelector"
            :selected-product="selectedProduct"
            @select-existing="handleSelectExistingDesign"
            @create-new="handleCreateNewDesign"
            @back="handleDesignSelectorBack"
            @edit-original="handleEditOriginal"
            @create-copy="handleCreateCopy"
        />
        
        <!-- Version History Sidebar -->
        <VersionHistory
            v-if="isEditing"
            v-model="showVersions"
            :design-id="designId"
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
                        <!-- <span>{{ isEditing ? 'Save' : 'Save & Exit' }}</span> -->
                         <span>Save & Exit</span>
                    </button>
                </div>
            </div>

            <!-- Mobile Sidebar Toggle -->
            <button 
                @click="toggleSidebar"
                class="fixed z-40 bottom-4 left-4 sm:hidden bg-white p-3 rounded-full shadow-lg border border-gray-200"
            >
                <Menu class="size-6" />
            </button>

            <div class="flex h-[calc(100vh-4rem-44px)]">
                <!-- Sidebar -->
                <BuilderSidebar
                    v-model:is-open="isSidebarOpen"
                    v-model:active-tab="activeTab"
                    :elements="elements"
                    :product="selectedProduct"
                    :design-id="designId"
                    @update:elements="elements = $event"
                    class="w-full sm:w-80 flex-shrink-0"
                >
                    <!-- You no longer need the conditional template here since we handle it in the sidebar -->
                </BuilderSidebar>

                <!-- Main Canvas Area -->
                <div class="flex-grow overflow-hidden bg-gray-50">
                    <BuilderCanvas 
                        :product="selectedProduct"
                        v-model="elements"
                        :design-id="designId"
                        @update:designId="designId = $event"
                    />
                </div>
            </div>
        </template>
    </BuilderLayout>
</template>