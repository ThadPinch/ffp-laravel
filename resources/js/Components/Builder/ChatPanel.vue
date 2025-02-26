<script setup>
import { ref } from 'vue';
import { Send } from 'lucide-vue-next';

const messages = ref([
    { id: 1, role: 'assistant', content: 'Hi! I\'m here to help you with your design. What would you like to create?' }
]);

const newMessage = ref('');

const sendMessage = () => {
    if (!newMessage.value.trim()) return;
    
    messages.value.push({
        id: Date.now(),
        role: 'user',
        content: newMessage.value
    });
    
    newMessage.value = '';
    // Here you would typically trigger the AI response
};
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Messages area -->
        <div class="flex-1 overflow-y-auto p-4 space-y-4">
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
                        'bg-gray-100 text-gray-900': message.role === 'assistant'
                    }"
                >
                    {{ message.content }}
                </div>
            </div>
        </div>

        <!-- Input area -->
        <div class="border-t border-gray-200 p-4">
            <div class="flex space-x-2">
                <input
                    v-model="newMessage"
                    type="text"
                    placeholder="Ask me anything about your design..."
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @keyup.enter="sendMessage"
                />
                <button
                    @click="sendMessage"
                    class="p-2 rounded-lg bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <Send class="size-5" />
                </button>
            </div>
        </div>
    </div>
</template>