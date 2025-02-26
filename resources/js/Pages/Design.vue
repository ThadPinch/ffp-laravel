<script setup>
import { ref, onMounted } from 'vue';
import BuilderLayout from '@/Layouts/BuilderLayout.vue';
import BuilderSidebar from '@/Components/Builder/BuilderSidebar.vue';
import BuilderCanvas from '@/Components/Builder/BuilderCanvas.vue';
import ProductSelector from '@/Components/Builder/ProductSelector.vue';
import { MenuIcon } from 'lucide-vue-next';

const props = defineProps({
    products: {
        type: Array,
        required: true
    }
});

console.log(props.products);

const isSidebarOpen = ref(false);
const activeTab = ref('elements');
const showProductSelector = ref(true);
const selectedProduct = ref(null);

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
</script>

<template>
    <BuilderLayout>
        <!-- Product Selector Modal -->
        <ProductSelector
            v-model="showProductSelector"
            :products="products"
            @select="handleProductSelect"
        />

        <template v-if="selectedProduct">
            <!-- Mobile Sidebar Toggle -->
            <button 
                @click="toggleSidebar"
                class="fixed z-40 bottom-4 right-4 sm:hidden bg-white p-3 rounded-full shadow-lg border border-gray-200"
            >
                <MenuIcon class="size-6" />
            </button>

            <div class="flex h-[calc(100vh-4rem)]">
                <!-- Sidebar -->
                <BuilderSidebar
                    v-model:is-open="isSidebarOpen"
                    v-model:active-tab="activeTab"
                    class="w-full sm:w-80 flex-shrink-0"
                />

                <!-- Main Canvas Area -->
                <div class="flex-grow overflow-hidden bg-gray-50">
                    <BuilderCanvas 
                        :product="selectedProduct"
                    />
                </div>
            </div>
        </template>
    </BuilderLayout>
</template>