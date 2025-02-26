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
    }
});

const emit = defineEmits(['update:elements']);

const messages = ref([
    { id: 1, role: 'assistant', content: 'Hi! I\'m here to help you with your design. What would you like to create or modify?' }
]);

const newMessage = ref('');
const isProcessing = ref(false);

// Watch for changes in elements from parent component
watch(() => props.elements, (newElements) => {
    // This ensures that if elements are modified outside the chat,
    // the chat component is aware of the current state
}, { deep: true });

const sendMessage = async () => {
    if (!newMessage.value.trim() || isProcessing.value) return;
    
    // Add user message
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
        
        // Prepare data for AI service
        const requestData = {
            prompt: userRequest,
            currentElements: props.elements,
            productInfo: {
                name: props.product.name,
                width: props.product.finished_width,
                length: props.product.finished_length
            }
        };
        
        // Call the AI service
        const response = await axios.post('/api/design-ai/edit', requestData);
        
        // Remove the thinking message
        messages.value = messages.value.filter(m => m.id !== thinkingId);
        
        // Add AI response
        messages.value.push({
            id: Date.now() + 2,
            role: 'assistant',
            content: response.data.message
        });
        
        // Update elements if AI provided changes
        if (response.data.elements) {
            emit('update:elements', response.data.elements);
        }
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
    } finally {
        isProcessing.value = false;
    }
};

// Auto-scroll to bottom when messages change
const messagesContainer = ref(null);
watch(messages, () => {
    setTimeout(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    }, 100);
}, { deep: true });
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Messages area -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
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
                        {{ message.content }}
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
                    :disabled="isProcessing"
                />
                <button
                    @click="sendMessage"
                    class="p-2 rounded-lg bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="isProcessing || !newMessage.trim()"
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