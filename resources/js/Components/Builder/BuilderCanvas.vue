<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Type, Image, Square, Layers, Save, RotateCw, RotateCcw } from 'lucide-vue-next';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
    product: {
        type: Object,
        required: true
    },
    modelValue: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue']);

// Create a local copy of elements from modelValue
const elements = ref([...props.modelValue]);

const selectedElement = ref(null);
const isDragging = ref(false);
const isResizing = ref(false);
const isRotating = ref(false);
const dragOffset = ref({ x: 0, y: 0 });
const resizeDirection = ref('');
const originalSize = ref({ width: 0, height: 0 });
const rotationStartAngle = ref(0);
const designHistory = ref([]);
const historyIndex = ref(-1);
const saveStatus = ref(''); // 'saving', 'saved', 'error'

// Watch for external changes to modelValue
watch(() => props.modelValue, (newVal) => {
    if (JSON.stringify(elements.value) !== JSON.stringify(newVal)) {
        elements.value = [...newVal];
    }
}, { deep: true });

// Emit changes to parent component when elements change
watch(elements, (newVal) => {
    emit('update:modelValue', [...newVal]);
}, { deep: true });

// Compute canvas settings based on product dimensions
const canvasSettings = computed(() => {
    const dpi = 72; // Using a more standard screen DPI
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

// Save current state to history
const saveToHistory = () => {
    // If we've gone back in history and then made a new change,
    // we need to remove the futures that won't happen anymore
    if (historyIndex.value < designHistory.value.length - 1) {
        designHistory.value = designHistory.value.slice(0, historyIndex.value + 1);
    }
    
    // Save current state
    designHistory.value.push(JSON.stringify(elements.value));
    historyIndex.value = designHistory.value.length - 1;
};

// Initialize history
onMounted(() => {
    saveToHistory(); // Save initial empty state
});

// Undo function
const undo = () => {
    if (historyIndex.value > 0) {
        historyIndex.value--;
        elements.value = JSON.parse(designHistory.value[historyIndex.value]);
    }
};

// Redo function
const redo = () => {
    if (historyIndex.value < designHistory.value.length - 1) {
        historyIndex.value++;
        elements.value = JSON.parse(designHistory.value[historyIndex.value]);
    }
};

// Auto-save functionality
const saveDesign = debounce(async () => {
    try {
        saveStatus.value = 'saving';
        
        // Capture current design state
        const designData = {
            product_id: props.product.id,
            elements: elements.value,
            // You could also capture a thumbnail by rendering to canvas and converting to base64
        };
        
        // Send to server
        await axios.post('/api/designs', designData);
        
        saveStatus.value = 'saved';
        setTimeout(() => {
            if (saveStatus.value === 'saved') {
                saveStatus.value = '';
            }
        }, 3000);
    } catch (error) {
        console.error('Error saving design:', error);
        saveStatus.value = 'error';
    }
}, 30000); // 30 seconds debounce

// Watch for changes to elements and save
watch(elements, () => {
    saveDesign();
}, { deep: true });

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
    
    // Calculate position in canvas coordinates
    const x = (e.clientX - rect.left) / canvasSettings.value.displayScale;
    const y = (e.clientY - rect.top) / canvasSettings.value.displayScale;

    const newElement = {
        id: Date.now(),
        type: elementType,
        x,
        y,
        width: elementType === 'text' ? 300 : 200,
        height: elementType === 'text' ? 100 : 200,
        content: elementType === 'text' ? 'Double click to edit' : null,
        rotation: 0,
        fontSize: 48,
        fontFamily: 'Arial',
        color: '#000000',
        zIndex: elements.value.length // Add z-index for layering
    };

    elements.value.push(newElement);
    selectedElement.value = newElement;
    saveToHistory();
};

const startDragging = (e, element) => {
    if (e.target.classList.contains('handle')) return;
    
    selectedElement.value = element;
    isDragging.value = true;
    
    // Get canvas rectangle for correct coordinate calculation
    const canvasRect = e.currentTarget.parentElement.getBoundingClientRect();
    
    // Calculate the offset using the element's current position and the mouse position
    // Both in canvas coordinates (not screen coordinates)
    dragOffset.value = {
        x: (e.clientX - canvasRect.left) / canvasSettings.value.displayScale - element.x,
        y: (e.clientY - canvasRect.top) / canvasSettings.value.displayScale - element.y
    };
};

const updateElementProperty = (property, value) => {
    if (!selectedElement.value) return;
    
    // Update the property in the elements array
    elements.value = elements.value.map(el => {
        if (el.id === selectedElement.value.id) {
            return { ...el, [property]: value };
        }
        return el;
    });
    
    // Update the selected element reference
    selectedElement.value = {
        ...selectedElement.value,
        [property]: value
    };
    
    // Save the change to history
    saveToHistory();
};

const startResizing = (e, element, direction) => {
    e.stopPropagation();
    selectedElement.value = element;
    isResizing.value = true;
    resizeDirection.value = direction;
    originalSize.value = {
        width: element.width,
        height: element.height,
        x: element.x,
        y: element.y
    };
};

const startRotating = (e, element) => {
    e.stopPropagation();
    selectedElement.value = element;
    isRotating.value = true;
    
    // Calculate the center of the element
    const centerX = element.x + element.width / 2;
    const centerY = element.y + element.height / 2;
    
    // Get canvas rectangle for correct coordinate calculation
    const canvasRect = e.currentTarget.parentElement.getBoundingClientRect();
    
    // Calculate the angle between the center and the mouse position
    const mouseX = (e.clientX - canvasRect.left) / canvasSettings.value.displayScale;
    const mouseY = (e.clientY - canvasRect.top) / canvasSettings.value.displayScale;
    
    rotationStartAngle.value = Math.atan2(mouseY - centerY, mouseX - centerX) * 180 / Math.PI - element.rotation;
};

const updateSelectedElementReference = () => {
    if (selectedElement.value) {
        // Find the updated element in the elements array
        const updated = elements.value.find(el => el.id === selectedElement.value.id);
        if (updated) {
            // Update the reference
            selectedElement.value = {...updated};
        }
    }
};

const handleDrag = (e) => {
    if (!isDragging.value && !isResizing.value && !isRotating.value) return;
    
    // Get the canvas rectangle to calculate position relative to it
    const canvasRect = e.currentTarget.parentElement.getBoundingClientRect();
    
    if (isDragging.value && selectedElement.value) {
        // Calculate mouse position relative to canvas
        const mouseX = e.clientX - canvasRect.left;
        const mouseY = e.clientY - canvasRect.top;
        
        // Convert to canvas coordinates with proper offset
        const newX = (mouseX / canvasSettings.value.displayScale) - dragOffset.value.x;
        const newY = (mouseY / canvasSettings.value.displayScale) - dragOffset.value.y;

        // Update the element position
        elements.value = elements.value.map(el => {
            if (el.id === selectedElement.value.id) {
                const updated = { 
                    ...el, 
                    x: newX,
                    y: newY
                };
                return updated;
            }
            return el;
        });
        
        // Update the selected element reference
        updateSelectedElementReference();
    } 
    else if (isResizing.value && selectedElement.value) {
        const mouseX = (e.clientX - canvasRect.left) / canvasSettings.value.displayScale;
        const mouseY = (e.clientY - canvasRect.top) / canvasSettings.value.displayScale;
        
        let newWidth = originalSize.value.width;
        let newHeight = originalSize.value.height;
        let newX = originalSize.value.x;
        let newY = originalSize.value.y;
        
        // Handle different resize directions
        switch (resizeDirection.value) {
            case 'n':
                newHeight = originalSize.value.height - (mouseY - originalSize.value.y);
                newY = mouseY;
                break;
            case 's':
                newHeight = mouseY - originalSize.value.y;
                break;
            case 'e':
                newWidth = mouseX - originalSize.value.x;
                break;
            case 'w':
                newWidth = originalSize.value.width - (mouseX - originalSize.value.x);
                newX = mouseX;
                break;
            case 'ne':
                newWidth = mouseX - originalSize.value.x;
                newHeight = originalSize.value.height - (mouseY - originalSize.value.y);
                newY = mouseY;
                break;
            case 'nw':
                newWidth = originalSize.value.width - (mouseX - originalSize.value.x);
                newHeight = originalSize.value.height - (mouseY - originalSize.value.y);
                newX = mouseX;
                newY = mouseY;
                break;
            case 'se':
                newWidth = mouseX - originalSize.value.x;
                newHeight = mouseY - originalSize.value.y;
                break;
            case 'sw':
                newWidth = originalSize.value.width - (mouseX - originalSize.value.x);
                newHeight = mouseY - originalSize.value.y;
                newX = mouseX;
                break;
        }
        
        // Ensure minimum size
        newWidth = Math.max(50, newWidth);
        newHeight = Math.max(50, newHeight);
        
        elements.value = elements.value.map(el => {
            if (el.id === selectedElement.value.id) {
                return { 
                    ...el, 
                    width: newWidth,
                    height: newHeight,
                    x: newX,
                    y: newY
                };
            }
            return el;
        });
    }
    else if (isRotating.value && selectedElement.value) {
        // Calculate the center of the element
        const element = selectedElement.value;
        const centerX = element.x + element.width / 2;
        const centerY = element.y + element.height / 2;
        
        // Calculate the current angle between the center and the mouse position
        const mouseX = (e.clientX - canvasRect.left) / canvasSettings.value.displayScale;
        const mouseY = (e.clientY - canvasRect.top) / canvasSettings.value.displayScale;
        
        const currentAngle = Math.atan2(mouseY - centerY, mouseX - centerX) * 180 / Math.PI;
        const newRotation = (currentAngle - rotationStartAngle.value) % 360;
        
        elements.value = elements.value.map(el => {
            if (el.id === selectedElement.value.id) {
                return { 
                    ...el, 
                    rotation: newRotation
                };
            }
            return el;
        });
    }
};

const stopTransformation = () => {
    if (isDragging.value || isResizing.value || isRotating.value) {
        saveToHistory();
    }
    
    isDragging.value = false;
    isResizing.value = false;
    isRotating.value = false;
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
        saveToHistory();
    }
};

const changeElementProperty = (propertyName, value) => {
    if (!selectedElement.value) return;
    
    elements.value = elements.value.map(el => {
        if (el.id === selectedElement.value.id) {
            return { 
                ...el, 
                [propertyName]: value
            };
        }
        return el;
    });
    
    // Update the selected element reference
    selectedElement.value = elements.value.find(el => el.id === selectedElement.value.id);
    saveToHistory();
};

const moveLayer = (direction) => {
    if (!selectedElement.value) return;
    
    const index = elements.value.findIndex(el => el.id === selectedElement.value.id);
    
    if (direction === 'up' && index < elements.value.length - 1) {
        // Move element one layer up
        const temp = elements.value[index].zIndex;
        elements.value[index].zIndex = elements.value[index + 1].zIndex;
        elements.value[index + 1].zIndex = temp;
        
        // Reorder array by zIndex
        elements.value.sort((a, b) => a.zIndex - b.zIndex);
    } 
    else if (direction === 'down' && index > 0) {
        // Move element one layer down
        const temp = elements.value[index].zIndex;
        elements.value[index].zIndex = elements.value[index - 1].zIndex;
        elements.value[index - 1].zIndex = temp;
        
        // Reorder array by zIndex
        elements.value.sort((a, b) => a.zIndex - b.zIndex);
    }
    else if (direction === 'top') {
        // Move element to top layer
        elements.value.forEach((el, i) => {
            if (i === index) {
                el.zIndex = elements.value.length - 1;
            } else if (el.zIndex > elements.value[index].zIndex) {
                el.zIndex--;
            }
        });
        
        // Reorder array by zIndex
        elements.value.sort((a, b) => a.zIndex - b.zIndex);
    }
    else if (direction === 'bottom') {
        // Move element to bottom layer
        elements.value.forEach((el, i) => {
            if (i === index) {
                el.zIndex = 0;
            } else if (el.zIndex < elements.value[index].zIndex) {
                el.zIndex++;
            }
        });
        
        // Reorder array by zIndex
        elements.value.sort((a, b) => a.zIndex - b.zIndex);
    }
    
    saveToHistory();
};

const handleKeyDown = (e) => {
    if ((e.key === 'Delete' || e.key === 'Backspace') && selectedElement.value) {
        elements.value = elements.value.filter(el => el.id !== selectedElement.value.id);
        selectedElement.value = null;
        saveToHistory();
    }
    else if (e.key === 'z' && (e.ctrlKey || e.metaKey)) {
        if (e.shiftKey) {
            redo();
        } else {
            undo();
        }
    }
    else if (e.key === 'y' && (e.ctrlKey || e.metaKey)) {
        redo();
    }
    else if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        saveDesign.flush(); // Force immediate save
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    saveDesign.flush(); // Ensure pending changes are saved
});
</script>

<template>
    <div 
        class="relative w-full h-full bg-gray-100 overflow-auto"
        @click="handleCanvasClick"
    >
        <!-- Toolbar -->
        <div class="absolute top-4 right-4 bg-white rounded-lg shadow p-2 flex space-x-2">
            <button 
                @click="undo" 
                :disabled="historyIndex <= 0"
                class="p-2 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" 
                title="Undo"
            >
                <RotateCcw class="size-5" />
            </button>
            <button 
                @click="redo" 
                :disabled="historyIndex >= designHistory.length - 1"
                class="p-2 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" 
                title="Redo"
            >
                <RotateCw class="size-5" />
            </button>
            <button 
                @click="saveDesign.flush" 
                class="p-2 rounded hover:bg-gray-100" 
                title="Save"
            >
                <Save class="size-5" />
            </button>
            <div v-if="saveStatus" class="text-sm flex items-center ml-2">
                <span v-if="saveStatus === 'saving'">Saving...</span>
                <span v-else-if="saveStatus === 'saved'" class="text-green-600">Saved</span>
                <span v-else-if="saveStatus === 'error'" class="text-red-600">Error saving</span>
            </div>
        </div>

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
                    bottom: '0.125in'
                }"
            />

            <!-- Design Elements -->
            <div 
                v-for="element in elements"
                :key="element.id"
                class="absolute cursor-move"
                :class="{ 'ring-2 ring-blue-500 z-50': selectedElement?.id === element.id }"
                :style="{
                    left: `${element.x * canvasSettings.displayScale}px`,
                    top: `${element.y * canvasSettings.displayScale}px`,
                    width: `${element.width * canvasSettings.displayScale}px`,
                    height: `${element.height * canvasSettings.displayScale}px`,
                    transform: `rotate(${element.rotation}deg)`,
                    transformOrigin: 'center center',
                    zIndex: element.zIndex
                }"
                @mousedown="startDragging($event, element)"
                @click.stop="handleElementClick(element, $event)"
                @dblclick.stop="handleTextEdit(element, $event)"
            >
                <!-- Text Element -->
                <div 
                    v-if="element.type === 'text'"
                    class="w-full h-full overflow-hidden"
                >
                    <p 
                        :style="{
                            fontSize: `${element.fontSize * canvasSettings.displayScale}px`,
                            fontFamily: element.fontFamily,
                            color: element.color,
                            lineHeight: '1.2',
                            margin: '0',
                            padding: '0'
                        }"
                    >
                        {{ element.content }}
                    </p>
                </div>

                <!-- Image Element -->
                <div 
                    v-else-if="element.type === 'image'"
                    class="w-full h-full bg-gray-200 flex items-center justify-center overflow-hidden"
                >
                    <img v-if="element.src" :src="element.src" class="w-full h-full object-contain" />
                    <Image v-else class="size-12 text-gray-400" />
                </div>

                <!-- Shape Element -->
                <div 
                    v-else-if="element.type === 'shape'"
                    class="w-full h-full"
                    :style="{
                        backgroundColor: element.color || '#FFFFFF',
                        border: '2px solid ' + (element.borderColor || '#000000'),
                        borderRadius: element.borderRadius ? `${element.borderRadius * canvasSettings.displayScale}px` : '0'
                    }"
                />

                <!-- Resize Handles (only visible when element is selected) -->
                <template v-if="selectedElement?.id === element.id">
                    <!-- Corners -->
                    <div class="handle absolute top-0 left-0 w-3 h-3 bg-white border border-blue-500 z-10 cursor-nwse-resize -translate-x-1/2 -translate-y-1/2" @mousedown.stop="startResizing($event, element, 'nw')"></div>
                    <div class="handle absolute top-0 right-0 w-3 h-3 bg-white border border-blue-500 z-10 cursor-nesw-resize translate-x-1/2 -translate-y-1/2" @mousedown.stop="startResizing($event, element, 'ne')"></div>
                    <div class="handle absolute bottom-0 left-0 w-3 h-3 bg-white border border-blue-500 z-10 cursor-nesw-resize -translate-x-1/2 translate-y-1/2" @mousedown.stop="startResizing($event, element, 'sw')"></div>
                    <div class="handle absolute bottom-0 right-0 w-3 h-3 bg-white border border-blue-500 z-10 cursor-nwse-resize translate-x-1/2 translate-y-1/2" @mousedown.stop="startResizing($event, element, 'se')"></div>
                    
                    <!-- Edges -->
                    <div class="handle absolute top-0 left-1/2 w-3 h-3 bg-white border border-blue-500 z-10 cursor-ns-resize -translate-x-1/2 -translate-y-1/2" @mousedown.stop="startResizing($event, element, 'n')"></div>
                    <div class="handle absolute bottom-0 left-1/2 w-3 h-3 bg-white border border-blue-500 z-10 cursor-ns-resize -translate-x-1/2 translate-y-1/2" @mousedown.stop="startResizing($event, element, 's')"></div>
                    <div class="handle absolute left-0 top-1/2 w-3 h-3 bg-white border border-blue-500 z-10 cursor-ew-resize -translate-x-1/2 -translate-y-1/2" @mousedown.stop="startResizing($event, element, 'w')"></div>
                    <div class="handle absolute right-0 top-1/2 w-3 h-3 bg-white border border-blue-500 z-10 cursor-ew-resize translate-x-1/2 -translate-y-1/2" @mousedown.stop="startResizing($event, element, 'e')"></div>
                    
                    <!-- Rotation Handle -->
                    <div class="handle absolute top-0 left-1/2 w-3 h-3 bg-white border border-blue-500 z-10 cursor-move -translate-x-1/2 -translate-y-8" @mousedown.stop="startRotating($event, element)"></div>
                </template>
            </div>
        </div>

        <!-- Element Properties Panel (when an element is selected) -->
        <div v-if="selectedElement" class="absolute bottom-4 left-4 bg-white rounded-lg shadow p-4 w-72" @click.stop>
            <h3 class="text-sm font-medium mb-3">Element Properties</h3>
            
            <!-- Common properties -->
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Position X</label>
                    <input 
                        type="number" 
                        :value="selectedElement.x"
                        @input="updateElementProperty('x', parseFloat($event.target.value))"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full px-2 py-1 border rounded text-sm"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Position Y</label>
                    <input 
                        type="number" 
                        :value="selectedElement.y"
                        @input="updateElementProperty('y', parseFloat($event.target.value))"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full px-2 py-1 border rounded text-sm"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Width</label>
                    <input 
                        type="number" 
                        :value="selectedElement.width"
                        @input="updateElementProperty('width', parseFloat($event.target.value))"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full px-2 py-1 border rounded text-sm"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Height</label>
                    <input 
                        type="number" 
                        :value="selectedElement.height"
                        @input="updateElementProperty('height', parseFloat($event.target.value))"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full px-2 py-1 border rounded text-sm"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Rotation</label>
                    <input 
                        type="number" 
                        :value="selectedElement.rotation"
                        @input="updateElementProperty('rotation', parseFloat($event.target.value))"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full px-2 py-1 border rounded text-sm"
                    >
                </div>
            </div>
            
            <!-- Text-specific properties -->
            <div v-if="selectedElement.type === 'text'" class="grid grid-cols-2 gap-3 mb-3">
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Text</label>
                    <input 
                        type="text" 
                        :value="selectedElement.content"
                        @input="updateElementProperty('content', $event.target.value)"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full px-2 py-1 border rounded text-sm"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Font Size</label>
                    <input 
                        type="number" 
                        :value="selectedElement.fontSize"
                        @input="updateElementProperty('fontSize', parseFloat($event.target.value))"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full px-2 py-1 border rounded text-sm"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Font Family</label>
                    <select 
                        :value="selectedElement.fontFamily"
                        @input="updateElementProperty('fontFamily', $event.target.value)"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full px-2 py-1 border rounded text-sm"
                    >
                        <option value="Arial">Arial</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Verdana">Verdana</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Color</label>
                    <input 
                        type="color" 
                        :value="selectedElement.color" 
                        @input="updateElementProperty('color', $event.target.value)"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full h-8"
                    >
                </div>
            </div>
            
            <!-- Shape-specific properties -->
            <div v-if="selectedElement.type === 'shape'" class="grid grid-cols-2 gap-3 mb-3">
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Fill Color</label>
                    <input 
                        type="color" 
                        :value="selectedElement.color" 
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full h-8"
                    >
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Border Color</label>
                    <input 
                        type="color" 
                        :value="selectedElement.borderColor" 
                        @input="updateElementProperty('borderColor', $event.target.value)"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full h-8"
                    >
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Border Radius</label>
                    <input 
                        type="range" 
                        min="0" 
                        max="100" 
                        :value="selectedElement.borderRadius" 
                        @input="updateElementProperty('borderRadius', parseFloat($event.target.value))"
                        @change="saveToHistory"
                        @click.stop
                        @keydown.stop
                        class="w-full"
                    >
                </div>
            </div>
            
            <!-- Layer controls -->
            <div class="flex space-x-2 mb-3">
                <button 
                    @click="moveLayer('top')" 
                    class="bg-gray-100 hover:bg-gray-200 text-xs px-2 py-1 rounded"
                    title="Move to top"
                >
                    Top
                </button>
                <button 
                    @click="moveLayer('up')" 
                    class="bg-gray-100 hover:bg-gray-200 text-xs px-2 py-1 rounded"
                    title="Move up one layer"
                >
                    Up
                </button>
                <button 
                    @click="moveLayer('down')" 
                    class="bg-gray-100 hover:bg-gray-200 text-xs px-2 py-1 rounded"
                    title="Move down one layer"
                >
                    Down
                </button>
                <button 
                    @click="moveLayer('bottom')" 
                    class="bg-gray-100 hover:bg-gray-200 text-xs px-2 py-1 rounded"
                    title="Move to bottom"
                >
                    Bottom
                </button>
            </div>
            
            <!-- Delete button -->
            <button 
                @click="elements = elements.filter(el => el.id !== selectedElement.id); selectedElement = null; saveToHistory();" 
                class="w-full bg-red-100 hover:bg-red-200 text-red-700 mt-2 text-xs px-2 py-1 rounded"
            >
                Delete Element
            </button>
        </div>

        <!-- Mouse move handler for transformations -->
        <div 
            v-if="isDragging || isResizing || isRotating"
            class="fixed inset-0 z-50"
            :class="{
                'cursor-move': isDragging,
                'cursor-nwse-resize': isResizing && ['nw', 'se'].includes(resizeDirection),
                'cursor-nesw-resize': isResizing && ['ne', 'sw'].includes(resizeDirection),
                'cursor-ns-resize': isResizing && ['n', 's'].includes(resizeDirection),
                'cursor-ew-resize': isResizing && ['e', 'w'].includes(resizeDirection),
            }"
            @mousemove="handleDrag"
            @mouseup="stopTransformation"
        />
    </div>
</template>