<template>
    <BuilderLayout>
        <!-- Header with controls -->
        <div class="bg-white border-b border-gray-200 py-2 px-4 flex items-center justify-between">
            <div class="flex items-center">
                <a href="/dashboard" class="mr-4 text-gray-500 hover:text-gray-700">
                    <ArrowLeft class="size-5" />
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
                    @click="saveDesign(true)"
                    class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center"
                    :disabled="isSaving"
                >
                    <Save class="size-4 mr-1" />
                    <span>Save & Exit</span>
                </button>
            </div>
        </div>

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
                
                <!-- Default is the Elements panel from your original code -->
                <template v-else>
                    <ElementsPanel />
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
    </BuilderLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import BuilderLayout from '@/Layouts/BuilderLayout.vue';
import BuilderSidebar from '@/Components/Builder/BuilderSidebar.vue';
import BuilderCanvas from '@/Components/Builder/BuilderCanvas.vue';
import ElementsPanel from '@/Components/Builder/ElementsPanel.vue';
import ChatPanel from '@/Components/Builder/ChatPanel.vue';
import { MenuIcon, Save, ArrowLeft } from 'lucide-vue-next';

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
const selectedProduct = ref(null);
const elements = ref([]);
const isSaving = ref(false);
const saveStatus = ref('');

// Initialize data
onMounted(() => {
    if (isEditing.value) {
        // Loading an existing design
        selectedProduct.value = props.products.find(p => p.id === props.design.product_id);
        elements.value = props.design.elements || [];
    } else if (props.selectedProductId) {
        // Creating a new design with pre-selected product
        selectedProduct.value = props.products.find(p => p.id === props.selectedProductId);
        elements.value = []; // Start with an empty canvas
    }
});

// Save design function
const saveDesign = async (shouldNavigate = false) => {
    isSaving.value = true;
    saveStatus.value = 'saving';
    
    try {
        const designData = {
            product_id: selectedProduct.value.id,
            elements: elements.value,
            name: designName.value
        };
        
        let response;
        
        if (isEditing.value) {
            response = await axios.put(`/api/designs/${props.design.id}`, designData);
        } else {
            response = await axios.post('/api/designs', designData);
        }
        
        saveStatus.value = 'saved';
        
        if (shouldNavigate) {
            window.location.href = '/dashboard';
        }
        
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

// Auto-save functionality
let saveTimeout = null;
watch(elements, () => {
    if (saveTimeout) clearTimeout(saveTimeout);
    
    saveTimeout = setTimeout(() => {
        if (selectedProduct.value) {
            saveDesign();
        }
    }, 5000); // Auto-save after 5 seconds of inactivity
}, { deep: true });
</script>