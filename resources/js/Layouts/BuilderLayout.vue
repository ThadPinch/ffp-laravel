<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import { XCircle } from 'lucide-vue-next';

defineProps({
    title: String,
});
</script>

<template>
    <div class="min-h-screen bg-white">
        <Head :title="title" />

        <!-- Minimal Header -->
        <div class="fixed top-0 left-0 right-0 bg-white border-b border-gray-100 z-50">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <Link :href="route('dashboard')" class="hover:opacity-75 transition-opacity">
                            <ApplicationMark class="block h-14 w-auto" />
                        </Link>
                    </div>

                    <!-- Title (if needed) -->
                    <div v-if="title" class="hidden sm:block text-xl font-semibold text-gray-800">
                        {{ title }}
                    </div>

                    <!-- Close Button -->
                    <Link 
                        :href="route('dashboard')" 
                        class="flex items-center text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        <span class="mr-2 hidden sm:inline">Close</span>
                        <XCircle class="size-6" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="pt-16">
            <slot />
        </main>
    </div>
</template>