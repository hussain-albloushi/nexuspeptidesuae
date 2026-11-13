<template>
  <div>
    <div class="card mb-4">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="card-title">Suppliers</h3>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <input v-model="term" type="search" class="form-control form-control-sm w-auto" placeholder="Search name, email, or phone…" aria-label="Search suppliers" />
          <button type="button" class="btn btn-primary" @click="openAdd">Add supplier</button>
        </div>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped align-middle mb-0">
          <thead><tr><th>Supplier</th><th>Contact</th><th>Status</th><th>Products</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            <tr v-if="filtered.length === 0"><td colspan="5" class="text-center text-secondary py-4">{{ term.trim() ? 'No suppliers match this search.' : 'No suppliers found.' }}</td></tr>
            <tr v-for="s in filtered" :key="s.id">
              <td>
                <div class="fw-semibold">{{ s.trading_name || s.legal_name }}</div>
                <div class="text-secondary small font-monospace">{{ s.supplier_code }}</div>
              </td>
              <td class="text-secondary">
                <div>{{ s.email }}</div>
                <div class="small">{{ s.phone || '—' }}</div>
              </td>
              <td><span class="badge" :class="statusClass(s.status)">{{ statusLabel(s.status) }}</span></td>
              <td>{{ s.products_count ?? 0 }}</td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="openEdit(s)">Edit</button>
                <button type="button" class="btn btn-sm btn-danger ms-2" @click="remove(s)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.editingId ? 'Edit supplier' : 'Add supplier' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="save">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="legalName">Legal name *</label>
                  <input id="legalName" v-model="form.legal_name" required maxlength="255" class="form-control" />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="tradingName">Trading name</label>
                  <input id="tradingName" v-model="form.trading_name" maxlength="255" class="form-control" />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="contactPerson">Contact person *</label>
                  <input id="contactPerson" v-model="form.contact_person" required maxlength="255" class="form-control" />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="email">Email *</label>
                  <input id="email" v-model="form.email" type="email" required maxlength="255" class="form-control" />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="phone">Phone</label>
                  <input id="phone" v-model="form.phone" maxlength="50" class="form-control" />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="city">City *</label>
                  <input id="city" v-model="form.city" required maxlength="100" class="form-control" />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="country">Country</label>
                  <input id="country" v-model="form.country" maxlength="100" class="form-control" />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="paymentTerms">Payment terms</label>
                  <input id="paymentTerms" v-model="form.payment_terms" maxlength="100" list="paymentTermsOptions" class="form-control" placeholder="Amount paid after 30 days" />
                  <datalist id="paymentTermsOptions">
                    <option value="Paid in advance"></option>
                    <option value="Paid on delivery"></option>
                    <option value="Paid after 30 days"></option>
                    <option value="Paid after 60 days"></option>
                  </datalist>
                  <div class="form-text">When you pay this supplier's invoices. Optional — pick one or type your own.</div>
                </div>
                <div class="col-12 mb-3">
                  <label class="form-label" for="address">Address *</label>
                  <input id="address" v-model="form.address" required maxlength="500" class="form-control" />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="status">Status</label>
                  <select id="status" v-model="form.status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="on_hold">On hold</option>
                    <option value="pending_review">Pending review</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="taxReg">Tax registration number</label>
                  <input id="taxReg" v-model="form.tax_reg_number" maxlength="100" class="form-control" />
                </div>
                <div class="col-12 mb-3">
                  <label class="form-label" for="notes">Notes</label>
                  <textarea id="notes" v-model="form.notes" rows="2" maxlength="2000" class="form-control"></textarea>
                </div>
              </div>
              <div v-if="form.error" class="alert alert-danger" role="alert">{{ form.error }}</div>
              <div class="d-flex gap-2 mt-2">
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
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { confirmAdmin } from './adminAlerts';
import { useCollectionSearch } from './search.js';

const list = ref([]);
const saving = ref(false);
const { term, filtered } = useCollectionSearch(list, useRoute(), [
  (s) => s.trading_name,
  (s) => s.legal_name,
  (s) => s.contact_person,
  (s) => s.email,
  (s) => s.phone,
]);

const blank = () => ({
  editingId: null,
  legal_name: '',
  trading_name: '',
  contact_person: '',
  email: '',
  phone: '',
  city: '',
  country: 'UAE',
  address: '',
  payment_terms: '',
  tax_reg_number: '',
  status: 'active',
  notes: '',
  error: '',
});

const form = reactive(blank());

onMounted(load);

async function load() {
  try {
    const res = await axios.get('/api/suppliers');
    list.value = Array.isArray(res.data) ? res.data : [];
  } catch {
    list.value = [];
  }
}

function modal() {
  return window.bootstrap?.Modal?.getOrCreateInstance(document.getElementById('supplierModal')) ?? null;
}

function openAdd() {
  Object.assign(form, blank());
  modal()?.show();
}

function openEdit(supplier) {
  Object.assign(form, blank(), {
    editingId: supplier.id,
    legal_name: supplier.legal_name || '',
    trading_name: supplier.trading_name || '',
    contact_person: supplier.contact_person || '',
    email: supplier.email || '',
    phone: supplier.phone || '',
    city: supplier.city || '',
    country: supplier.country || 'UAE',
    address: supplier.address || '',
    payment_terms: supplier.payment_terms || '',
    tax_reg_number: supplier.tax_reg_number || '',
    status: supplier.status || 'active',
    notes: supplier.notes || '',
  });
  modal()?.show();
}

function payload() {
  const { editingId, error, ...data } = form;
  return data;
}

async function save() {
  saving.value = true;
  form.error = '';
  try {
    if (form.editingId) {
      await axios.put(`/api/suppliers/${form.editingId}`, payload());
    } else {
      await axios.post('/api/suppliers', payload());
    }
    modal()?.hide();
    await load();
  } catch (err) {
    form.error = errorMessage(err);
  } finally {
    saving.value = false;
  }
}

async function remove(supplier) {
  const confirmed = await confirmAdmin({
    title: 'Delete supplier?',
    text: `Delete "${supplier.trading_name || supplier.legal_name}"? This cannot be undone.`,
    confirmButtonText: 'Delete supplier',
    destructive: true,
  });

  if (!confirmed.isConfirmed) return;

  try {
    await axios.delete(`/api/suppliers/${supplier.id}`);
    await load();
  } catch {
    // The API message is surfaced by the global alert interceptor.
  }
}

function errorMessage(err) {
  const data = err?.response?.data;
  if (data?.errors) return Object.values(data.errors).flat().join(' ');
  return data?.message || 'Something went wrong. Please try again.';
}

const STATUS = {
  active: ['Active', 'text-bg-success'],
  inactive: ['Inactive', 'text-bg-secondary'],
  on_hold: ['On hold', 'text-bg-warning'],
  pending_review: ['Pending review', 'text-bg-info'],
};

function statusLabel(status) {
  return STATUS[status]?.[0] ?? status ?? 'Active';
}

function statusClass(status) {
  return STATUS[status]?.[1] ?? 'text-bg-success';
}
</script>
