
<script setup>
import AuthenticatedLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { defineProps } from 'vue';

const form = useForm({
  title: '',
  entry: '',
});

const submit = () => {
  form.post(route('journals.store'));
};
</script>

<template>
  <Head title="Create Journal" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Journal</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg text-black">
          <div class="p-6 bg-white border-b border-gray-200">
            <form @submit.prevent="submit">
              <div>
                <label for="title" class="block font-medium text-sm text-gray-700">Title</label>
                <input type="text" v-model="form.title" id="title" class="block mt-1 w-full" required autofocus />
              </div>

              <div class="mt-4 text-black">
                <label for="entry" class="block font-medium text-sm text-gray-700">Entry</label>
                <textarea v-model="form.entry" id="entry" rows="10" class="block mt-1 w-full"></textarea>
              </div>

              <div class="flex items-center justify-end mt-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                  Create
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

