<template>
  <div>
    <div class="card mb-4">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="card-title">Purchase Orders</h3>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <input v-model="term" type="search" class="form-control form-control-sm w-auto" placeholder="Search PO number or supplier…" aria-label="Search purchase orders" />
          <button type="button" class="btn btn-primary" @click="openAdd">New purchase order</button>
        </div>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped align-middle mb-0">
          <thead><tr><th>PO #</th><th>Supplier</th><th>Status</th><th>Date</th><th class="text-end">Items</th><th class="text-end">Total</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            <tr v-if="filtered.length === 0"><td colspan="7" class="text-center text-secondary py-4">{{ term.trim() ? 'No purchase orders match this search.' : 'No purchase orders found.' }}</td></tr>
            <tr v-for="po in filtered" :key="po.id">
              <td class="fw-semibold font-monospace">{{ po.po_number }}</td>
              <td>{{ po.supplier?.trading_name || po.supplier?.legal_name || '—' }}</td>
              <td><span class="badge" :class="statusClass(po.status)">{{ po.status }}</span></td>
              <td class="text-secondary">{{ (po.po_date || po.created_at || '').slice(0, 10) }}</td>
              <td class="text-end">{{ po.items_count ?? '—' }}</td>
              <td class="text-end fw-semibold">AED {{ money(po.total_amount) }}</td>
              <td class="text-end text-nowrap">
                <button v-if="po.status === 'draft'" type="button" class="btn btn-sm btn-outline-primary" @click="openEdit(po)">Edit</button>
                <button v-if="canReceive(po)" type="button" class="btn btn-sm btn-success ms-2" :disabled="saving" @click="receive(po)">Receive</button>
                <button v-if="po.status === 'draft'" type="button" class="btn btn-sm btn-danger ms-2" @click="remove(po)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade" id="purchaseOrderModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.editingId ? `Edit ${form.poNumber}` : 'New purchase order' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="save">
              <div class="row">
                <div class="col-md-5 mb-3">
                  <label class="form-label" for="poSupplier">Supplier *</label>
                  <select id="poSupplier" v-model="form.supplier_id" required class="form-select">
                    <option value="" disabled>Select a supplier</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.trading_name || s.legal_name }}</option>
                  </select>
                </div>
                <div class="col-md-3 mb-3">
                  <label class="form-label" for="poDate">Order date *</label>
                  <input id="poDate" v-model="form.po_date" type="date" required class="form-control" />
                </div>
                <div class="col-md-2 mb-3">
                  <label class="form-label" for="poDelivery">Expected</label>
                  <input id="poDelivery" v-model="form.expected_delivery" type="date" class="form-control" />
                </div>
                <div class="col-md-2 mb-3">
                  <label class="form-label" for="poCurrency">Currency</label>
                  <input id="poCurrency" v-model="form.currency" maxlength="3" class="form-control" />
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead><tr><th style="min-width:220px">Product</th><th style="width:90px">Qty</th><th style="width:120px">Unit cost</th><th style="width:110px">Discount</th><th style="width:110px">Tax</th><th class="text-end" style="width:120px">Line total</th><th></th></tr></thead>
                  <tbody>
                    <tr v-for="(row, index) in form.items" :key="index">
                      <td>
                        <select v-model="row.product_id" required class="form-select form-select-sm">
                          <option value="" disabled>Select product</option>
                          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                      </td>
                      <td><input v-model="row.quantity" type="number" min="1" step="1" required class="form-control form-control-sm" /></td>
                      <td><input v-model="row.unit_cost" type="number" min="0" step="0.01" required class="form-control form-control-sm" /></td>
                      <td><input v-model="row.discount" type="number" min="0" step="0.01" class="form-control form-control-sm" /></td>
                      <td><input v-model="row.tax" type="number" min="0" step="0.01" class="form-control form-control-sm" /></td>
                      <td class="text-end fw-semibold">{{ money(lineTotal(row)) }}</td>
                      <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" :disabled="form.items.length === 1" @click="form.items.splice(index, 1)">×</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary mb-3" @click="addRow">Add line</button>

              <div class="row">
                <div class="col-md-3 mb-3">
                  <label class="form-label" for="shippingCost">Shipping</label>
                  <input id="shippingCost" v-model="form.shipping_cost" type="number" min="0" step="0.01" class="form-control" />
                </div>
                <div class="col-md-3 mb-3">
                  <label class="form-label" for="customsCost">Customs</label>
                  <input id="customsCost" v-model="form.customs_cost" type="number" min="0" step="0.01" class="form-control" />
                </div>
                <div class="col-md-3 mb-3">
                  <label class="form-label" for="additionalCharges">Other charges</label>
                  <input id="additionalCharges" v-model="form.additional_charges" type="number" min="0" step="0.01" class="form-control" />
                </div>
                <div class="col-md-3 mb-3">
                  <label class="form-label">Order total</label>
                  <div class="form-control-plaintext fw-semibold">AED {{ money(orderTotal) }}</div>
                </div>
                <div class="col-12 mb-3">
                  <label class="form-label" for="poNotes">Notes</label>
                  <textarea id="poNotes" v-model="form.notes" rows="2" maxlength="2000" class="form-control"></textarea>
                </div>
              </div>

              <p class="text-secondary small mb-2">Receiving moves every line into stock and records shipping, customs and other charges as one Logistics expense.</p>
              <div v-if="form.error" class="alert alert-danger" role="alert">{{ form.error }}</div>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" :disabled="saving || form.items.length === 0">{{ saving ? 'Saving…' : (form.editingId ? 'Update' : 'Create') }}</button>
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
import { lineTotal, orderTotal as calculateOrderTotal } from './purchaseOrders.js';
import { useCollectionSearch } from './search.js';

const list = ref([]);
const products = ref([]);
const suppliers = ref([]);
const saving = ref(false);
const { term, filtered } = useCollectionSearch(list, useRoute(), [
  (po) => po.po_number,
  (po) => po.supplier?.trading_name,
  (po) => po.supplier?.legal_name,
]);

const today = () => {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  return `${now.getFullYear()}-${month}-${String(now.getDate()).padStart(2, '0')}`;
};

const emptyRow = () => ({ product_id: '', quantity: 1, unit_cost: 0, discount: 0, tax: 0 });

const blank = () => ({
  editingId: null,
  poNumber: '',
  supplier_id: '',
  po_date: today(),
  expected_delivery: '',
  currency: 'AED',
  shipping_cost: 0,
  customs_cost: 0,
  additional_charges: 0,
  notes: '',
  items: [emptyRow()],
  error: '',
});

const form = reactive(blank());

onMounted(load);

async function load() {
  try {
    const [orders, productList, supplierList] = await Promise.all([
      axios.get('/api/purchase-orders'),
      axios.get('/api/products'),
      axios.get('/api/suppliers'),
    ]);
    list.value = Array.isArray(orders.data) ? orders.data : [];
    products.value = Array.isArray(productList.data) ? productList.data : (productList.data?.data ?? []);
    suppliers.value = Array.isArray(supplierList.data) ? supplierList.data : [];
  } catch {
    list.value = [];
  }
}

function modal() {
  return window.bootstrap?.Modal?.getOrCreateInstance(document.getElementById('purchaseOrderModal')) ?? null;
}

function num(value) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

const orderTotal = computed(() => calculateOrderTotal(form.items, form));

function addRow() {
  form.items.push(emptyRow());
}

function openAdd() {
  Object.assign(form, blank());
  modal()?.show();
}

async function openEdit(po) {
  const { data } = await axios.get(`/api/purchase-orders/${po.id}`);

  Object.assign(form, blank(), {
    editingId: data.id,
    poNumber: data.po_number,
    supplier_id: data.supplier_id,
    po_date: (data.po_date || '').slice(0, 10),
    expected_delivery: (data.expected_delivery || '').slice(0, 10),
    currency: data.currency || 'AED',
    shipping_cost: num(data.shipping_cost),
    customs_cost: num(data.customs_cost),
    additional_charges: num(data.additional_charges),
    notes: data.notes || '',
    items: (data.items || []).map((item) => ({
      product_id: item.product_id,
      quantity: item.quantity,
      unit_cost: num(item.unit_cost),
      discount: num(item.discount),
      tax: num(item.tax),
    })),
  });

  if (form.items.length === 0) form.items = [emptyRow()];
  modal()?.show();
}

function payload() {
  return {
    supplier_id: form.supplier_id,
    po_date: form.po_date,
    expected_delivery: form.expected_delivery || null,
    currency: form.currency || 'AED',
    notes: form.notes,
    shipping_cost: num(form.shipping_cost),
    customs_cost: num(form.customs_cost),
    additional_charges: num(form.additional_charges),
    items: form.items.map((row) => ({
      product_id: row.product_id,
      quantity: num(row.quantity),
      unit_cost: num(row.unit_cost),
      discount: num(row.discount),
      tax: num(row.tax),
    })),
  };
}

async function save() {
  saving.value = true;
  form.error = '';
  try {
    if (form.editingId) {
      await axios.put(`/api/purchase-orders/${form.editingId}`, payload());
    } else {
      await axios.post('/api/purchase-orders', payload());
    }
    modal()?.hide();
    await load();
  } catch (err) {
    form.error = errorMessage(err);
  } finally {
    saving.value = false;
  }
}

function canReceive(po) {
  return po.status === 'draft' || po.status === 'approved';
}

async function receive(po) {
  const confirmed = await confirmAdmin({
    title: 'Receive purchase order?',
    text: `Receive ${po.po_number}? Stock will be increased and charges recorded as an expense.`,
    confirmButtonText: 'Receive',
  });

  if (!confirmed.isConfirmed) return;

  saving.value = true;
  try {
    await axios.post(`/api/purchase-orders/${po.id}/receive`);
    await load();
  } catch {
    // The API message is surfaced by the global alert interceptor.
  } finally {
    saving.value = false;
  }
}

async function remove(po) {
  const confirmed = await confirmAdmin({
    title: 'Delete purchase order?',
    text: `Delete ${po.po_number}? This cannot be undone.`,
    confirmButtonText: 'Delete',
    destructive: true,
  });

  if (!confirmed.isConfirmed) return;

  try {
    await axios.delete(`/api/purchase-orders/${po.id}`);
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

function money(value) {
  return Number(value ?? 0).toFixed(2);
}

const STATUS = {
  draft: 'text-bg-secondary',
  approved: 'text-bg-info',
  received: 'text-bg-success',
  cancelled: 'text-bg-danger',
};

function statusClass(status) {
  return STATUS[status] ?? 'text-bg-secondary';
}
</script>
