<template>
  <div>
    <div v-if="flash" class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle me-1"></i>{{ flash }}
      <button type="button" class="btn-close" @click="flash = ''" aria-label="Close"></button>
    </div>

    <div class="row">
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
          <div class="inner"><h3>{{ summary.total_units }}</h3><p>Units in Stock</p></div>
          <i class="small-box-icon bi bi-boxes"></i>
          <span class="small-box-footer link-light link-underline-opacity-0">Across {{ summary.total_products }} products</span>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
          <div class="inner"><h3>AED {{ money(summary.inventory_value) }}</h3><p>Inventory Value</p></div>
          <i class="small-box-icon bi bi-cash-stack"></i>
          <span class="small-box-footer link-light link-underline-opacity-0">At cost price</span>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
          <div class="inner"><h3>{{ summary.low_stock }}</h3><p>Low Stock</p></div>
          <i class="small-box-icon bi bi-exclamation-triangle"></i>
          <a href="#" class="small-box-footer link-dark link-underline-opacity-0" @click.prevent="filterStatus('low_stock')">Review items <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
          <div class="inner"><h3>{{ summary.out_of_stock }}</h3><p>Out of Stock</p></div>
          <i class="small-box-icon bi bi-x-octagon"></i>
          <a href="#" class="small-box-footer link-light link-underline-opacity-0" @click.prevent="filterStatus('out_of_stock')">Review items <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">Stock Levels</h3>
        <div class="card-tools d-flex flex-wrap gap-2">
          <select v-model="statusFilter" class="form-select form-select-sm" style="width: 160px" @change="load">
            <option value="">All statuses</option>
            <option value="in_stock">In stock</option>
            <option value="low_stock">Low stock</option>
            <option value="out_of_stock">Out of stock</option>
          </select>
          <div class="input-group input-group-sm" style="width: 230px">
            <input v-model="q" type="search" class="form-control" placeholder="Search name or SKU…" @input="queueSearch" />
            <button class="btn btn-default" type="button" @click="load"><i class="bi bi-search"></i></button>
          </div>
          <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="loading" @click="refreshAll" title="Refresh">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Product</th>
              <th>Category</th>
              <th class="text-end">Stock</th>
              <th class="text-end">Threshold</th>
              <th>Status</th>
              <th class="text-end">Stock Value</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="7" class="text-center text-secondary py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading inventory…</td></tr>
            <tr v-else-if="products.length === 0"><td colspan="7" class="text-center text-secondary py-4">No products match these filters.</td></tr>
            <tr v-for="p in products" :key="p.id">
              <td>
                <div class="fw-semibold">{{ p.name }}</div>
                <div class="text-secondary fs-7 font-monospace">{{ p.sku }}</div>
              </td>
              <td class="text-secondary">{{ p.category?.name || '—' }}</td>
              <td class="text-end"><span class="badge fs-6" :class="statusBadge(p.computed_status)">{{ p.stock_quantity }}</span></td>
              <td class="text-end text-secondary">{{ p.low_stock_threshold }}</td>
              <td><span class="badge" :class="statusBadge(p.computed_status)">{{ statusLabel(p.computed_status) }}</span></td>
              <td class="text-end">AED {{ money(p.inventory_value) }}</td>
              <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-primary" type="button" @click="openAdjust(p)"><i class="bi bi-sliders me-1"></i>Adjust</button>
                <button class="btn btn-sm btn-outline-secondary ms-1" type="button" title="Edit low-stock threshold" @click="openThreshold(p)"><i class="bi bi-gear"></i></button>
                <button class="btn btn-sm btn-outline-secondary ms-1" type="button" title="View history" @click="openHistory(p)"><i class="bi bi-clock-history"></i></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-secondary fs-7">Showing {{ products.length }} product(s)</div>
    </div>

    <div id="inventory-history" class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">Inventory History</h3>
        <div class="card-tools d-flex flex-wrap gap-2">
          <select v-model="historyProduct" class="form-select form-select-sm" style="width: 220px" @change="loadHistory">
            <option value="">All products</option>
            <option v-for="p in allProducts" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
          </select>
          <select v-model="historyType" class="form-select form-select-sm" style="width: 170px" @change="loadHistory">
            <option value="">All movements</option>
            <option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option>
          </select>
          <button class="btn btn-sm btn-outline-secondary" type="button" @click="clearHistoryFilters">Clear</button>
        </div>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Product</th>
              <th>Movement</th>
              <th class="text-end">Change</th>
              <th class="text-end">Before → After</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="historyLoading"><td colspan="6" class="text-center text-secondary py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading history…</td></tr>
            <tr v-else-if="history.length === 0"><td colspan="6" class="text-center text-secondary py-4">No inventory movements recorded yet.</td></tr>
            <tr v-for="t in history" :key="t.id">
              <td class="text-secondary text-nowrap fs-7">{{ formatDate(t.created_at) }}</td>
              <td>
                <div class="fw-semibold">{{ t.product?.name || 'Deleted product' }}</div>
                <div class="text-secondary fs-7 font-monospace">{{ t.product?.sku }}</div>
              </td>
              <td><span class="badge" :class="typeBadge(t.type)">{{ typeLabel(t.type) }}</span></td>
              <td class="text-end fw-semibold" :class="changeClass(t)">{{ changeText(t) }}</td>
              <td class="text-end text-secondary">{{ t.quantity_before }} → {{ t.quantity_after }}</td>
              <td class="text-secondary">{{ t.reason || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-secondary fs-7">{{ history.length }} movement record(s) · showing the latest 200</div>
    </div>

    <!-- Adjust stock modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="adjustStockModalLabel" class="modal-title">Adjust Stock</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form @submit.prevent="submitAdjust">
            <div class="modal-body">
              <div v-if="selected" class="p-2 mb-3 bg-body-tertiary rounded">
                <div class="fw-semibold">{{ selected.name }}</div>
                <div class="fs-7 text-secondary font-monospace">{{ selected.sku }} · Current stock: {{ selected.stock_quantity }}</div>
              </div>

              <div v-if="formError" class="alert alert-danger py-2 fs-7">{{ formError }}</div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="adjustType">Movement type</label>
                  <select id="adjustType" v-model="form.type" class="form-select" required>
                    <option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="adjustMode">Operation</label>
                  <select id="adjustMode" v-model="form.mode" class="form-select" required>
                    <option value="increase">Add stock (+)</option>
                    <option value="decrease">Remove stock (−)</option>
                    <option value="set">Set exact quantity</option>
                  </select>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="adjustQuantity">Quantity</label>
                  <input id="adjustQuantity" v-model.number="form.quantity" type="number" min="0" class="form-control" required />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="adjustThreshold">Low-stock threshold</label>
                  <input id="adjustThreshold" v-model.number="form.low_stock_threshold" type="number" min="0" class="form-control" />
                </div>
              </div>

              <div class="mb-2">
                <label class="form-label" for="adjustReason">Reason <span class="text-danger">*</span></label>
                <textarea id="adjustReason" v-model="form.reason" class="form-control" rows="2" maxlength="1000" required placeholder="e.g. Supplier delivery, damaged vials, stock count correction"></textarea>
              </div>

              <div class="fs-7 text-secondary">
                New stock level will be
                <strong :class="previewInvalid ? 'text-danger' : ''">{{ previewStock }}</strong>.
                <span v-if="previewInvalid" class="text-danger">Not enough stock on hand for this movement.</span>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>{{ saving ? 'Saving…' : 'Save adjustment' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Threshold modal -->
    <div class="modal fade" id="thresholdModal" tabindex="-1" aria-labelledby="thresholdModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="thresholdModalLabel" class="modal-title">Low-stock Threshold</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form @submit.prevent="submitThreshold">
            <div class="modal-body">
              <div v-if="selected" class="fs-7 text-secondary mb-3">{{ selected.name }} · current stock {{ selected.stock_quantity }}</div>
              <div v-if="formError" class="alert alert-danger py-2 fs-7">{{ formError }}</div>
              <label class="form-label" for="thresholdValue">Alert when stock is at or below</label>
              <input id="thresholdValue" v-model.number="thresholdValue" type="number" min="0" class="form-control" required />
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const products = ref([]);
const allProducts = ref([]);
const summary = ref({ total_units: 0, total_products: 0, low_stock: 0, out_of_stock: 0, inventory_value: 0 });
const history = ref([]);
const loading = ref(false);
const historyLoading = ref(false);
const saving = ref(false);
const flash = ref('');
const formError = ref('');
const q = ref('');
const statusFilter = ref('');
const historyType = ref('');
const historyProduct = ref('');
const selected = ref(null);
const thresholdValue = ref(0);

const typeLabels = {
  restock: 'Restock / delivery',
  adjustment: 'Manual adjustment',
  correction: 'Stock count correction',
  damage: 'Damaged / expired',
  return: 'Customer return',
  sale: 'Sale / order',
  transfer: 'Transfer',
  other: 'Other',
};

const form = ref({ mode: 'increase', quantity: 0, type: 'restock', reason: '', low_stock_threshold: 0 });

const previewStock = computed(() => {
  if (!selected.value) return 0;
  const current = Number(selected.value.stock_quantity) || 0;
  const qty = Number(form.value.quantity) || 0;
  if (form.value.mode === 'increase') return current + qty;
  if (form.value.mode === 'decrease') return current - qty;
  return qty;
});

const previewInvalid = computed(() => previewStock.value < 0);

let adjustModal = null;
let thresholdModal = null;
let searchTimer = null;

function money(value) {
  return Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function statusLabel(status) {
  return { in_stock: 'In stock', low_stock: 'Low stock', out_of_stock: 'Out of stock' }[status] || status;
}

function statusBadge(status) {
  return {
    in_stock: 'text-bg-success',
    low_stock: 'text-bg-warning',
    out_of_stock: 'text-bg-danger',
  }[status] || 'text-bg-secondary';
}

function typeLabel(type) {
  return typeLabels[type] || type;
}

function typeBadge(type) {
  return {
    restock: 'text-bg-success',
    adjustment: 'text-bg-info',
    correction: 'text-bg-secondary',
    damage: 'text-bg-danger',
    return: 'text-bg-primary',
    sale: 'text-bg-dark',
    transfer: 'text-bg-warning',
    other: 'text-bg-secondary',
  }[type] || 'text-bg-secondary';
}

function changeText(t) {
  const change = Number(t.quantity_after) - Number(t.quantity_before);
  return change > 0 ? '+' + change : String(change);
}

function changeClass(t) {
  const change = Number(t.quantity_after) - Number(t.quantity_before);
  return change > 0 ? 'text-success' : change < 0 ? 'text-danger' : 'text-secondary';
}

function queueSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(load, 300);
}

function filterStatus(status) {
  statusFilter.value = status;
  load();
}

async function load() {
  loading.value = true;
  try {
    const r = await axios.get('/api/inventory', {
      params: { q: q.value || undefined, status: statusFilter.value || undefined },
    });
    products.value = r.data.products || [];
    summary.value = r.data.summary || summary.value;
    if (!q.value && !statusFilter.value) allProducts.value = r.data.products || [];
  } catch (e) {
    flash.value = '';
    formError.value = '';
  } finally {
    loading.value = false;
  }
}

async function loadHistory() {
  historyLoading.value = true;
  try {
    const r = await axios.get('/api/inventory/history', {
      params: {
        type: historyType.value || undefined,
        product_id: historyProduct.value || undefined,
      },
    });
    history.value = r.data || [];
  } catch (e) {
    history.value = [];
  } finally {
    historyLoading.value = false;
  }
}

function refreshAll() {
  load();
  loadHistory();
}

function openAdjust(product) {
  selected.value = product;
  formError.value = '';
  form.value = {
    mode: 'increase',
    quantity: 0,
    type: 'restock',
    reason: '',
    low_stock_threshold: product.low_stock_threshold,
  };
  adjustModal = window.bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustStockModal'));
  adjustModal.show();
}

function openThreshold(product) {
  selected.value = product;
  formError.value = '';
  thresholdValue.value = product.low_stock_threshold;
  thresholdModal = window.bootstrap.Modal.getOrCreateInstance(document.getElementById('thresholdModal'));
  thresholdModal.show();
}

function openHistory(product) {
  historyProduct.value = String(product.id);
  loadHistory();
  document.getElementById('inventory-history')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearHistoryFilters() {
  historyProduct.value = '';
  historyType.value = '';
  loadHistory();
}

async function submitAdjust() {
  if (!selected.value) return;
  saving.value = true;
  formError.value = '';
  try {
    const r = await axios.post('/api/inventory/adjust', {
      product_id: selected.value.id,
      mode: form.value.mode,
      quantity: form.value.quantity,
      type: form.value.type,
      reason: form.value.reason,
      low_stock_threshold: form.value.low_stock_threshold,
    });
    flash.value = r.data.message || 'Stock adjusted successfully.';
    adjustModal?.hide();
    refreshAll();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Could not save this adjustment.';
  } finally {
    saving.value = false;
  }
}

async function submitThreshold() {
  if (!selected.value) return;
  saving.value = true;
  formError.value = '';
  try {
    const r = await axios.put(`/api/inventory/products/${selected.value.id}/threshold`, {
      low_stock_threshold: thresholdValue.value,
    });
    flash.value = r.data.message || 'Threshold updated.';
    thresholdModal?.hide();
    refreshAll();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Could not update the threshold.';
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  load();
  loadHistory();
});
</script>
