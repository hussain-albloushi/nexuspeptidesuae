<template>
  <div>
    <div class="card mb-4">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="card-title">Coupons</h3>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <input v-model="term" type="search" class="form-control form-control-sm w-auto" placeholder="Search code…" aria-label="Search coupons" />
          <button type="button" class="btn btn-primary" @click="resetAddForm">Add coupon</button>
        </div>
      </div>

      <div class="card-body p-0">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Type</th>
              <th class="text-end">Value</th>
              <th class="text-end">Used / limit</th>
              <th class="text-end">Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filtered.length === 0">
              <td colspan="6" class="text-center text-secondary py-4">{{ term.trim() ? 'No coupons match this search.' : 'No coupons found.' }}</td>
            </tr>
            <tr v-for="c in filtered" :key="c.id">
              <td class="fw-semibold font-monospace">{{ c.code }}</td>
              <td>{{ typeLabel(c.type) }}</td>
              <td class="text-end">{{ formatMoney(c.value ?? 0) }}</td>
              <td class="text-end text-secondary">
                {{ c.usage_count }}<span v-if="c.usage_limit"> / {{ c.usage_limit }}</span>
              </td>
              <td>
                <span class="badge text-bg-success" v-if="c.is_active !== false">Active</span>
                <span class="badge text-bg-secondary" v-else>Inactive</span>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="prepareEdit(c)">
                  Edit
                </button>
                <button type="button" class="btn btn-sm btn-danger ml-2" @click="confirmRemove(c)">
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add coupon -->
    <div class="modal fade" id="addCouponModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add coupon</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveAdd">
              <div class="mb-3">
                <label class="form-label" for="addCode">Code *</label>
                <input id="addCode" v-model="addForm.code" required class="form-control"
                       autocomplete="off" placeholder="WELCOME10">
              </div>
              <div class="mb-3">
                <label class="form-label" for="addType">Type *</label>
                <select id="addType" v-model="addForm.type" class="form-select">
                  <option value="percentage">Percentage off</option>
                  <option value="fixed">Fixed amount off</option>
                </select>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="addValue">Value *{{ addForm.type === 'percentage' ? ' (%)' : ' (AED)' }}</label>
                  <input id="addValue" v-model="addForm.value" type="number" required min="0" step="0.01"
                         class="form-control" placeholder="0">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="addUsage">Usage limit</label>
                  <input id="addUsage" v-model="addForm.usageLimit" type="number" min="1" step="1"
                         class="form-control" placeholder="Unlimited">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="addMin">Minimum order (AED)</label>
                  <input id="addMin" v-model="addForm.minOrderAmount" type="number" min="0" step="0.01"
                         class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="addMax">Max discount (AED)</label>
                  <input id="addMax" v-model="addForm.maxDiscount" type="number" min="0" step="0.01"
                         class="form-control" placeholder="0.00">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="addStarts">Valid from</label>
                  <input id="addStarts" v-model="addForm.startsAt" type="date" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="addExpires">Valid until</label>
                  <input id="addExpires" v-model="addForm.expiresAt" type="date" class="form-control">
                </div>
              </div>
              <div class="form-check mb-3">
                <input id="addActive" v-model="addForm.isActive" type="checkbox" class="form-check-input">
                <label class="form-check-label" for="addActive">Active</label>
              </div>
              <div v-if="addForm.error" class="alert alert-danger" role="alert">{{ addForm.error }}</div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Create' }}</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit coupon -->
    <div class="modal fade" id="editCouponModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit coupon</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveEdit">
              <div class="mb-3">
                <label class="form-label" for="editCode">Code *</label>
                <input id="editCode" v-model="editForm.code" required class="form-control" autocomplete="off">
              </div>
              <div class="mb-3">
                <label class="form-label" for="editType">Type *</label>
                <select id="editType" v-model="editForm.type" class="form-select">
                  <option value="percentage">Percentage off</option>
                  <option value="fixed">Fixed amount off</option>
                </select>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="editValue">Value *{{ editForm.type === 'percentage' ? ' (%)' : ' (AED)' }}</label>
                  <input id="editValue" v-model="editForm.value" type="number" required min="0" step="0.01"
                         class="form-control" placeholder="0">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="editUsage">Usage limit</label>
                  <input id="editUsage" v-model="editForm.usageLimit" type="number" min="1" step="1"
                         class="form-control" placeholder="Unlimited">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="editMin">Minimum order (AED)</label>
                  <input id="editMin" v-model="editForm.minOrderAmount" type="number" min="0" step="0.01"
                         class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="editMax">Max discount (AED)</label>
                  <input id="editMax" v-model="editForm.maxDiscount" type="number" min="0" step="0.01"
                         class="form-control" placeholder="0.00">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="editStarts">Valid from</label>
                  <input id="editStarts" v-model="editForm.startsAt" type="date" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="editExpires">Valid until</label>
                  <input id="editExpires" v-model="editForm.expiresAt" type="date" class="form-control">
                </div>
              </div>
              <div class="form-check mb-3">
                <input id="editActive" v-model="editForm.isActive" type="checkbox" class="form-check-input">
                <label class="form-check-label" for="editActive">Active</label>
              </div>
              <div v-if="editForm.error" class="alert alert-danger" role="alert">{{ editForm.error }}</div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Update' }}</button>
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
import { confirmAdmin } from './adminAlerts';
import { ref, reactive, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useCollectionSearch } from './search.js';

const list = ref([]);
const saving = ref(false);
const { term, filtered } = useCollectionSearch(list, useRoute(), [(c) => c.code]);

const addForm = reactive({
  code: '',
  type: 'percentage',
  value: '',
  minOrderAmount: '',
  maxDiscount: '',
  usageLimit: '',
  startsAt: '',
  expiresAt: '',
  isActive: true,
  error: '',
});

const editForm = reactive({
  id: null,
  code: '',
  type: 'percentage',
  value: '',
  minOrderAmount: '',
  maxDiscount: '',
  usageLimit: '',
  startsAt: '',
  expiresAt: '',
  isActive: true,
  error: '',
});

onMounted(load);

async function load() {
  try {
    const res = await axios.get('/api/coupons');
    list.value = Array.isArray(res.data) ? res.data : [];
  } catch {
    list.value = [];
  }
}

function modal(id) {
  return window.bootstrap?.Modal?.getOrCreateInstance(document.getElementById(id)) ?? null;
}

function numOrNull(v) {
  if (v === '' || v === null || v === undefined) return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

function payloadOf(form) {
  return {
    code: String(form.code).trim().toUpperCase(),
    type: form.type,
    value: numOrNull(form.value) ?? 0,
    usage_limit: form.usageLimit ? Number(form.usageLimit) : null,
    min_order_amount: numOrNull(form.minOrderAmount),
    max_discount: numOrNull(form.maxDiscount),
    starts_at: form.startsAt ? `${form.startsAt} 00:00:00` : null,
    expires_at: form.expiresAt ? `${form.expiresAt} 23:59:59` : null,
    is_active: Boolean(form.isActive),
  };
}

function errorMessage(err) {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) return Object.values(data.errors).flat().join(' ');
  return 'Something went wrong. Please try again.';
}

async function saveAdd() {
  saving.value = true;
  addForm.error = '';
  try {
    await axios.post('/api/coupons', payloadOf(addForm));
    modal('addCouponModal')?.hide();
    await load();
  } catch (err) {
    addForm.error = errorMessage(err);
  } finally {
    saving.value = false;
  }
}

async function saveEdit() {
  saving.value = true;
  editForm.error = '';
  try {
    await axios.put(`/api/coupons/${editForm.id}`, payloadOf(editForm));
    modal('editCouponModal')?.hide();
    await load();
  } catch (err) {
    editForm.error = errorMessage(err);
  } finally {
    saving.value = false;
  }
}

function resetAddForm() {
  addForm.code = '';
  addForm.type = 'percentage';
  addForm.value = '';
  addForm.minOrderAmount = '';
  addForm.maxDiscount = '';
  addForm.usageLimit = '';
  addForm.startsAt = '';
  addForm.expiresAt = '';
  addForm.isActive = true;
  addForm.error = '';
  modal('addCouponModal')?.show();
}

function prepareEdit(coupon) {
  editForm.id = coupon.id;
  editForm.code = coupon.code;
  editForm.type = coupon.type ?? 'percentage';
  editForm.value = coupon.value != null ? String(coupon.value) : '';
  editForm.minOrderAmount = coupon.min_order_amount != null ? String(coupon.min_order_amount) : '';
  editForm.maxDiscount = coupon.max_discount != null ? String(coupon.max_discount) : '';
  editForm.usageLimit = coupon.usage_limit != null ? String(coupon.usage_limit) : '';
  editForm.startsAt = coupon.starts_at ? String(coupon.starts_at).slice(0, 10) : '';
  editForm.expiresAt = coupon.expires_at ? String(coupon.expires_at).slice(0, 10) : '';
  editForm.isActive = coupon.is_active !== false;
  editForm.error = '';
  modal('editCouponModal')?.show();
}

async function removeCoupon(coupon) {
  if (!(await confirmAdmin({ title: 'Delete coupon?', text: `Delete coupon "${coupon.code}"? This cannot be undone.`, confirmButtonText: 'Delete coupon', destructive: true })).isConfirmed) {
    return;
  }

  saving.value = true;
  try {
    await axios.delete(`/api/coupons/${coupon.id}`);
    await load();
  } catch (err) {
    // Surface nothing destructive; leave UI unchanged.
  } finally {
    saving.value = false;
  }
}

function confirmRemove(coupon) {
  removeCoupon(coupon);
}

function typeLabel(type) {
  return type === 'fixed' ? 'Fixed amount' : 'Percentage';
}

function formatMoney(value) {
  return Number(value ?? 0).toFixed(2) + ' AED';
}
</script>
