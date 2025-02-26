<script setup>
import { ref } from 'vue';
import { Dialog, DialogPanel } from '@headlessui/vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true
    },
    products: {
        type: Array,
        required: true
    }
});

const emit = defineEmits(['update:modelValue', 'select']);

const selectProduct = (product) => {
    emit('select', product);
    emit('update:modelValue', false);
};
</script>

<template>
    <Dialog 
        :open="modelValue" 
        @close="$emit('update:modelValue', false)"
        class="relative z-50"
    >
        <div class="fixed inset-0 bg-black/30" aria-hidden="true" />

        <div class="fixed inset-0 flex w-screen items-center justify-center p-4">
            <DialogPanel class="w-full max-w-3xl rounded-lg bg-white">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        Choose a Product to Design
                    </h3>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div
                            v-for="product in products"
                            :key="product.id"
                            class="border rounded-lg p-4 hover:border-blue-500 cursor-pointer transition-colors"
                            @click="selectProduct(product)"
                        >
                            <div class="aspect-square mb-2">
                                <img
                                    :src="`/product-images/${product.product_image}`"
                                    :alt="product.name"
                                    class="w-full h-full object-cover rounded"
                                />
                            </div>
                            <h4 class="font-medium text-gray-900">{{ product.name }}</h4>
                            <p class="text-sm text-gray-500">
                                {{ product.finished_width }}" × {{ product.finished_length }}"
                            </p>
                        </div>
                    </div>
                </div>
            </DialogPanel>
        </div>
    </Dialog>
</template>