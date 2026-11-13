<template>
  <div>
    <div class="card mb-4">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="card-title">Categories</h3>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <select v-model="statusFilter" class="form-select form-select-sm w-auto" aria-label="Filter by status">
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <input v-model="term" type="search" class="form-control form-control-sm w-auto" placeholder="Search name or slug…" aria-label="Search categories" />
          <button type="button" class="btn btn-primary" @click="openAdd">Add category</button>
        </div>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped align-middle mb-0">
          <thead><tr><th>Name</th><th>Slug</th><th>Parent</th><th class="text-end">Products</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            <tr v-if="filtered.length === 0"><td colspan="6" class="text-center text-secondary py-4">{{ term.trim() || statusFilter !== 'all' ? 'No categories match this filter.' : 'No categories found.' }}</td></tr>
            <tr v-for="c in filtered" :key="c.id">
              <td class="fw-semibold">{{ c.name }}</td>
              <td class="text-secondary font-monospace small">{{ c.slug }}</td>
              <td class="text-secondary">{{ parentName(c.parent_id) || '—' }}</td>
              <td class="text-end">{{ c.products_count ?? 0 }}</td>
              <td><span class="badge" :class="c.is_active !== false ? 'text-bg-success' : 'text-bg-secondary'">{{ c.is_active !== false ? 'Active' : 'Inactive' }}</span></td>
              <td class="text-end text-nowrap">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="openEdit(c)">Edit</button>
                <button type="button" class="btn btn-sm btn-danger ms-2" @click="remove(c)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.editingId ? 'Edit category' : 'Add category' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="save">
              <div class="mb-3">
                <label class="form-label" for="categoryName">Name *</label>
                <input id="categoryName" v-model="form.name" required maxlength="255" class="form-control" />
              </div>
              <div class="mb-3">
                <label class="form-label" for="categorySlug">Slug</label>
                <input id="categorySlug" v-model="form.slug" maxlength="255" class="form-control" placeholder="auto-generated from name" />
              </div>
              <div class="mb-3">
                <label class="form-label" for="categoryParent">Parent category</label>
                <select id="categoryParent" v-model="form.parent_id" class="form-select">
                  <option value="">None</option>
                  <option v-for="c in parentOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label" for="categorySort">Sort order</label>
                <input id="categorySort" v-model="form.sort_order" type="number" min="0" step="1" class="form-control" />
              </div>
              <div class="mb-3">
                <label class="form-label" for="categoryDescription">Description</label>
                <textarea id="categoryDescription" v-model="form.description" rows="2" maxlength="2000" class="form-control"></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label" for="categoryImage">Image URL</label>
                <input id="categoryImage" v-model="form.image_url" maxlength="500" class="form-control" />
              </div>
              <div class="form-check mb-3">
                <input id="categoryActive" v-model="form.is_active" type="checkbox" class="form-check-input" />
                <label class="form-check-label" for="categoryActive">Active</label>
              </div>
              <div v-if="form.error" class="alert alert-danger" role="alert">{{ form.error }}</div>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving…' : (form.editingId ? 'Update' : 'Create') }}</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { confirmAdmin } from './adminAlerts';
import { useCollectionSearch } from './search.js';

const list = ref([]);
const saving = ref(false);
const statusFilter = ref('all');
const { term, filtered: searchFiltered } = useCollectionSearch(list, useRoute(), [
  (c) => c.name,
  (c) => c.slug,
]);

const filtered = computed(() => searchFiltered.value.filter((c) => {
  if (statusFilter.value === 'active') return c.is_active !== false;
  if (statusFilter.value === 'inactive') return c.is_active === false;
  return true;
}));

const blank = () => ({
  editingId: null,
  name: '',
  slug: '',
  description: '',
  parent_id: '',
  is_active: true,
  sort_order: 0,
  image_url: '',
  error: '',
});

const form = reactive(blank());

onMounted(load);

async function load() {
  try {
    const res = await axios.get('/api/categories');
    list.value = Array.isArray(res.data) ? res.data : [];
  } catch {
    list.value = [];
  }
}

function modal() {
  return window.bootstrap?.Modal?.getOrCreateInstance(document.getElementById('categoryModal')) ?? null;
}

function parentName(id) {
  return list.value.find((c) => c.id === id)?.name;
}

const parentOptions = computed(() => list.value.filter((c) => c.id !== form.editingId));

function openAdd() {
  Object.assign(form, blank());
  modal()?.show();
}

function openEdit(category) {
  Object.assign(form, blank(), {
    editingId: category.id,
    name: category.name || '',
    slug: category.slug || '',
    description: category.description || '',
    parent_id: category.parent_id ?? '',
    is_active: category.is_active !== false,
    sort_order: category.sort_order ?? 0,
    image_url: category.image_url || '',
  });
  modal()?.show();
}

function payload() {
  const { editingId, error, ...data } = form;
  return {
    ...data,
    parent_id: data.parent_id === '' ? null : data.parent_id,
    sort_order: Number(data.sort_order) || 0,
  };
}

async function save() {
  saving.value = true;
  form.error = '';
  try {
    if (form.editingId) {
      await axios.put(`/api/categories/${form.editingId}`, payload());
    } else {
      await axios.post('/api/categories', payload());
    }
    modal()?.hide();
    await load();
  } catch (err) {
    const data = err?.response?.data;
    form.error = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'Something went wrong. Please try again.');
  } finally {
    saving.value = false;
  }
}

async function remove(category) {
  const confirmed = await confirmAdmin({
    title: 'Delete category?',
    text: `Delete "${category.name}"? This cannot be undone.`,
    confirmButtonText: 'Delete category',
    destructive: true,
  });
  if (!confirmed.isConfirmed) return;
  try {
    await axios.delete(`/api/categories/${category.id}`);
    await load();
  } catch {
    // API message surfaced by global interceptor.
  }
}
</script>
