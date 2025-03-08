<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { 
    Type, 
    Image, 
    Square, 
    Circle,
    Triangle,
    Minus,
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
    ChevronUp,
    ChevronLeft,
    ChevronRight,
    Download,
    FileText,
    MousePointer2,
    Hand,
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
    },
    designId: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['update:modelValue', 'update:designId']);

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

const isPdfDownloading = ref(false); // Track PDF download status

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

// Pan-related refs
const isPanning = ref(false);
const panOffset = ref({ x: 0, y: 0 });
const panStartPosition = ref({ x: 0, y: 0 });
const isPanDragging = ref(false);

const useMetric = ref(false); // Default to inches
const toggleUnitSystem = () => {
    useMetric.value = !useMetric.value;
};

const pixelsToDisplayUnits = (pixels) => {
    const inches = pixels / canvasSettings.value.dpi;
    if (useMetric.value) {
        // Convert inches to centimeters (1 inch = 2.54 cm)
        return (inches * 2.54).toFixed(2);
    } else {
        return inches.toFixed(2);
    }
};

const displayUnitsToPixels = (value) => {
    if (useMetric.value) {
        // Convert from cm to inches, then to pixels
        return (parseFloat(value) / 2.54) * canvasSettings.value.dpi;
    } else {
        // Convert from inches to pixels
        return parseFloat(value) * canvasSettings.value.dpi;
    }
};

const unitDisplayText = computed(() => {
    return useMetric.value ? 'cm' : 'in';
});

const updateElementPropertyWithUnits = (property, value) => {
    if (!selectedElement.value) return;
    
    // Convert from display units to pixels for position and size properties
    const pixelProperties = ['x', 'y', 'width', 'height'];
    const newValue = pixelProperties.includes(property) 
        ? displayUnitsToPixels(value)
        : value;
    
    // Update the property in the elements array
    elements.value = elements.value.map(el => {
        if (el.id === selectedElement.value.id) {
            return { ...el, [property]: newValue };
        }
        return el;
    });
    
    // Update the selected element reference
    selectedElement.value = {
        ...selectedElement.value,
        [property]: newValue
    };
    
    // Save the change to history
    saveToHistory();
};

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

// Pan-related methods
const togglePanning = () => {
    isPanning.value = !isPanning.value;
    if (!isPanning.value) {
        document.body.style.cursor = 'default';
        isPanDragging.value = false;
    }
};

const startPanning = (e) => {
    if (!isPanning.value) return;
    e.preventDefault();
    
    // Start panning regardless of what was clicked when in panning mode
    isPanDragging.value = true;
    panStartPosition.value = { x: e.clientX, y: e.clientY };
};

const handlePan = (e) => {
    if (!isPanning.value || !isPanDragging.value) return;
    
    const deltaX = e.clientX - panStartPosition.value.x;
    const deltaY = e.clientY - panStartPosition.value.y;
    
    panOffset.value = {
        x: panOffset.value.x + deltaX / canvasSettings.value.displayScale,
        y: panOffset.value.y + deltaY / canvasSettings.value.displayScale
    };
    
    panStartPosition.value = { x: e.clientX, y: e.clientY };
};

const stopPanning = () => {
    if (isPanning.value) {
        isPanDragging.value = false;
    }
};

// Modified canvasSettings to include pan offset
const canvasSettings = computed(() => {
    const dpi = 72;
    const bleed = props.product.bleed || 0; // Get bleed value from product or default to 0
    
    // Calculate dimensions including bleed
    const width = (parseFloat(props.product.finished_width) + (bleed * 2)) * dpi;
    const height = (parseFloat(props.product.finished_length) + (bleed * 2)) * dpi;
    
    const maxDisplayWidth = window.innerWidth * 0.7;
    const maxDisplayHeight = window.innerHeight * 0.8;
    
    const widthScale = maxDisplayWidth / width;
    const heightScale = maxDisplayHeight / height;
    const displayScale = Math.min(widthScale, heightScale, 1) * zoomLevel.value;

    return {
        width,
        height,
        dpi,
        displayScale,
        panX: panOffset.value.x,
        panY: panOffset.value.y,
        // Add bleed-related properties
        bleed: bleed * dpi,
        trimWidth: props.product.finished_width * dpi,
        trimHeight: props.product.finished_length * dpi,
        bleedValue: bleed // Store original bleed value for UI display
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
        
        // Get the design ID from parent component
        const designId = props.designId;
        
        // If we have a design ID, update existing design; otherwise create new
        if (designId) {
            // Update existing design
            await axios.put(`/api/designs/${designId}`, designData);
            console.log(`Updated existing design: ${designId}`);
        } else {
            // Create new design
            const response = await axios.post('/api/designs', designData);
            // Emit the new design ID to the parent
            emit('update:designId', response.data.id);
            console.log(`Created new design: ${response.data.id}`);
        }
        
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

    // Base element properties
    const newElement = {
        id: Date.now(),
        type: elementType,
        x,
        y,
        rotation: 0,
        zIndex: elements.value.length // Add z-index for layering
    };
    
    // Add element-specific properties
    if (elementType === 'text') {
        newElement.width = 300;
        newElement.height = 100;
        newElement.content = 'Click to edit';
        newElement.fontSize = 12;
        newElement.fontFamily = 'Arial';
        newElement.color = '#000000';
    } 
    else if (elementType === 'image') {
        newElement.width = 200;
        newElement.height = 200;
        newElement.color = '#FFFFFF';
    }
    else if (elementType === 'square') {
        newElement.type = 'shape'; // Keep type as 'shape' for compatibility
        newElement.width = 200;
        newElement.height = 200;
        newElement.color = '#FFFFFF';
        newElement.borderColor = '#000000';
        newElement.borderRadius = 0; // Square has no border radius
        newElement.shapeType = 'square'; // Add a shapeType property
    }
    else if (elementType === 'circle') {
        newElement.type = 'shape';
        newElement.width = 200;
        newElement.height = 200;
        newElement.color = '#FFFFFF';
        newElement.borderColor = '#000000';
        newElement.borderRadius = 100; // High border radius for circle
        newElement.shapeType = 'circle';
    }
    else if (elementType === 'triangle') {
        newElement.type = 'shape';
        newElement.width = 200;
        newElement.height = 200;
        newElement.color = '#FFFFFF';
        newElement.borderColor = '#000000';
        newElement.shapeType = 'triangle';
    }
    else if (elementType === 'line') {
        newElement.type = 'shape';
        newElement.width = 200;
        newElement.height = 4; // Thin height for a line
        newElement.color = '#000000';
        newElement.borderColor = '#000000';
        newElement.shapeType = 'line';
    }

    elements.value.push(newElement);
    selectedElement.value = newElement;
    saveToHistory();
};

// Modified startDragging to respect panning mode
const startDragging = (e, element) => {
    if (isPanning.value) return; // Don't drag elements when in pan mode
    if (e.target.classList.contains('handle')) return;
    
    selectedElement.value = element;
    isDragging.value = true;
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
    const rect = e.currentTarget.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    
    // Calculate the initial angle
    const dx = e.clientX - centerX;
    const dy = e.clientY - centerY;
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
        
        // Special handling for line shape - preserve its height (thickness)
        const isLine = selectedElement.value.type === 'shape' && selectedElement.value.shapeType === 'line';
        
        // Ensure minimum size - different for lines vs other elements
        if (isLine) {
            newWidth = Math.max(20, newWidth); // Minimum width for lines
            // Don't enforce minimum height for lines - keep original height (thickness)
            newHeight = elementStartSize.value.height;
        } else {
            newWidth = Math.max(50, newWidth);
            newHeight = Math.max(50, newHeight);
        }
        
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
        
        // Get the bounding rectangle of the element
        const elementRect = document.querySelector(`[data-element-id="${element.id}"]`).getBoundingClientRect();
        const centerX = elementRect.left + elementRect.width / 2;
        const centerY = elementRect.top + elementRect.height / 2;
        
        // Calculate angle between center and current mouse position
        const dx = e.clientX - centerX;
        const dy = e.clientY - centerY;
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

// PDF download methods for Lambda-based PDF generation
const downloadPdf = async () => {
    if (!props.designId) {
        // Make sure design is saved first
        await saveDesign.flush();
    }
    
    if (!props.designId) {
        alert('Please save your design before downloading a PDF');
        return;
    }
    
    isPdfDownloading.value = true;
    
    try {
        // Start the PDF generation process - this will trigger the Lambda function
        const startResponse = await axios.post(`/api/designs/${props.designId}/pdf/generate`, {
            designType: 'standard'
        });
        
        const jobId = startResponse.data.job_id;
        
        // Poll for completion
        const pdfUrl = await pollForCompletion(jobId);
        
        // Download the completed PDF
        if (pdfUrl) {
            // Create a temporary anchor element to trigger the download
            const link = document.createElement('a');
            link.href = pdfUrl;
            link.setAttribute('download', `${props.product.name}_design.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        }
    } catch (error) {
        console.error('Error downloading PDF:', error);
        alert('Error downloading PDF. Please try again.');
    } finally {
        isPdfDownloading.value = false;
    }
};

// Download print-ready PDF with crop marks using Lambda
const downloadPrintReadyPdf = async () => {
    if (!props.designId) {
        // Make sure design is saved first
        await saveDesign.flush();
    }
    
    if (!props.designId) {
        alert('Please save your design before downloading a print-ready PDF');
        return;
    }
    
    isPdfDownloading.value = true;
    
    try {
        // Start the PDF generation process with print-ready type
        const startResponse = await axios.post(`/api/designs/${props.designId}/pdf/generate`, {
            designType: 'print_ready'
        });
        
        const jobId = startResponse.data.job_id;
        
        // Poll for completion
        const pdfUrl = await pollForCompletion(jobId);
        
        // Download the completed PDF
        if (pdfUrl) {
            // Create a temporary anchor element to trigger the download
            const link = document.createElement('a');
            link.href = pdfUrl;
            link.setAttribute('download', `${props.product.name}_print-ready.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        }
    } catch (error) {
        console.error('Error downloading print-ready PDF:', error);
        alert('Error downloading print-ready PDF. Please try again.');
    } finally {
        isPdfDownloading.value = false;
    }
};

// Helper function to poll for PDF generation completion
const pollForCompletion = async (jobId) => {
    const maxAttempts = 30; // Maximum polling attempts
    const pollingInterval = 2000; // Polling interval in milliseconds
    
    for (let attempt = 0; attempt < maxAttempts; attempt++) {
        try {
            // Check job status
            const statusResponse = await axios.get(`/api/pdf-jobs/${jobId}/status`);
            const status = statusResponse.data.status;
            
            if (status === 'completed') {
                // PDF is ready, return the download URL
                return statusResponse.data.download_url;
            } else if (status === 'failed') {
                // PDF generation failed
                throw new Error(statusResponse.data.error || 'PDF generation failed');
            }
            
            // Wait before polling again
            await new Promise(resolve => setTimeout(resolve, pollingInterval));
        } catch (error) {
            console.error('Error polling for PDF status:', error);
            throw error;
        }
    }
    
    // If we reach here, the PDF generation is taking too long
    throw new Error('PDF generation timed out. Please try again later.');
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
    // Separate listeners for panning
    window.addEventListener('mousemove', handlePan);
    window.addEventListener('mouseup', stopPanning);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('mousemove', handlePan);
    window.removeEventListener('mouseup', stopPanning);
    saveDesign.flush();
});

// For direct text editing
const directEditingElement = ref(null);
const directEditingTextarea = ref(null);

// Format text with line breaks for display
const formatTextWithLineBreaks = (text) => {
    if (!text) return '';
    return text.replace(/\n/g, '<br>');
};

// Start direct text editing
const startDirectTextEditing = (element, e) => {
    if (element.type !== 'text') return;
    e.stopPropagation();
    
    directEditingElement.value = element;
    
    // Focus the textarea after it's rendered
    nextTick(() => {
        if (directEditingTextarea.value) {
            directEditingTextarea.value.focus();
        }
    });
};

// Update text during direct editing
const updateDirectEditingText = (e) => {
    if (!directEditingElement.value) return;
    
    // Update the element in the elements array
    elements.value = elements.value.map(el => {
        if (el.id === directEditingElement.value.id) {
            return { ...el, content: e.target.value };
        }
        return el;
    });
    
    // Update the selected element if it's the same as the one being edited
    if (selectedElement.value && selectedElement.value.id === directEditingElement.value.id) {
        selectedElement.value = {
            ...selectedElement.value,
            content: e.target.value
        };
    }
    
    // Update the direct editing element reference
    directEditingElement.value = elements.value.find(el => el.id === directEditingElement.value.id);
};

// Continue editing when Shift+Enter is pressed (add a new line)
const continueDirectEditing = () => {
    // Do nothing, just prevent the default behavior which would stop editing
};

// Stop direct text editing
const stopDirectTextEditing = () => {
    if (directEditingElement.value) {
        saveToHistory();
        directEditingElement.value = null;
    }
};
</script>

<template>
    <div 
        class="relative w-full h-full bg-gray-100 overflow-auto"
        @click="handleCanvasClick"
        @wheel="handleWheel"
        @mousedown="startPanning"
        :class="{ 
            'cursor-grab': isPanning && !isPanDragging, 
            'cursor-grabbing': isPanning && isPanDragging 
        }"
    >
        <!-- Top Toolbar -->
        <div class="absolute top-0 left-0 right-0 bg-white shadow-md z-20 flex justify-between items-center px-4 py-2">
            <!-- Left: Product info -->
            <div class="text-sm font-medium">
                {{ product.name }} - {{ product.finished_width }}" × {{ product.finished_length }}"
                <span v-if="product.bleed" class="text-xs ml-1 text-gray-500">
                    (+ {{ product.bleed }}" bleed)
                </span>
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
                <!-- New PDF Download Button -->
                <button 
                    @click="downloadPdf" 
                    class="p-1.5 rounded-full hover:bg-gray-100" 
                    title="Download PDF"
                    :disabled="isPdfDownloading || !designId"
                >
                    <FileText class="size-4" :class="{'text-gray-400': !designId}" />
                </button>
                <!-- New Print-Ready PDF Download Button -->
                <div class="relative">
                    <button 
                        @click="downloadPrintReadyPdf" 
                        class="p-1.5 rounded-full hover:bg-gray-100" 
                        title="Download Print-Ready PDF"
                        :disabled="isPdfDownloading || !designId"
                    >
                        <Download class="size-4" :class="{'text-gray-400': !designId}" />
                    </button>
                </div>
                <div v-if="saveStatus" class="flex items-center ml-1">
                    <span v-if="saveStatus === 'saving'" class="text-xs text-gray-500">Saving...</span>
                    <span v-else-if="saveStatus === 'saved'" class="text-xs text-green-600">Saved</span>
                    <span v-else-if="saveStatus === 'error'" class="text-xs text-red-600">Error</span>
                </div>
                <div v-if="isPdfDownloading" class="flex items-center ml-1">
                    <span class="text-xs text-gray-500">Generating PDF...</span>
                </div>
            </div>
        </div>

        <!-- Properties Panel -->
        <div 
            v-if="selectedElement" 
            class="absolute top-12 left-0 right-0 bg-white shadow-md z-20"
            @click.stop
        >
            <!-- Panel Header with Tabs (reduced vertical size) -->
            <div class="flex border-b border-gray-200 h-10">
                <button 
                    @click="activePropertyTab = 'transform'" 
                    class="flex items-center px-3 py-1 text-sm border-b-2 transition-colors focus:outline-none"
                    :class="activePropertyTab === 'transform' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:bg-gray-50'"
                >
                    <Move class="size-4 mr-1" />
                    <span>Transform</span>
                </button>
                
                <button 
                    v-if="selectedElement.type === 'text'"
                    @click="activePropertyTab = 'text'" 
                    class="flex items-center px-3 py-1 text-sm border-b-2 transition-colors focus:outline-none"
                    :class="activePropertyTab === 'text' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:bg-gray-50'"
                >
                    <Text class="size-4 mr-1" />
                    <span>Text</span>
                </button>
                
                <button 
                    @click="activePropertyTab = 'style'" 
                    class="flex items-center px-3 py-1 text-sm border-b-2 transition-colors focus:outline-none"
                    :class="activePropertyTab === 'style' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:bg-gray-50'"
                >
                    <Palette class="size-4 mr-1" />
                    <span>Style</span>
                </button>
                
                <button 
                    @click="activePropertyTab = 'layer'" 
                    class="flex items-center px-3 py-1 text-sm border-b-2 transition-colors focus:outline-none"
                    :class="activePropertyTab === 'layer' ? 'border-blue-500 text-blue-600' : 'border-transparent hover:bg-gray-50'"
                >
                    <Layers class="size-4 mr-1" />
                    <span>Layer</span>
                </button>
                
                <div class="ml-auto flex items-center px-2">
                    <button 
                        @click="elements = elements.filter(el => el.id !== selectedElement.id); selectedElement = null; saveToHistory();" 
                        class="px-2 py-1 text-sm text-red-600 hover:bg-red-100 rounded transition-colors"
                        title="Delete Element"
                    >
                        Delete Element
                    </button>
                    
                    <button 
                        @click="propertiesPanelExpanded = !propertiesPanelExpanded" 
                        class="p-1 rounded-full hover:bg-gray-100 ml-2"
                        :title="propertiesPanelExpanded ? 'Collapse Panel' : 'Expand Panel'"
                    >
                        <ChevronUp v-if="propertiesPanelExpanded" class="size-4" />
                        <ChevronDown v-else class="size-4" />
                    </button>
                </div>
            </div>
            
        <!-- Panel Content (reduced vertical height) -->
        <div v-if="propertiesPanelExpanded" class="p-2"> 
            <!-- Transform Properties -->
            <div v-if="activePropertyTab === 'transform'">
                <div class="flex flex-row gap-2 overflow-x-auto pb-1">
                    <div class="min-w-[100px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Position X ({{ unitDisplayText }})</label> 
                        <input 
                            type="number" 
                            step="0.1"
                            :value="pixelsToDisplayUnits(selectedElement.x)"
                            @input="updateElementPropertyWithUnits('x', $event.target.value)"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full px-2 py-1 border border-gray-300 rounded-md text-sm" 
                        >
                    </div>
                    <div class="min-w-[100px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Position Y ({{ unitDisplayText }})</label>
                        <input 
                            type="number" 
                            step="0.1"
                            :value="pixelsToDisplayUnits(selectedElement.y)"
                            @input="updateElementPropertyWithUnits('y', $event.target.value)"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full px-2 py-1 border border-gray-300 rounded-md text-sm"
                        >
                    </div>
                    <div class="min-w-[100px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Width ({{ unitDisplayText }})</label>
                        <input 
                            type="number" 
                            step="0.1"
                            :value="pixelsToDisplayUnits(selectedElement.width)"
                            @input="updateElementPropertyWithUnits('width', $event.target.value)"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full px-2 py-1 border border-gray-300 rounded-md text-sm"
                        >
                    </div>
                    <div class="min-w-[100px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Height ({{ unitDisplayText }})</label>
                        <input 
                            type="number" 
                            step="0.1"
                            :value="pixelsToDisplayUnits(selectedElement.height)"
                            @input="updateElementPropertyWithUnits('height', $event.target.value)"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full px-2 py-1 border border-gray-300 rounded-md text-sm"
                        >
                    </div>
                    <div class="min-w-[100px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Rotation (°)</label>
                        <input 
                            type="number" 
                            :value="selectedElement.rotation"
                            @input="updateElementProperty('rotation', parseFloat($event.target.value))"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full px-2 py-1 border border-gray-300 rounded-md text-sm"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Text Properties -->
            <div v-if="activePropertyTab === 'text' && selectedElement.type === 'text'">
                <div class="flex flex-row gap-2 overflow-x-auto pb-1">
                    <div class="min-w-[200px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Text Content</label>
                        <textarea 
                            :value="selectedElement.content"
                            @input="updateElementProperty('content', $event.target.value)"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full px-2 py-1 border border-gray-300 rounded-md text-sm resize-y"
                            rows="1"
                        ></textarea>
                    </div>
                    <div class="min-w-[80px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Font Size</label>
                        <input 
                            type="number" 
                            :value="selectedElement.fontSize"
                            @input="updateElementProperty('fontSize', parseFloat($event.target.value))"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full px-2 py-1 border border-gray-300 rounded-md text-sm"
                        >
                    </div>
                    <div class="min-w-[140px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Font Family</label>
                        <select 
                            :value="selectedElement.fontFamily"
                            @input="updateElementProperty('fontFamily', $event.target.value)"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full px-2 py-1 border border-gray-300 rounded-md text-sm"
                        >
                            <option value="Arial">Arial</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Courier New">Courier New</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Verdana">Verdana</option>
                        </select>
                    </div>
                    <div class="min-w-[140px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Color</label>
                        <div class="flex items-center">
                            <input 
                                type="color" 
                                :value="selectedElement.color" 
                                @input="updateElementProperty('color', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-6 h-6 p-0 rounded border border-gray-300"
                            >
                            <input 
                                type="text" 
                                :value="selectedElement.color"
                                @input="updateElementProperty('color', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-20 ml-1 px-2 py-1 border border-gray-300 rounded-md text-sm"
                            >
                        </div>
                    </div>
                    <div class="min-w-[140px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Alignment</label>
                        <div class="flex border border-gray-300 rounded-md overflow-hidden">
                            <button 
                                @click="updateElementProperty('textAlign', 'left')"
                                @click.stop
                                class="p-1 hover:bg-gray-100"
                                :class="{'bg-blue-50 text-blue-600': selectedElement.textAlign === 'left' || !selectedElement.textAlign}"
                                title="Align Left"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="15" y2="12"></line><line x1="3" y1="18" x2="18" y2="18"></line></svg>
                            </button>
                            <button 
                                @click="updateElementProperty('textAlign', 'center')"
                                @click.stop
                                class="p-1 hover:bg-gray-100"
                                :class="{'bg-blue-50 text-blue-600': selectedElement.textAlign === 'center'}"
                                title="Align Center"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="6" y1="12" x2="18" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                            </button>
                            <button 
                                @click="updateElementProperty('textAlign', 'right')"
                                @click.stop
                                class="p-1 hover:bg-gray-100"
                                :class="{'bg-blue-50 text-blue-600': selectedElement.textAlign === 'right'}"
                                title="Align Right"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="9" y1="12" x2="21" y2="12"></line><line x1="6" y1="18" x2="21" y2="18"></line></svg>
                            </button>
                            <button 
                                @click="updateElementProperty('textAlign', 'justify')"
                                @click.stop
                                class="p-1 hover:bg-gray-100"
                                :class="{'bg-blue-50 text-blue-600': selectedElement.textAlign === 'justify'}"
                                title="Justify"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Style Properties -->
            <div v-if="activePropertyTab === 'style'">
                <div class="flex flex-row gap-2 overflow-x-auto pb-1">
                    <div v-if="selectedElement.type === 'shape'" class="min-w-[180px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Fill Color</label>
                        <div class="flex items-center">
                            <input 
                                type="color" 
                                :value="selectedElement.color" 
                                @input="updateElementProperty('color', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-6 h-6 p-0 rounded border border-gray-300"
                            >
                            <input 
                                type="text" 
                                :value="selectedElement.color"
                                @input="updateElementProperty('color', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-20 ml-1 px-2 py-1 border border-gray-300 rounded-md text-sm"
                            >
                        </div>
                    </div>
                    
                    <div v-if="selectedElement.type === 'shape' && selectedElement.shapeType !== 'line'" class="min-w-[180px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Border Color</label>
                        <div class="flex items-center">
                            <input 
                                type="color" 
                                :value="selectedElement.borderColor || '#000000'" 
                                @input="updateElementProperty('borderColor', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-6 h-6 p-0 rounded border border-gray-300"
                            >
                            <input 
                                type="text" 
                                :value="selectedElement.borderColor || '#000000'"
                                @input="updateElementProperty('borderColor', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-20 ml-1 px-2 py-1 border border-gray-300 rounded-md text-sm"
                            >
                        </div>
                    </div>
                    
                    <!-- Only show border radius for square/circle shapes -->
                    <div v-if="selectedElement.type === 'shape' && (!selectedElement.shapeType || selectedElement.shapeType === 'square' || selectedElement.shapeType === 'circle')" class="min-w-[180px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Border Radius: {{ selectedElement.borderRadius || 0 }}px</label>
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
                    
                    <!-- Line thickness for line shape -->
                    <div v-if="selectedElement.type === 'shape' && selectedElement.shapeType === 'line'" class="min-w-[180px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Line Thickness: {{ selectedElement.height || 1 }}px</label>
                        <input 
                            type="range" 
                            min="1" 
                            max="20" 
                            :value="selectedElement.height || 1" 
                            @input="updateElementProperty('height', parseFloat($event.target.value))"
                            @change="saveToHistory"
                            @click.stop
                            @keydown.stop
                            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                        >
                    </div>
                    
                    <div v-if="selectedElement.type === 'text'" class="min-w-[180px]">
                        <label class="block text-xs text-gray-500 mb-0.5">Text Color</label>
                        <div class="flex items-center">
                            <input 
                                type="color" 
                                :value="selectedElement.color" 
                                @input="updateElementProperty('color', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-6 h-6 p-0 rounded border border-gray-300"
                            >
                            <input 
                                type="text" 
                                :value="selectedElement.color"
                                @input="updateElementProperty('color', $event.target.value)"
                                @change="saveToHistory"
                                @click.stop
                                @keydown.stop
                                class="w-20 ml-1 px-2 py-1 border border-gray-300 rounded-md text-sm"
                            >
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Layer Properties -->
            <div v-if="activePropertyTab === 'layer'">
                <div class="flex flex-row gap-2 overflow-x-auto pb-1">
                    <button 
                        @click="moveLayer('top')" 
                        class="flex items-center justify-center py-1 px-3 border border-gray-300 rounded-md text-sm whitespace-nowrap" 
                    >
                        <span class="mr-1">Bring to Front</span> 
                        <Layers class="size-4" />
                    </button>
                    <button 
                        @click="moveLayer('up')" 
                        class="flex items-center justify-center py-1 px-3 border border-gray-300 rounded-md text-sm whitespace-nowrap"
                    >
                        <span class="mr-1">Bring Forward</span>
                        <ChevronUp class="size-4" />
                    </button>
                    <button 
                        @click="moveLayer('down')" 
                        class="flex items-center justify-center py-1 px-3 border border-gray-300 rounded-md text-sm whitespace-nowrap"
                    >
                        <span class="mr-1">Send Backward</span>
                        <ChevronDown class="size-4" />
                    </button>
                    <button 
                        @click="moveLayer('bottom')" 
                        class="flex items-center justify-center py-1 px-3 border border-gray-300 rounded-md text-sm whitespace-nowrap"
                    >
                        <span class="mr-1">Send to Back</span>
                        <Layers class="size-4 transform rotate-180" />
                    </button>
                    
                    <button 
                        @click="elements = elements.filter(el => el.id !== selectedElement.id); selectedElement = null; saveToHistory();" 
                        class="py-1 px-3 border border-red-300 rounded-md text-sm text-red-700 bg-red-50 hover:bg-red-100 whitespace-nowrap ml-auto" 
                    >
                        Delete Element
                    </button>
                </div>
            </div>
        </div>
        </div>

<!-- Canvas Area -->
<div 
    class="relative bg-white shadow-lg mx-auto mt-40 mb-8"
    :style="{
        width: canvasSettings.width * canvasSettings.displayScale + 'px',
        height: canvasSettings.height * canvasSettings.displayScale + 'px',
        transform: `translate(${canvasSettings.panX}px, ${canvasSettings.panY}px)`
    }"
    @dragover="handleDragOver"
    @drop="handleDrop"
>
    <!-- Trim Line (Red Dotted Border) -->
    <div 
        class="absolute border-2 border-red-500 border-dashed pointer-events-none"
        :style="{
            top: `${canvasSettings.bleed * canvasSettings.displayScale}px`,
            left: `${canvasSettings.bleed * canvasSettings.displayScale}px`,
            width: `${canvasSettings.trimWidth * canvasSettings.displayScale}px`,
            height: `${canvasSettings.trimHeight * canvasSettings.displayScale}px`
        }"
    >
        <!-- Trim Line Label -->
        <div class="absolute -top-8 left-0 text-red-500 font-medium"
             :class="zoomLevel < 0.9 ? 'text-[7px]' : 'text-xs'">
            Trim Line - Content outside this line will be cut off
        </div>
    </div>

    <!-- Bleed Area Label -->
    <div class="absolute -bottom-16 right-1 bg-white bg-opacity-75 px-1 py-0.5 rounded text-gray-700"
         :class="zoomLevel < 0.9 ? 'text-[7px]' : 'text-xs'">
        <div>
            Dimensions: {{ pixelsToDisplayUnits(canvasSettings.trimWidth) }} × {{ pixelsToDisplayUnits(canvasSettings.trimHeight) }} {{ unitDisplayText }}
        </div>
        <div>
            Bleed: {{ useMetric ? (canvasSettings.bleedValue * 2.54).toFixed(2) : canvasSettings.bleedValue }} {{ unitDisplayText }} - Extend artwork to edges
        </div>
    </div>

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
        :data-element-id="element.id"
        @mousedown="startDragging($event, element)"
        @click.stop="handleElementClick(element, $event)"
        @dblclick.stop="startDirectTextEditing(element, $event)"
    >
        <!-- Text Element -->
        <div 
            v-if="element.type === 'text'"
            class="w-full h-full overflow-hidden"
            @dblclick.stop="startDirectTextEditing(element, $event)"
        >
            <p 
                :style="{
                    fontSize: `${element.fontSize * canvasSettings.displayScale}px`,
                    fontFamily: element.fontFamily,
                    color: element.color,
                    lineHeight: '1.2',
                    margin: '0',
                    padding: '0',
                    textAlign: element.textAlign || 'left',
                    whiteSpace: 'pre-wrap' /* This preserves line breaks */
                }"
                v-html="formatTextWithLineBreaks(element.content)"
            >
            </p>
            
            <!-- Editable overlay that appears when editing directly -->
            <textarea
                v-if="directEditingElement?.id === element.id"
                :value="element.content"
                @input="updateDirectEditingText($event)"
                @blur="stopDirectTextEditing"
                @keydown.enter.shift.prevent="continueDirectEditing"
                @keydown.enter.exact.prevent="stopDirectTextEditing"
                class="absolute inset-0 w-full h-full p-0 border-0 outline-none resize-none bg-transparent"
                :style="{
                    fontSize: `${element.fontSize * canvasSettings.displayScale}px`,
                    fontFamily: element.fontFamily,
                    color: element.color,
                    textAlign: element.textAlign || 'left'
                }"
                ref="directEditingTextarea"
            ></textarea>
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
            class="w-full h-full relative"
        >
            <!-- Square or Circle shape (rendered using borderRadius) -->
            <div 
                v-if="!element.shapeType || element.shapeType === 'square' || element.shapeType === 'circle'"
                class="w-full h-full absolute inset-0"
                :style="{
                    backgroundColor: element.color || '#FFFFFF',
                    border: '2px solid ' + (element.borderColor || '#000000'),
                    borderRadius: element.borderRadius ? `${element.borderRadius * canvasSettings.displayScale}px` : '0'
                }"
            />
            
            <!-- Triangle shape (rendered using CSS border trick) -->
            <div 
                v-else-if="element.shapeType === 'triangle'"
                class="w-full h-full absolute inset-0 overflow-hidden"
            >
                <div
                    class="absolute"
                    :style="{
                        width: '0',
                        height: '0',
                        borderLeft: `${element.width * canvasSettings.displayScale / 2}px solid transparent`,
                        borderRight: `${element.width * canvasSettings.displayScale / 2}px solid transparent`,
                        borderBottom: `${element.height * canvasSettings.displayScale}px solid ${element.color || '#FFFFFF'}`,
                        top: '0',
                        left: '0'
                    }"
                />
            </div>
            
            <!-- Line shape (just a rectangle with minimal height) -->
            <div 
                v-else-if="element.shapeType === 'line'"
                class="absolute left-0 right-0 top-1/2 transform -translate-y-1/2"
                :style="{
                    height: `${element.height * canvasSettings.displayScale}px`,
                    backgroundColor: element.color || '#000000'
                }"
            />
        </div>

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

      <!-- Zoom, Pan, and Units Controls -->
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
                @click="togglePanning" 
                class="p-1.5 rounded-full hover:bg-gray-100"
                :class="{ 'bg-gray-200': isPanning }"
                title="Toggle Pan Mode"
            >
                <Hand class="size-5" />
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
            
            <div class="h-6 border-l border-gray-300 mx-1"></div>
            
            <button 
                @click="toggleUnitSystem" 
                class="p-1.5 rounded-full hover:bg-gray-100 text-xs font-medium"
                title="Toggle between inches and centimeters"
            >
                {{ useMetric ? 'cm' : 'in' }}
            </button>
        </div>
        <!-- Transformation handler -->
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