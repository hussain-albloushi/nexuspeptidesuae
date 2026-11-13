<template>
  <div class="row">
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-primary"><div class="inner"><h3>AED {{ summary.sales_period ?? '0.00' }}</h3><p>Period Sales</p></div><i class="small-box-icon bi bi-cash-stack"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-info"><div class="inner"><h3>AED {{ summary.co_gs ?? '0.00' }}</h3><p>COGS</p></div><i class="small-box-icon bi bi-box-seam"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-success"><div class="inner"><h3>AED {{ summary.gross_profit ?? '0.00' }}</h3><p>Gross Profit</p></div><i class="small-box-icon bi bi-graph-up"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-danger"><div class="inner"><h3>AED {{ summary.expenses ?? '0.00' }}</h3><p>Expenses</p></div><i class="small-box-icon bi bi-wallet2"></i></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h3 class="card-title">Expenses</h3>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <input v-model="term" type="search" class="form-control form-control-sm w-auto" placeholder="Search expense or category…" aria-label="Search expenses" />
        <button type="button" class="btn btn-primary" @click="openAdd">Add expense</button>
      </div>
    </div>
    <div class="card-body p-0">
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Expense</th><th>Category</th><th>Date</th><th>VAT</th><th>Source</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <tr v-if="filteredExpenses.length === 0"><td colspan="7" class="text-center text-secondary py-4">{{ term.trim() ? 'No expenses match this search.' : 'No expenses found.' }}</td></tr>
          <tr v-for="e in filteredExpenses" :key="e.id">
            <td class="fw-semibold">{{ e.name }}</td>
            <td class="text-secondary">{{ e.category || '—' }}</td>
            <td class="text-secondary">{{ formatDate(e.date) }}</td>
            <td><span class="badge text-bg-secondary">{{ e.vat_status || 'n/a' }}</span></td>
            <td>
              <span v-if="e.purchase_order" class="badge text-bg-info font-monospace" :title="'Auto-created from purchase order ' + e.purchase_order.po_number">{{ e.purchase_order.po_number }}</span>
              <span v-else class="text-secondary">Manual</span>
            </td>
            <td class="text-end fw-semibold">AED {{ Number(e.amount ?? 0).toFixed(2) }}</td>
            <td class="text-end text-nowrap">
              <template v-if="!e.purchase_order_id">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="openEdit(e)">Edit</button>
                <button type="button" class="btn btn-sm btn-danger ms-2" @click="remove(e)">Delete</button>
              </template>
              <span v-else class="text-secondary small">From PO</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ form.editingId ? 'Edit expense' : 'Add expense' }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="save">
            <div class="mb-3">
              <label class="form-label" for="expenseName">Expense *</label>
              <input id="expenseName" v-model="form.name" required maxlength="255" class="form-control" placeholder="e.g. November rent" />
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label class="form-label" for="expenseCategory">Category *</label>
                <input id="expenseCategory" v-model="form.category" required maxlength="100" list="expenseCategories" class="form-control" />
                <datalist id="expenseCategories">
                  <option value="Rent"></option>
                  <option value="Logistics"></option>
                  <option value="Marketing"></option>
                  <option value="Salaries"></option>
                  <option value="Utilities"></option>
                  <option value="Software"></option>
                  <option value="Other"></option>
                </datalist>
              </div>
              <div class="col-6 mb-3">
                <label class="form-label" for="expenseDate">Date *</label>
                <input id="expenseDate" v-model="form.date" type="date" required class="form-control" />
              </div>
              <div class="col-6 mb-3">
                <label class="form-label" for="expenseAmount">Amount (AED) *</label>
                <input id="expenseAmount" v-model="form.amount" type="number" min="0.01" step="0.01" required class="form-control" />
              </div>
              <div class="col-6 mb-3">
                <label class="form-label" for="expenseVatStatus">VAT</label>
                <select id="expenseVatStatus" v-model="form.vat_status" class="form-select">
                  <option value="not_applicable">Not applicable</option>
                  <option value="recoverable">Recoverable</option>
                  <option value="non_recoverable">Non-recoverable</option>
                </select>
              </div>
              <div class="col-6 mb-3">
                <label class="form-label" for="expenseVat">VAT amount</label>
                <input id="expenseVat" v-model="form.vat_amount" type="number" min="0" step="0.01" class="form-control" />
              </div>
              <div class="col-6 mb-3">
                <label class="form-label" for="expensePayee">Paid to</label>
                <input id="expensePayee" v-model="form.supplier_payee" maxlength="255" class="form-control" />
              </div>
              <div class="col-6 mb-3">
                <label class="form-label" for="expenseMethod">Payment method</label>
                <input id="expenseMethod" v-model="form.payment_method" maxlength="100" class="form-control" placeholder="card, cash, bank_transfer" />
              </div>
              <div class="col-12 mb-3">
                <label class="form-label" for="expenseNotes">Notes</label>
                <textarea id="expenseNotes" v-model="form.notes" rows="2" maxlength="2000" class="form-control"></textarea>
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
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { confirmAdmin } from './adminAlerts';
import { useCollectionSearch } from './search.js';

const summary = ref({});
const expenses = ref([]);
const saving = ref(false);
const { term, filtered: filteredExpenses } = useCollectionSearch(expenses, useRoute(), [
  (e) => e.name,
  (e) => e.category,
]);

const blank = () => ({
  editingId: null,
  name: '',
  category: '',
  date: todayLocal(),
  amount: '',
  vat_status: 'not_applicable',
  vat_amount: 0,
  supplier_payee: '',
  payment_method: '',
  notes: '',
  error: '',
});

const form = reactive(blank());

function todayLocal() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  return `${now.getFullYear()}-${month}-${String(now.getDate()).padStart(2, '0')}`;
}

onMounted(load);

async function load() {
  try {
    const [report, list] = await Promise.all([
      axios.get('/api/reports/accounting'),
      axios.get('/api/expenses'),
    ]);
    summary.value = report.data || {};
    expenses.value = Array.isArray(list.data) ? list.data : [];
  } catch {
    // Leave the last good values on screen.
  }
}

function modal() {
  return window.bootstrap?.Modal?.getOrCreateInstance(document.getElementById('expenseModal')) ?? null;
}

function openAdd() {
  Object.assign(form, blank());
  modal()?.show();
}

function openEdit(expense) {
  Object.assign(form, blank(), {
    editingId: expense.id,
    name: expense.name || '',
    category: expense.category || '',
    date: (expense.date || '').slice(0, 10),
    amount: expense.amount ?? '',
    vat_status: expense.vat_status || 'not_applicable',
    vat_amount: expense.vat_amount ?? 0,
    supplier_payee: expense.supplier_payee || '',
    payment_method: expense.payment_method || '',
    notes: expense.notes || '',
  });
  modal()?.show();
}

function payload() {
  return {
    name: form.name,
    category: form.category,
    date: form.date,
    amount: Number(form.amount),
    vat_status: form.vat_status,
    vat_amount: Number(form.vat_amount || 0),
    supplier_payee: form.supplier_payee || null,
    payment_method: form.payment_method || null,
    notes: form.notes || null,
  };
}

async function save() {
  saving.value = true;
  form.error = '';
  try {
    if (form.editingId) {
      await axios.put(`/api/expenses/${form.editingId}`, payload());
    } else {
      await axios.post('/api/expenses', payload());
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

async function remove(expense) {
  const confirmed = await confirmAdmin({
    title: 'Delete expense?',
    text: `Delete "${expense.name}"? This cannot be undone.`,
    confirmButtonText: 'Delete expense',
    destructive: true,
  });

  if (!confirmed.isConfirmed) return;

  try {
    await axios.delete(`/api/expenses/${expense.id}`);
    await load();
  } catch {
    // The API message is surfaced by the global alert interceptor.
  }
}

function formatDate(value) {
  const text = String(value ?? '').slice(0, 10);
  return text || '—';
}
</script>
