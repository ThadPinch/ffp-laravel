<script setup>
import { ref, onMounted, watch } from 'vue';
import { Send } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    elements: {
        type: Array,
        required: true
    },
    product: {
        type: Object,
        required: true
    },
    designId: {
        type: [Number, String],
        required: true
    }
});

const emit = defineEmits(['update:elements']);

const messages = ref([]);
const newMessage = ref('');
const isProcessing = ref(false);
const messagesLoaded = ref(false);

// Process message content to ensure it's properly formatted
const processMessageContent = (message) => {
    if (!message) return '';
    
    // If content is already a string, return it
    if (typeof message.content === 'string') {
        try {
            // Check if the string is JSON
            const parsedContent = JSON.parse(message.content);
            // If it has a message property, use that
            if (parsedContent && parsedContent.message) {
                return parsedContent.message;
            }
        } catch (e) {
            // Not JSON, just return the original content
            return message.content;
        }
    }
    
    // Return original content as fallback
    return message.content;
};

// Fetch existing messages on component mount
onMounted(async () => {
    try {
        const response = await axios.get(`/api/designs/${props.designId}/chat`);
        // Process each message to ensure proper content formatting
        messages.value = response.data.map(message => ({
            ...message,
            content: processMessageContent(message)
        }));
        messagesLoaded.value = true;
        
        // If no messages yet, add a welcome message
        if (messages.value.length === 0) {
            messages.value.push({
                id: Date.now(),
                role: 'assistant',
                content: 'Hi! I\'m here to help you with your design. What would you like to create or modify?'
            });
        }
        
        scrollToBottom();
    } catch (error) {
        console.error('Error fetching chat messages:', error);
        messages.value = [{
            id: Date.now(),
            role: 'assistant',
            content: 'Hi! I\'m here to help you with your design. What would you like to create or modify?'
        }];
        messagesLoaded.value = true;
    }
});

// Watch for changes in elements from parent component
watch(() => props.elements, (newElements) => {
    // This ensures that if elements are modified outside the chat,
    // the chat component is aware of the current state
}, { deep: true });

// Watch for changes in the design ID
watch(() => props.designId, async (newDesignId, oldDesignId) => {
    if (newDesignId !== oldDesignId) {
        messagesLoaded.value = false;
        try {
            const response = await axios.get(`/api/designs/${newDesignId}/chat`);
            // Process each message to ensure proper content formatting
            messages.value = response.data.map(message => ({
                ...message,
                content: processMessageContent(message)
            }));
            messagesLoaded.value = true;
            
            // If no messages yet, add a welcome message
            if (messages.value.length === 0) {
                messages.value.push({
                    id: Date.now(),
                    role: 'assistant',
                    content: 'Hi! I\'m here to help you with your design. What would you like to create or modify?'
                });
            }
            
            scrollToBottom();
        } catch (error) {
            console.error('Error fetching chat messages for new design:', error);
            messages.value = [{
                id: Date.now(),
                role: 'assistant',
                content: 'Hi! I\'m here to help you with your design. What would you like to create or modify?'
            }];
            messagesLoaded.value = true;
        }
    }
}, { immediate: false });

const sendMessage = async () => {
    if (!newMessage.value.trim() || isProcessing.value) return;
    
    // Add user message to the UI
    const userMessage = {
        id: Date.now(),
        role: 'user',
        content: newMessage.value
    };
    messages.value.push(userMessage);
    
    // Clear input and show processing state
    const userRequest = newMessage.value;
    newMessage.value = '';
    isProcessing.value = true;
    
    try {
        // Add a temporary "thinking" message
        const thinkingId = Date.now() + 1;
        messages.value.push({
            id: thinkingId,
            role: 'assistant',
            content: 'Thinking...',
            isThinking: true
        });
        
        scrollToBottom();
        
        // Send the message to our backend
        const response = await axios.post(`/api/designs/${props.designId}/chat`, {
            message: userRequest
        });
        
        // Remove the thinking message
        messages.value = messages.value.filter(m => m.id !== thinkingId);
        
        // Handle JSON response from AI
        let messageContent, designElements;

        try {
            // Parse the content as JSON
            const parsedContent = JSON.parse(response.data.message.content);
            messageContent = parsedContent.message;
            designElements = parsedContent.design;
        } catch (e) {
            // If parsing fails, use the original message
            console.error('Failed to parse AI response as JSON:', e);
            messageContent = response.data.message.content;
            designElements = response.data.elements;
        }
        
        // Add AI response with the message portion
        messages.value.push({
            id: response.data.message.id,
            role: 'assistant',
            content: messageContent,
            created_at: response.data.message.created_at
        });
        
        // Update elements if design data is provided
        if (designElements && Array.isArray(designElements) && designElements.length > 0) {
            emit('update:elements', designElements);
        }
        
        scrollToBottom();
    } catch (error) {
        console.error('Error processing AI request:', error);
        
        // Remove the thinking message
        messages.value = messages.value.filter(m => !m.isThinking);
        
        // Add error message
        messages.value.push({
            id: Date.now() + 2,
            role: 'assistant',
            content: 'Sorry, I encountered an error while processing your request. Please try again.'
        });
        
        scrollToBottom();
    } finally {
        isProcessing.value = false;
    }
};

// Auto-scroll to bottom
const messagesContainer = ref(null);
const scrollToBottom = () => {
    setTimeout(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    }, 100);
};

// Format timestamp
const formatTime = (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Messages area with loading state -->
        <div v-if="!messagesLoaded" class="flex-1 flex items-center justify-center">
            <div class="text-gray-500">Loading messages...</div>
        </div>
        
        <div v-else ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
            <div
                v-for="message in messages"
                :key="message.id"
                class="flex"
                :class="{ 'justify-end': message.role === 'user' }"
            >
                <div
                    class="max-w-[80%] rounded-lg px-4 py-2"
                    :class="{
                        'bg-blue-500 text-white': message.role === 'user',
                        'bg-gray-100 text-gray-900': message.role === 'assistant' && !message.isThinking,
                        'bg-gray-50 text-gray-500 italic': message.isThinking
                    }"
                >
                    <div v-if="message.isThinking" class="flex items-center">
                        <span>{{ message.content }}</span>
                        <span class="ml-2 flex">
                            <span class="animate-bounce mx-0.5 delay-0">.</span>
                            <span class="animate-bounce mx-0.5 delay-100">.</span>
                            <span class="animate-bounce mx-0.5 delay-200">.</span>
                        </span>
                    </div>
                    <div v-else>
                        <div class="text-sm">{{ message.content }}</div>
                        <div v-if="message.created_at" class="text-xs opacity-75 mt-1">
                            {{ formatTime(message.created_at) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input area -->
        <div class="border-t border-gray-200 p-4">
            <div class="flex space-x-2">
                <input
                    v-model="newMessage"
                    type="text"
                    placeholder="Ask me to edit your design..."
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @keyup.enter="sendMessage"
                    :disabled="isProcessing || !messagesLoaded"
                />
                <button
                    @click="sendMessage"
                    class="p-2 rounded-lg bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="isProcessing || !newMessage.trim() || !messagesLoaded"
                >
                    <Send class="size-5" />
                </button>
            </div>
            <div v-if="isProcessing" class="text-xs text-gray-500 mt-2">
                Processing your request...
            </div>
            <div class="text-xs text-gray-500 mt-2">
                Try asking: "Add a title at the top", "Change the color of the text to blue", or "Make the image larger"
            </div>
        </div>
    </div>
</template>