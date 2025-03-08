<script setup>

import { Type, Image, Square, Circle, Triangle, Minus } from 'lucide-vue-next';

const elements = [
    { id: 'text', icon: Type, label: 'Text', description: 'Add text to your design' },
    { id: 'image', icon: Image, label: 'Image', description: 'Upload or choose an image' },
    { id: 'square', icon: Square, label: 'Square', description: 'Add a square or rectangle' },
    { id: 'circle', icon: Circle, label: 'Circle', description: 'Add a circle or oval' },
    { id: 'triangle', icon: Triangle, label: 'Triangle', description: 'Add a triangle' },
    { id: 'line', icon: Minus, label: 'Line', description: 'Add a straight line' }
];
const handleDragStart = (e, elementType) => {
    e.dataTransfer.setData('text/plain', elementType);
};
</script>

<template>
    <div class="p-4">
        <div class="space-y-3">
            <div
                v-for="element in elements"
                :key="element.id"
                draggable="true"
                @dragstart="handleDragStart($event, element.id)"
                class="flex items-center p-3 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 cursor-move transition-colors"
            >
                <component :is="element.icon" class="size-5 text-gray-500 mr-3" />
                <div>
                    <h3 class="text-sm font-medium text-gray-900">
                        {{ element.label }}
                    </h3>
                    <p class="text-xs text-gray-500">
                        {{ element.description }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>