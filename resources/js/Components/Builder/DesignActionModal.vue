<!-- DesignActionModal.vue -->
<script setup>
import { Dialog, DialogPanel } from '@headlessui/vue';
import { ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true
    },
    design: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['update:modelValue', 'editOriginal', 'createCopy']);

const handleEditOriginal = () => {
    emit('editOriginal', props.design);
    emit('update:modelValue', false);
};

const handleCreateCopy = () => {
    emit('createCopy', props.design);
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
            <DialogPanel class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="px-6 py-6">
                    <h3 class="text-lg font-semibold mb-3 text-gray-900">
                        Open Design
                    </h3>
                    
                    <p class="text-gray-600 mb-5">
                        How would you like to work with "{{ design.name }}"?
                    </p>
                    
                    <div class="flex flex-col space-y-3">
                        <button 
                            @click="handleEditOriginal" 
                            class="w-full flex items-center justify-center py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors"
                        >
                            Edit Original
                        </button>
                        
                        <button 
                            @click="handleCreateCopy" 
                            class="w-full flex items-center justify-center py-3 px-4 bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium rounded-md transition-colors"
                        >
                            Create a Copy
                        </button>
                    </div>
                </div>
            </DialogPanel>
        </div>
    </Dialog>
</template>