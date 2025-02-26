<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { 
    Type, 
    Image, 
    Square, 
    Save, 
    RotateCw, 
    RotateCcw, 
    Move, 
    Layers, 
    Palette, 
    Ruler, 
    Text, 
    Settings, 
    ZoomIn, 
    ZoomOut, 
    Maximize,
    X,
    ChevronDown,
    ChevronUp
} from 'lucide-vue-next';
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

// For all transformations
const dragStartPosition = ref({ x: 0, y: 0 });
const elementStartPosition = ref({ x: 0, y: 0 });
const elementStartSize = ref({ width: 0, height: 0 });
const rotationStartPosition = ref({ x: 0, y: 0 });

// Zoom functionality
const zoomLevel = ref(1);
const maxZoom = 5;
const minZoom = 0.5;
const zoomStep = 0.1;

// Properties panel controls
const activePropertyTab = ref('transform'); // 'transform', 'style', 'text', 'layer'
const propertiesPanelExpanded = ref(true);

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
    const displayScale = Math.min(widthScale, heightScale, 1) * zoomLevel.value;

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

// Zoom functions
const zoomIn = () => {
    if (zoomLevel.value < maxZoom) {
        zoomLevel.value = Math.min(maxZoom, zoomLevel.value + zoomStep);
    }
};

const zoomOut = () => {
    if (zoomLevel.value > minZoom) {
        zoomLevel.value = Math.max(minZoom, zoomLevel.value - zoomStep);
    }
};

const resetZoom = () => {
    zoomLevel.value = 1;
};

// Handle zoom from mouse wheel
const handleWheel = (e) => {
    if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        if (e.deltaY < 0) {
            zoomIn();
        } else {
            zoomOut();
        }
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
        content: elementType === 'text' ? 'Click to edit' : null,
        rotation: 0,
        fontSize: 12,
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
    
    // Store starting positions in client coordinates
    dragStartPosition.value = { x: e.clientX, y: e.clientY };
    elementStartPosition.value = { x: element.x, y: element.y };
};

const startResizing = (e, element, direction) => {
    e.stopPropagation();
    
    selectedElement.value = element;
    isResizing.value = true;
    resizeDirection.value = direction;
    
    // Store starting positions and size
    dragStartPosition.value = { x: e.clientX, y: e.clientY };
    elementStartPosition.value = { x: element.x, y: element.y };
    elementStartSize.value = { width: element.width, height: element.height };
};

const startRotating = (e, element) => {
    e.stopPropagation();
    
    selectedElement.value = element;
    isRotating.value = true;
    
    // Store the starting mouse position
    rotationStartPosition.value = { x: e.clientX, y: e.clientY };
    
    // Calculate the center of the element in screen coordinates
    const centerX = element.x + element.width / 2;
    const centerY = element.y + element.height / 2;
    const centerScreenX = centerX * canvasSettings.value.displayScale;
    const centerScreenY = centerY * canvasSettings.value.displayScale;
    
    // Calculate the initial angle
    const dx = e.clientX - centerScreenX;
    const dy = e.clientY - centerScreenY;
    rotationStartAngle.value = Math.atan2(dy, dx) * 180 / Math.PI - element.rotation;
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
    // Handle dragging
    if (isDragging.value && selectedElement.value) {
        // Calculate delta in client coordinates
        const deltaX = e.clientX - dragStartPosition.value.x;
        const deltaY = e.clientY - dragStartPosition.value.y;
        
        // Apply delta to element's starting position
        const newX = elementStartPosition.value.x + deltaX / canvasSettings.value.displayScale;
        const newY = elementStartPosition.value.y + deltaY / canvasSettings.value.displayScale;
        
        // Update element
        elements.value = elements.value.map(el => {
            if (el.id === selectedElement.value.id) {
                return { ...el, x: newX, y: newY };
            }
            return el;
        });
        
        // Update selected element reference
        selectedElement.value = elements.value.find(el => el.id === selectedElement.value.id);
    }
    // Handle resizing
    else if (isResizing.value && selectedElement.value) {
        const deltaX = (e.clientX - dragStartPosition.value.x) / canvasSettings.value.displayScale;
        const deltaY = (e.clientY - dragStartPosition.value.y) / canvasSettings.value.displayScale;
        
        let newWidth = elementStartSize.value.width;
        let newHeight = elementStartSize.value.height;
        let newX = elementStartPosition.value.x;
        let newY = elementStartPosition.value.y;
        
        // Handle different resize directions
        switch (resizeDirection.value) {
            case 'n':
                newHeight = elementStartSize.value.height - deltaY;
                newY = elementStartPosition.value.y + deltaY;
                break;
            case 's':
                newHeight = elementStartSize.value.height + deltaY;
                break;
            case 'e':
                newWidth = elementStartSize.value.width + deltaX;
                break;
            case 'w':
                newWidth = elementStartSize.value.width - deltaX;
                newX = elementStartPosition.value.x + deltaX;
                break;
            case 'ne':
                newWidth = elementStartSize.value.width + deltaX;
                newHeight = elementStartSize.value.height - deltaY;
                newY = elementStartPosition.value.y + deltaY;
                break;
            case 'nw':
                newWidth = elementStartSize.value.width - deltaX;
                newHeight = elementStartSize.value.height - deltaY;
                newX = elementStartPosition.value.x + deltaX;
                newY = elementStartPosition.value.y + deltaY;
                break;
            case 'se':
                newWidth = elementStartSize.value.width + deltaX;
                newHeight = elementStartSize.value.height + deltaY;
                break;
            case 'sw':
                newWidth = elementStartSize.value.width - deltaX;
                newHeight = elementStartSize.value.height + deltaY;
                newX = elementStartPosition.value.x + deltaX;
                break;
        }
        
        // Ensure minimum size
        newWidth = Math.max(50, newWidth);
        newHeight = Math.max(50, newHeight);
        
        // Update element
        elements.value = elements.value.map(el => {
            if (el.id === selectedElement.value.id) {
                return { ...el, width: newWidth, height: newHeight, x: newX, y: newY };
            }
            return el;
        });
        
        // Update selected element reference
        selectedElement.value = elements.value.find(el => el.id === selectedElement.value.id);
    }
    // Handle rotation
    else if (isRotating.value && selectedElement.value) {
        const element = selectedElement.value;
        
        // Calculate the center of the element in screen coordinates
        const centerX = element.x + element.width / 2;
        const centerY = element.y + element.height / 2;
        const centerScreenX = centerX * canvasSettings.value.displayScale;
        const centerScreenY = centerY * canvasSettings.value.displayScale;
        
        // Calculate angle between center and current mouse position
        const dx = e.clientX - centerScreenX;
        const dy = e.clientY - centerScreenY;
        const currentAngle = Math.atan2(dy, dx) * 180 / Math.PI;
        
        // Calculate new rotation
        let newRotation = currentAngle - rotationStartAngle.value;
        
        // Normalize rotation to 0-360 range
        newRotation = ((newRotation % 360) + 360) % 360;
        
        // Update element
        elements.value = elements.value.map(el => {
            if (el.id === selectedElement.value.id) {
                return { ...el, rotation: newRotation };
            }
            return el;
        });
        
        // Update selected element reference
        selectedElement.value = elements.value.find(el => el.id === selectedElement.value.id);
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
        @wheel.passive="handleWheel"
    >
        <!-- Top Toolbar -->
        <div class="absolute top-0 left-0 right-0 bg-white shadow-md z-20 flex justify-between items-center px-4 py-2">
            <!-- Left: Product info -->
            <div class="text-sm font-medium">
                {{ product.name }} - {{ product.finished_width }}" × {{ product.finished_length }}"
            </div>
            
            <!-- Center: Zoom controls -->
            <div class="flex items-center space-x-2">
                <button 
                    @click="zoomOut" 
                    :disabled="zoomLevel <= minZoom"
                    class="p-1.5 rounded-full hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" 
                    title="Zoom Out"
                >
                    <ZoomOut class="size-4" />
                </button>
                
                <div class="text-sm font-medium w-16 text-center">
                    {{ Math.round(zoomLevel * 100) }}%
                </div>
                
                <button 
                    @click="zoomIn" 
                    :disabled="zoomLevel >= maxZoom"
                    class="p-1.5 rounded-full hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" 
                    title="Zoom In"
                >
                    <ZoomIn class="size-4" />
                </button>
                
                <button 
                    @click="resetZoom" 
                    class="p-1.5 rounded-full hover:bg-gray-100" 
                    title="Reset Zoom"
                >
                    <Maximize class="size-4" />
                </button>
            </div>
            
            <!-- Right: History and Save controls -->
            <div class="flex space-x-2">
                <button 
                    @click="undo" 
                    :disabled="historyIndex <= 0"
                    class="p-1.5 rounded-full hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" 
                    title="Undo"
                >
                    <RotateCcw class="size-4" />
                </button>
                <button 
                    @click="redo" 
                    :disabled="historyIndex >= designHistory.length - 1"
                    class="p-1.5 rounded-full hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" 
                    title="Redo"
                >
                    <RotateCw class="size-4" />
                </button>
                <button 
                    @click="saveDesign.flush" 
                    class="p-1.5 rounded-full hover:bg-gray-100" 
                    title="Save"
                >
                    <Save class="size-4" />
                </button>
                <div v-if="saveStatus" class="flex items-center ml-1">
                    <span v-if="saveStatus === 'saving'" class="text-xs text-gray-500">Saving...</span>
                    <span v-else-if="saveStatus === 'saved'" class="text-xs text-green-600">Saved</span>
                    <span v-else-if="saveStatus === 'error'" class="text-xs text-red-600">Error</span>
                </div>
            </div>
        </div>

        <!-- Properties Panel (when an element is selected) -->
        <div 
            v-if="selectedElement" 
            class="absolute top-12 left-0 right-0 bg-white shadow-md z-20"
            @click.stop
        >
            <!-- Panel Header with Tabs -->
            <div class="flex border-b border-gray-200">
                <!-- Tab buttons -->
                <button 
                    @click="activePropertyTab = 'transform'" 
                    class="flex items-center px-4 py-2 text-sm border-b-2 transition-colors focus:outline-none"
                    :class="activePropertyTab === 'transform' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:bg-gray-50'"
                >
                    <Move class="size-4 mr-2" />
                    <span>Transform</span>
                </button>
                
                <button 
                    v-if="selectedElement.type === 'text'"
                    @click="activePropertyTab = 'text'" 
                    class="flex items-center px-4 py-2 text-sm border-b-2 transition-colors focus:outline-none"
                    :class="activePropertyTab === 'text' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:bg-gray-50'"
                >
                    <Text class="size-4 mr-2" />
                    <span>Text</span>
                </button>
                
                <button 
                    @click="activePropertyTab = 'style'" 
                    class="flex items-center px-4 py-2 text-sm border-b-2 transition-colors focus:outline-none"
                    :class="activePropertyTab === 'style' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:bg-gray-50'"
                >
                    <Palette class="size-4 mr-2" />
                    <span>Style</span>
                </button>
                
                <button 
                    @click="activePropertyTab = 'layer'" 
                    class="flex items-center px-4 py-2 text-sm border-b-2 transition-colors focus:outline-none"
                    :class="activePropertyTab === 'layer' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:bg-gray-50'"
                >
                    <Layers class="size-4 mr-2" />
                    <span>Layer</span>
                </button>
                
                <div class="ml-auto flex items-center px-2">
                    <button 
                        @click="elements = elements.filter(el => el.id !== selectedElement.id); selectedElement = null; saveToHistory();" 
                        class="p-1.5 rounded-full hover:bg-red-100 hover:text-red-600 transition-colors"
                        title="Delete Element"
                    >
                        <X class="size-4" />
                    </button>
                    
                    <button 
                        @click="propertiesPanelExpanded = !propertiesPanelExpanded" 
                        class="p-1.5 rounded-full hover:bg-gray-100 ml-2"
                        :title="propertiesPanelExpanded ? 'Collapse Panel' : 'Expand Panel'"
                    >
                        <ChevronUp v-if="propertiesPanelExpanded" class="size-4" />
                        <ChevronDown v-else class="size-4" />
                    </button>
                </div>
            </div>
            
            <!-- Panel Content -->
            <div v-if="propertiesPanelExpanded" class="p-4">
                <!-- Transform Properties -->
                <div v-if="activePropertyTab === 'transform'">
                    <div class="grid grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Position X</label>
                            <input 
                                type="number" 
                                :value="selectedElement.x"
                                @input="updateElementProperty('x', parseFloat($event.target.value))"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            >
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Rotation (°)</label>
                            <input 
                                type="number" 
                                :value="selectedElement.rotation"
                                @input="updateElementProperty('rotation', parseFloat($event.target.value))"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            >
                        </div>
                    </div>
                </div>
                
                <!-- Text Properties -->
                <div v-if="activePropertyTab === 'text' && selectedElement.type === 'text'">
                    <div class="grid grid-cols-4 gap-4">
                        <div class="col-span-4 mb-2">
                            <label class="block text-xs text-gray-500 mb-1">Text Content</label>
                            <input 
                                type="text" 
                                :value="selectedElement.content"
                                @input="updateElementProperty('content', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            >
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Font Family</label>
                            <select 
                                :value="selectedElement.fontFamily"
                                @input="updateElementProperty('fontFamily', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            >
                                <option value="Arial">Arial</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Courier New">Courier New</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Verdana">Verdana</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Color</label>
                            <div class="flex items-center">
                                <input 
                                    type="color" 
                                    :value="selectedElement.color" 
                                    @input="updateElementProperty('color', $event.target.value)"
                                    @change="saveToHistory"
                                    @click.stop
                                    @keydown.stop
                                    class="w-8 h-8 p-0 rounded border border-gray-300"
                                >
                                <input 
                                    type="text" 
                                    :value="selectedElement.color"
                                    @input="updateElementProperty('color', $event.target.value)"
                                    @change="saveToHistory"
                                    @click.stop
                                    @keydown.stop
                                    class="flex-1 ml-2 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                >
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Style Properties -->
                <div v-if="activePropertyTab === 'style'">
                    <div class="grid grid-cols-4 gap-4">
                        <div v-if="selectedElement.type === 'shape'" class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Fill Color</label>
                            <div class="flex items-center">
                                <input 
                                    type="color" 
                                    :value="selectedElement.color" 
                                    @input="updateElementProperty('color', $event.target.value)"
                                    @change="saveToHistory"
                                    @click.stop
                                    @keydown.stop
                                    class="w-8 h-8 p-0 rounded border border-gray-300"
                                >
                                <input 
                                    type="text" 
                                    :value="selectedElement.color"
                                    @input="updateElementProperty('color', $event.target.value)"
                                    @change="saveToHistory"
                                    @click.stop
                                    @keydown.stop
                                    class="flex-1 ml-2 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                >
                            </div>
                        </div>
                        
                        <div v-if="selectedElement.type === 'shape'" class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Border Color</label>
                            <div class="flex items-center">
                                <input 
                                    type="color" 
                                    :value="selectedElement.borderColor || '#000000'" 
                                    @input="updateElementProperty('borderColor', $event.target.value)"
                                    @change="saveToHistory"
                                    @click.stop
                                    @keydown.stop
                                    class="w-8 h-8 p-0 rounded border border-gray-300"
                                >
                                <input 
                                    type="text" 
                                    :value="selectedElement.borderColor || '#000000'"
                                    @input="updateElementProperty('borderColor', $event.target.value)"
                                    @change="saveToHistory"
                                    @click.stop
                                    @keydown.stop
                                    class="flex-1 ml-2 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                >
                            </div>
                        </div>
                        
                        <div v-if="selectedElement.type === 'shape'" class="col-span-4">
                            <label class="block text-xs text-gray-500 mb-1">Border Radius: {{ selectedElement.borderRadius || 0 }}px</label>
                            <input 
                                type="range" 
                                min="0" 
                                max="100" 
                                :value="selectedElement.borderRadius || 0" 
                                @input="updateElementProperty('borderRadius', parseFloat($event.target.value))"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                            >
                        </div>
                        
                        <div v-if="selectedElement.type === 'text'" class="col-span-4">
                            <label class="block text-xs text-gray-500 mb-1">Text Color</label>
                            <div class="flex items-center">
                                <input 
                                    type="color" 
                                    :value="selectedElement.color" 
                                    @input="updateElementProperty('color', $event.target.value)"
                                    @change="saveToHistory"
                                    @click.stop
                                    @keydown.stop
                                    class="w-8 h-8 p-0 rounded border border-gray-300"
                                >
                                <input 
                                    type="text" 
                                    :value="selectedElement.color"
                                    @input="updateElementProperty('color', $event.target.value)"
                                    @change="saveToHistory"
                                    @click.stop
                                    @keydown.stop
                                    class="flex-1 ml-2 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                >
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Layer Properties -->
                <div v-if="activePropertyTab === 'layer'">
                    <div class="flex flex-col items-center">
                        <div class="grid grid-cols-2 gap-4 w-full mb-4">
                            <button 
                                @click="moveLayer('top')" 
                                class="flex items-center justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <span class="mr-2">Bring to Front</span>
                                <Layers class="size-4" />
                            </button>
                            <button 
                                @click="moveLayer('up')" 
                                class="flex items-center justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <span class="mr-2">Bring Forward</span>
                                <ChevronUp class="size-4" />
                            </button>
                            <button 
                                @click="moveLayer('down')" 
                                class="flex items-center justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <span class="mr-2">Send Backward</span>
                                <ChevronDown class="size-4" />
                            </button>
                            <button 
                                @click="moveLayer('bottom')" 
                                class="flex items-center justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <span class="mr-2">Send to Back</span>
                                <Layers class="size-4 transform rotate-180" />
                            </button>
                        </div>
                        
                        <button 
                            @click="elements = elements.filter(el => el.id !== selectedElement.id); selectedElement = null; saveToHistory();" 
                            class="py-2 px-4 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 w-full"
                        >
                            Delete Element
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canvas Area -->
        <div 
            class="relative bg-white shadow-lg mx-auto mt-20 mb-8"
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
                    position: 'absolute',
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

        <!-- Zoom Controls (bottom right) -->
        <div class="fixed bottom-4 right-4 bg-white rounded-full shadow-md p-1 flex items-center space-x-1 z-20">
            <button 
                @click="zoomOut" 
                :disabled="zoomLevel <= minZoom"
                class="p-1.5 rounded-full hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" 
                title="Zoom Out"
            >
                <ZoomOut class="size-5" />
            </button>
            
            <button 
                @click="resetZoom" 
                class="p-1.5 rounded-full hover:bg-gray-100" 
                title="Reset Zoom"
            >
                <Maximize class="size-5" />
            </button>
            
            <button 
                @click="zoomIn" 
                :disabled="zoomLevel >= maxZoom"
                class="p-1.5 rounded-full hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" 
                title="Zoom In"
            >
                <ZoomIn class="size-5" />
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