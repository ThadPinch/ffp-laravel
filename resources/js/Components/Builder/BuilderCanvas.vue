<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Type, Image, Square } from 'lucide-vue-next';

const props = defineProps({
    product: {
        type: Object,
        required: true
    }
});

const elements = ref([]);
const selectedElement = ref(null);
const isDragging = ref(false);
const dragOffset = ref({ x: 0, y: 0 });

// Compute canvas settings based on product dimensions
const canvasSettings = computed(() => {
    const dpi = 300;
    const width = props.product.finished_width * dpi;
    const height = props.product.finished_length * dpi;
    
    // Calculate display scale to fit in viewport while maintaining aspect ratio
    const maxDisplayWidth = window.innerWidth * 0.7;
    const maxDisplayHeight = window.innerHeight * 0.8;
    
    const widthScale = maxDisplayWidth / width;
    const heightScale = maxDisplayHeight / height;
    const displayScale = Math.min(widthScale, heightScale, 1);

    return {
        width,
        height,
        dpi,
        displayScale
    };
});

// Handle element dragging from sidebar
const handleDragStart = (e, elementType) => {
    e.dataTransfer.setData('text/plain', elementType);
};

const handleDragOver = (e) => {
    e.preventDefault();
};

const handleDrop = (e) => {
    e.preventDefault();
    const elementType = e.dataTransfer.getData('text/plain');
    const rect = e.currentTarget.getBoundingClientRect();
    const x = (e.clientX - rect.left) / canvasSettings.value.displayScale;
    const y = (e.clientY - rect.top) / canvasSettings.value.displayScale;

    const newElement = {
        id: Date.now(),
        type: elementType,
        x,
        y,
        width: elementType === 'text' ? 300 : 600,
        height: elementType === 'text' ? 100 : 600,
        content: elementType === 'text' ? 'Double click to edit' : null,
        rotation: 0,
        fontSize: 48,
        fontFamily: 'Arial',
        color: '#000000'
    };

    elements.value.push(newElement);
};

const startDragging = (e, element) => {
    if (e.target.classList.contains('handle')) return;
    
    selectedElement.value = element;
    isDragging.value = true;
    
    // Calculate the offset between the mouse position and the element's position
    // We need to account for the canvas scale to get the correct offset
    dragOffset.value = {
        x: (e.clientX - e.currentTarget.getBoundingClientRect().left) / canvasSettings.value.displayScale - element.x,
        y: (e.clientY - e.currentTarget.getBoundingClientRect().top) / canvasSettings.value.displayScale - element.y
    };
};

const handleDrag = (e) => {
    if (!isDragging.value || !selectedElement.value) return;

    // Get the canvas rectangle to calculate position relative to it
    const canvasRect = e.currentTarget.parentElement.getBoundingClientRect();
    
    // Calculate the new position, accounting for scale and drag offset
    const newX = (e.clientX - canvasRect.left) / canvasSettings.value.displayScale - dragOffset.value.x;
    const newY = (e.clientY - canvasRect.top) / canvasSettings.value.displayScale - dragOffset.value.y;

    elements.value = elements.value.map(el => {
        if (el.id === selectedElement.value.id) {
            return { 
                ...el, 
                x: newX,
                y: newY
            };
        }
        return el;
    });
};

const stopDragging = () => {
    isDragging.value = false;
};

const handleElementClick = (element, e) => {
    e.stopPropagation();
    selectedElement.value = element;
};

const handleCanvasClick = () => {
    selectedElement.value = null;
};

const handleTextEdit = (element, e) => {
    if (element.type !== 'text') return;
    const newContent = prompt('Edit text:', element.content);
    if (newContent !== null) {
        elements.value = elements.value.map(el => {
            if (el.id === element.id) {
                return { ...el, content: newContent };
            }
            return el;
        });
    }
};

const handleKeyDown = (e) => {
    if ((e.key === 'Delete' || e.key === 'Backspace') && selectedElement.value) {
        elements.value = elements.value.filter(el => el.id !== selectedElement.value.id);
        selectedElement.value = null;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <div 
        class="relative w-full h-full bg-gray-100 overflow-auto"
        @click="handleCanvasClick"
    >
        <!-- Product Information -->
        <div class="absolute top-4 left-4 bg-white rounded-lg shadow p-2 text-sm">
            {{ product.name }} - {{ product.finished_width }}" × {{ product.finished_length }}"
        </div>

        <!-- Canvas Area -->
        <div 
            class="relative bg-white shadow-lg mx-auto my-8"
            :style="{
                width: canvasSettings.width * canvasSettings.displayScale + 'px',
                height: canvasSettings.height * canvasSettings.displayScale + 'px'
            }"
            @dragover="handleDragOver"
            @drop="handleDrop"
        >
            <!-- Safe Area Guide -->
            <div 
                class="absolute border-2 border-gray-200 border-dashed pointer-events-none"
                :style="{
                    top: '0.125in',
                    left: '0.125in',
                    right: '0.125in',
                    bottom: '0.125in',
                    transform: `scale(${canvasSettings.displayScale})`
                }"
            />

            <!-- Design Elements -->
            <div 
                v-for="element in elements"
                :key="element.id"
                class="absolute cursor-move"
                :class="{ 'ring-2 ring-blue-500': selectedElement?.id === element.id }"
                :style="{
                    left: `${element.x}px`,
                    top: `${element.y}px`,
                    width: `${element.width}px`,
                    height: `${element.height}px`,
                    transform: `scale(${canvasSettings.displayScale}) rotate(${element.rotation}deg)`,
                    transformOrigin: 'top left'
                }"
                @mousedown="startDragging($event, element)"
                @click="handleElementClick(element, $event)"
                @dblclick="handleTextEdit(element, $event)"
            >
                <!-- Text Element -->
                <div 
                    v-if="element.type === 'text'"
                    class="w-full h-full"
                >
                    <p 
                        :style="{
                            fontSize: `${element.fontSize}px`,
                            fontFamily: element.fontFamily,
                            color: element.color
                        }"
                    >
                        {{ element.content }}
                    </p>
                </div>

                <!-- Image Element Placeholder -->
                <div 
                    v-else-if="element.type === 'image'"
                    class="w-full h-full bg-gray-200 flex items-center justify-center"
                >
                    <Image class="size-12 text-gray-400" />
                </div>

                <!-- Shape Element -->
                <div 
                    v-else-if="element.type === 'shape'"
                    class="w-full h-full border-2 border-gray-400"
                />
            </div>
        </div>

        <!-- Mouse move handler for dragging -->
        <div 
            v-if="isDragging"
            class="fixed inset-0 z-50 cursor-move"
            @mousemove="handleDrag"
            @mouseup="stopDragging"
        />
    </div>
</template>