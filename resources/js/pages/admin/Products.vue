<template>
  <div class="product-workspace">
    <header class="catalog-heading">
      <div><span class="catalog-eyebrow"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> CATALOG WORKSPACE</span><h2>Product catalog</h2><p>Find, review, and manage your products in one place.</p></div>
      <div class="catalog-heading-actions"><router-link to="/admin/inventory" class="catalog-button"><i class="fa-solid fa-warehouse" aria-hidden="true"></i> Inventory</router-link><button type="button" class="catalog-button primary" :disabled="loading" @click="refresh"><i class="fa-solid fa-arrows-rotate" :class="{ spinning: loading }" aria-hidden="true"></i> Refresh</button></div>
    </header>

    <div v-if="success" class="catalog-feedback success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>{{ success }}</span><button type="button" class="icon-button" aria-label="Dismiss success message" @click="success = ''"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
    <div v-if="categoryError" class="catalog-feedback warning" role="alert"><span>{{ categoryError }}</span><button type="button" class="catalog-button" @click="loadCategories">Retry categories</button></div>

    <section class="catalog-panel" aria-labelledby="catalog-search-title">
      <div class="catalog-panel-heading"><div><h3 id="catalog-search-title">Find a product</h3><p>Search by product name, SKU, or category.</p></div><button type="button" class="catalog-button subtle" :disabled="!hasFilters" @click="resetFilters"><i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Reset filters</button></div>
      <form class="catalog-filters" role="search" @submit.prevent="refresh">
        <div class="search-field"><label for="product-search">Search products</label><div class="search-input"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="product-search" v-model="filters.q" type="search" maxlength="255" placeholder="Name, SKU, or category…" autocomplete="off" /></div></div>
        <div><label for="category-filter">Category</label><select id="category-filter" v-model="filters.category_id"><option value="">All categories</option><option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option></select></div>
        <details class="advanced-filters" :open="hasAdvancedFilters">
          <summary><i class="fa-solid fa-sliders" aria-hidden="true"></i> Stock, status &amp; price filters <span v-if="hasAdvancedFilters" class="filter-active">Applied</span></summary>
          <div class="advanced-filter-grid">
        <div><label for="stock-filter">Stock status</label><select id="stock-filter" v-model="filters.stock_status"><option value="">Any stock level</option><option value="in_stock">In stock</option><option value="low_stock">Low stock</option><option value="out_of_stock">Out of stock</option></select></div>
        <div><label for="active-filter">Product status</label><select id="active-filter" v-model="filters.is_active"><option value="">All statuses</option><option value="1">Active</option><option value="0">Draft</option></select></div>
        <div><label for="price-min">Min. price (AED)</label><input id="price-min" v-model="filters.min_price" type="number" min="0" max="99999999.99" step="0.01" placeholder="0.00" /></div>
        <div><label for="price-max">Max. price (AED)</label><input id="price-max" v-model="filters.max_price" type="number" :min="filters.min_price || 0" max="99999999.99" step="0.01" placeholder="No maximum" /></div>
          </div>
        </details>
        <button type="submit" class="visually-hidden">Search products</button>
      </form>
    </section>

    <section class="catalog-panel" aria-labelledby="product-list-title" :aria-busy="loading">
      <div class="catalog-panel-heading">
        <div><h3 id="product-list-title">Products <span v-if="!loading && !error" class="count-badge">{{ pagination.total }}</span></h3><p aria-live="polite">{{ loading ? 'Updating product list…' : error ? 'The list could not be updated.' : pagination.total + ' matching products' }}</p></div>
        <label class="page-size" for="products-per-page">Show <select id="products-per-page" v-model.number="perPage"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option></select><span>per page</span></label>
      </div>
      <div class="product-table-wrap">
        <table class="product-table">
          <caption class="visually-hidden">Products with SKU, category, price, stock, publishing status, and management actions</caption>
          <thead><tr><th scope="col">Product / SKU</th><th scope="col">Category</th><th scope="col">Price</th><th scope="col">Stock</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
          <tbody v-if="loading"><tr><td colspan="6" class="list-state" role="status"><i class="fa-solid fa-circle-notch spinning" aria-hidden="true"></i><strong>Loading products</strong><span>Your latest results are on their way.</span></td></tr></tbody>
          <tbody v-else-if="error"><tr><td colspan="6" class="list-state"><div role="alert"><strong>Unable to load products</strong><span>{{ error }}</span></div><button type="button" class="catalog-button" @click="refresh">Try again</button></td></tr></tbody>
          <tbody v-else-if="!products.length"><tr><td colspan="6" class="list-state"><i class="fa-solid fa-box-open" aria-hidden="true"></i><strong>{{ hasFilters ? 'No products match your search' : 'No products yet' }}</strong><span>{{ hasFilters ? 'Try a different term or clear your filters.' : 'Your product catalog will appear here.' }}</span><button v-if="hasFilters" type="button" class="catalog-button" @click="resetFilters">Clear all filters</button></td></tr></tbody>
          <tbody v-else>
            <tr v-for="product in products" :key="product.id" class="product-row">
              <td class="product-name-cell"><div class="product-identity"><div class="product-thumbnail"><img v-if="safeImage(product.featured_image_url)" :src="product.featured_image_url" alt="" loading="lazy" @error="$event.target.hidden = true" /><i v-else class="fa-solid fa-box" aria-hidden="true"></i></div><div><button type="button" class="product-name" @click="openProduct(product.id, 'view')">{{ product.name }}</button><span class="sku-label">{{ product.sku }}</span></div></div></td>
              <td data-label="Category">{{ product.category?.name || 'Uncategorized' }}</td>
              <td data-label="Price" class="price-cell">{{ money(product.price) }}</td>
              <td data-label="Stock"><span class="stock-count">{{ product.stock_quantity }}</span><span class="stock-label" :class="stockStatus(product).key">{{ stockStatus(product).label }}</span></td>
              <td data-label="Status"><span class="status-pill" :class="{ active: product.is_active }"><span></span>{{ product.is_active ? 'Active' : 'Draft' }}</span></td>
              <td class="actions-cell"><div class="product-actions"><button type="button" class="row-action" :aria-label="'View ' + product.name" @click="openProduct(product.id, 'view')"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button><button type="button" class="row-action" :aria-label="'Edit ' + product.name" @click="openProduct(product.id, 'edit')"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button><button type="button" class="row-action danger" :aria-label="'Delete ' + product.name" @click="openProduct(product.id, 'delete')"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button></div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer v-if="!error" class="catalog-pagination">
        <span aria-live="polite">{{ loading ? 'Loading…' : 'Showing ' + (pagination.from || 0) + '–' + (pagination.to || 0) + ' of ' + pagination.total }}</span>
        <nav aria-label="Product pagination"><button type="button" class="catalog-button" :disabled="loading || page <= 1" @click="page--"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Previous</button><span>Page {{ page }} of {{ pagination.last_page }}</span><button type="button" class="catalog-button" :disabled="loading || page >= pagination.last_page" @click="page++">Next <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></nav>
      </footer>
    </section>

    <dialog ref="productDialog" class="product-dialog" :class="{ 'delete-dialog': mode === 'delete' }" aria-labelledby="product-dialog-title" @cancel.prevent="closeDialog" @close="closeProduct">
      <header class="dialog-heading"><div><span class="catalog-eyebrow">{{ mode === 'delete' ? 'CONFIRM DELETION' : mode === 'edit' ? 'EDIT PRODUCT' : 'PRODUCT DETAILS' }}</span><h2 id="product-dialog-title">{{ detailLoading ? 'Loading product…' : activeProduct?.name || 'Product' }}</h2></div><button type="button" class="icon-button" :disabled="busy" aria-label="Close product dialog" @click="closeDialog"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
      <div v-if="detailLoading" class="list-state" role="status"><i class="fa-solid fa-circle-notch spinning" aria-hidden="true"></i>Loading product information…</div>
      <div v-else-if="detailError" class="dialog-scroll"><div class="catalog-feedback warning" role="alert">{{ detailError }}</div><button type="button" class="catalog-button" @click="reloadProduct">Retry details</button></div>
      <template v-else-if="activeProduct">
        <div v-if="mode === 'delete'" class="dialog-scroll">
          <div v-if="actionError" ref="actionFeedback" class="catalog-feedback warning" role="alert" tabindex="-1">{{ actionError }}</div>
          <div class="delete-symbol"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></div>
          <h3>Delete “{{ activeProduct.name }}”?</h3><p>This removes the product from the catalog. Existing order and inventory history will be preserved.</p>
          <label class="delete-confirmation"><input v-model="deleteConfirmed" type="checkbox" :disabled="busy" /> I confirm that I want to delete this product.</label>
        </div>
        <form v-else-if="mode === 'edit'" id="product-edit-form" class="dialog-scroll" @submit.prevent="submitEdit">
          <div v-if="actionError" ref="actionFeedback" class="catalog-feedback warning" role="alert" tabindex="-1">{{ actionError }}</div>
          <p class="editor-note">Fields marked <span aria-hidden="true">*</span> are required. Stock changes are recorded in inventory history.</p>
          <fieldset v-for="group in productFieldGroups" :key="group.title" :disabled="busy" class="editor-group"><legend>{{ group.title }}</legend><div class="editor-grid">
            <div v-for="field in group.fields" :key="field.key" :class="{ 'field-wide': field.wide }">
              <label :for="'edit-' + field.key">{{ field.label }} <span v-if="field.required" aria-hidden="true">*</span></label>
              <div v-if="field.type === 'image'" class="image-editor">
                <div class="image-editor-preview"><img v-if="imagePreview && (imageFile || safeImage(imagePreview))" :src="imagePreview" alt="Product image preview" @load="$event.target.hidden = false" @error="$event.target.hidden = true" /><span v-else><i class="fa-regular fa-image" aria-hidden="true"></i> No image</span></div>
                <div class="image-editor-controls">
                  <label class="catalog-button" for="product-image-file"><i class="fa-solid fa-upload" aria-hidden="true"></i> {{ imagePreview ? 'Replace image' : 'Upload image' }}</label>
                  <input id="product-image-file" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp" :disabled="busy" :aria-invalid="Boolean(imageError || fieldErrors.image)" :aria-describedby="imageError || fieldErrors.image ? 'product-image-error' : 'hint-featured_image_url'" @change="selectImage($event.target.files[0]); $event.target.value = ''" />
                  <button v-if="imagePreview" type="button" class="catalog-button" @click="removeImage"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Remove image</button>
                  <span v-if="imageFile" class="field-hint">Selected: {{ imageFile.name }}</span>
                </div>
                <p v-if="imageError || fieldErrors.image" id="product-image-error" class="field-error" role="alert">{{ imageError || fieldErrors.image?.join(' ') }}</p>
                <label :for="'edit-' + field.key" class="field-hint">Or use an image URL / local path</label>
                <input :id="'edit-' + field.key" v-model="form[field.key]" type="text" maxlength="255" :aria-invalid="Boolean(fieldErrors[field.key])" :aria-describedby="fieldDescription(field)" @input="resetImage(form[field.key])" />
              </div>
              <select v-else-if="field.type === 'category'" :id="'edit-' + field.key" v-model="form[field.key]" :aria-invalid="Boolean(fieldErrors[field.key])" :aria-describedby="fieldDescription(field)"><option value="">Uncategorized</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
              <select v-else-if="field.type === 'visibility'" :id="'edit-' + field.key" v-model="form[field.key]" required :aria-invalid="Boolean(fieldErrors[field.key])" :aria-describedby="fieldDescription(field)"><option value="public">Public</option><option value="hidden">Hidden</option><option value="private">Private</option></select>
              <textarea v-else-if="field.type === 'textarea'" :id="'edit-' + field.key" v-model="form[field.key]" :maxlength="field.maxlength" rows="4" :aria-invalid="Boolean(fieldErrors[field.key])" :aria-describedby="fieldDescription(field)"></textarea>
              <input v-else :id="'edit-' + field.key" v-model="form[field.key]" :type="field.type || 'text'" :required="field.required" :maxlength="field.maxlength" :pattern="field.pattern" :min="field.key === 'compare_at_price' ? form.price || 0 : field.min" :max="field.max" :step="field.step" :aria-invalid="Boolean(fieldErrors[field.key])" :aria-describedby="fieldDescription(field)" />
              <small v-if="field.hint" :id="'hint-' + field.key" class="field-hint">{{ field.hint }}</small><span v-if="fieldErrors[field.key]" :id="'error-' + field.key" class="field-error">{{ fieldErrors[field.key].join(' ') }}</span>
            </div>
          </div></fieldset>
          <fieldset :disabled="busy" class="editor-group"><legend>Product options</legend><div class="product-options"><label><input v-model="form.is_active" type="checkbox" /> Active product</label><label><input v-model="form.featured" type="checkbox" /> Featured product</label><label><input v-model="form.requires_subscription" type="checkbox" /> Requires subscription</label></div><span v-for="key in ['is_active', 'featured', 'requires_subscription']" :key="key" class="field-error">{{ fieldErrors[key]?.join(' ') }}</span></fieldset>
        </form>
        <div v-else class="dialog-scroll">
          <div class="detail-summary"><div class="detail-image"><img v-if="safeImage(activeProduct.featured_image_url)" :src="activeProduct.featured_image_url" :alt="activeProduct.name" @error="$event.target.hidden = true" /><i v-else class="fa-solid fa-box" aria-hidden="true"></i></div><div><span class="status-pill" :class="{ active: activeProduct.is_active }">{{ activeProduct.is_active ? 'Active' : 'Draft' }}</span><strong class="detail-price">{{ money(activeProduct.price) }}</strong><p>{{ activeProduct.sku }} · {{ activeProduct.category?.name || 'Uncategorized' }}</p></div></div>
          <section v-for="group in productFieldGroups" :key="group.title" class="detail-group"><h3>{{ group.title }}</h3><dl class="detail-grid"><div v-for="field in group.fields" :key="field.key" :class="{ 'field-wide': field.wide }"><dt>{{ field.label }}</dt><dd>{{ detailValue(field.key) }}</dd></div></dl></section>
          <section class="detail-group"><h3>Options &amp; record details</h3><dl class="detail-grid"><div v-for="(label, key) in { is_active: 'Active', featured: 'Featured', requires_subscription: 'Requires subscription' }" :key="key"><dt>{{ label }}</dt><dd>{{ activeProduct[key] ? 'Yes' : 'No' }}</dd></div><div><dt>Stock status</dt><dd>{{ stockStatus(activeProduct).label }}</dd></div><div><dt>Product ID</dt><dd>{{ activeProduct.id }}</dd></div><div><dt>Created</dt><dd>{{ dateTime(activeProduct.created_at) }}</dd></div><div><dt>Last updated</dt><dd>{{ dateTime(activeProduct.updated_at) }}</dd></div></dl></section>
          <section class="detail-group"><h3>Variants ({{ activeProduct.variants?.length || 0 }})</h3><p v-if="!activeProduct.variants?.length">No variants for this product.</p><div v-else class="variant-list"><article v-for="variant in activeProduct.variants" :key="variant.id"><strong>{{ variant.name }}</strong><span>SKU: {{ variant.sku }}</span><span>Price modifier: {{ money(variant.price_modifier) }}</span><span>Stock: {{ variant.stock_quantity }} · {{ Number(variant.is_active) ? 'Active' : 'Draft' }}</span></article></div></section>
          <section class="detail-group"><h3>Product images ({{ activeProduct.images?.length || 0 }})</h3><p v-if="!activeProduct.images?.length">No additional images.</p><ul v-else class="image-records"><li v-for="image in activeProduct.images" :key="image.id"><span>{{ image.image_path }}</span><strong v-if="Number(image.is_primary)">Primary image</strong></li></ul></section>
        </div>
      </template>
      <footer class="dialog-footer">
        <button type="button" class="catalog-button" :disabled="busy" autofocus @click="closeDialog">{{ mode === 'view' ? 'Close' : 'Cancel' }}</button>
        <button v-if="activeProduct && mode === 'view'" type="button" class="catalog-button primary" @click="startEdit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit product</button>
        <button v-if="activeProduct && mode === 'edit'" type="submit" form="product-edit-form" class="catalog-button primary" :disabled="busy || Boolean(imageError)">{{ busy ? 'Saving…' : 'Save changes' }}</button>
        <button v-if="activeProduct && mode === 'delete'" type="button" class="catalog-button destructive" :disabled="!deleteConfirmed || busy" @click="submitDelete">{{ busy ? 'Deleting…' : 'Delete product' }}</button>
      </footer>
    </dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { useRoute } from 'vue-router';
import { confirmAdmin, Swal } from './adminAlerts';
import { productFieldGroups, productForm, stockStatus, useProductImage, useProducts } from './products';
import { formatSiteDate } from '../../siteTime.js';

const { filters, products, categories, categoryError, loading, error, page, perPage, pagination, activeProduct, detailLoading, detailError, busy, actionError, fieldErrors, success, hasFilters, refresh, loadCategories, resetFilters, loadProduct, closeProduct, saveProduct, deleteProduct } = useProducts(useRoute());
const productDialog = ref(null);
const actionFeedback = ref(null);
const mode = ref('view');
const form = ref({});
const { file: imageFile, preview: imagePreview, error: imageError, reset: resetImage, select: selectImage } = useProductImage();
const deleteConfirmed = ref(false);
const hasAdvancedFilters = computed(() => ['stock_status', 'is_active', 'min_price', 'max_price'].some((key) => String(filters[key]).trim() !== ''));
let selectedId;
let opener;
const money = (value) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(Number(value || 0));
const dateTime = (value) => formatSiteDate(value, { dateStyle: 'medium', timeStyle: 'short' });
const safeImage = (value) => typeof value === 'string' && !value.includes('\\') && (/^https?:\/\//i.test(value) || /^\/(?!\/)/.test(value));

async function openProduct(id, action) {
  if (action === 'delete') {
    await loadProduct(id);
    if (!activeProduct.value) return;
    await confirmAdmin({
      title: 'Delete product?',
      text: 'Delete “' + activeProduct.value.name + '”? Existing order and inventory history will be preserved.',
      confirmButtonText: 'Delete product',
      destructive: true,
      input: 'checkbox',
      inputPlaceholder: 'I confirm deletion of this product.',
      inputValidator: value => value ? undefined : 'Please confirm deletion.',
      showLoaderOnConfirm: true,
      preConfirm: async () => {
        if (await deleteProduct(true)) return true;
        Swal.showValidationMessage(actionError.value || 'Product could not be deleted.');
        return false;
      },
    });
    closeProduct();
    return;
  }
  selectedId = id;
  opener = document.activeElement;
  mode.value = action;
  deleteConfirmed.value = false;
  productDialog.value.showModal();
  await reloadProduct();
}
async function reloadProduct() {
  await loadProduct(selectedId);
  if (activeProduct.value && mode.value === 'edit') {
    form.value = productForm(activeProduct.value);
    resetImage(form.value.featured_image_url);
  }
}
async function startEdit() {
  form.value = productForm(activeProduct.value);
  resetImage(form.value.featured_image_url);
  mode.value = 'edit';
  await nextTick();
  productDialog.value.querySelector('#edit-name')?.focus();
}
function closeDialog() {
  if (!closeProduct()) return;
  resetImage();
  productDialog.value.close();
  if (opener?.isConnected) opener.focus();
  else document.getElementById('product-search')?.focus();
}
async function submitEdit() {
  if (imageError.value) return;
  if (await saveProduct(form.value, imageFile.value)) closeDialog();
  else {
    await nextTick();
    (productDialog.value.querySelector('[aria-invalid="true"]') || actionFeedback.value)?.focus();
  }
}
async function submitDelete() {
  if (await deleteProduct(deleteConfirmed.value)) closeDialog();
  else {
    await nextTick();
    actionFeedback.value?.focus();
  }
}
function fieldDescription(field) {
  return [field.hint ? 'hint-' + field.key : '', fieldErrors.value[field.key] ? 'error-' + field.key : ''].filter(Boolean).join(' ') || undefined;
}
function removeImage() {
  resetImage();
  form.value.featured_image_url = '';
}
function detailValue(key) {
  if (key === 'category_id') return activeProduct.value.category?.name || 'Uncategorized';
  const value = activeProduct.value[key];
  if (value === null || value === undefined || value === '') return 'Not set';
  return ['price', 'cost_price', 'compare_at_price'].includes(key) ? money(value) : value;
}
onBeforeUnmount(() => productDialog.value?.close());
</script>

<style scoped>
.product-workspace {
  --catalog-accent: var(--admin-link); --catalog-ink: var(--admin-text); --catalog-muted: var(--admin-muted);
  --catalog-surface: var(--admin-surface); --catalog-inset: var(--admin-inset); --catalog-border: var(--admin-border);
  --catalog-danger: var(--admin-danger-text); --catalog-success: var(--admin-success-text); --catalog-warning: var(--admin-warning-text);
  max-width: 1600px; margin: 0 auto; padding: .5rem 1rem 2.5rem;
  color: var(--catalog-ink); font-family: 'Source Sans 3', 'Open Sans', system-ui, sans-serif; line-height: 1.55;
}

.catalog-heading, .catalog-heading-actions, .catalog-panel-heading, .catalog-pagination, .catalog-pagination nav, .dialog-heading, .dialog-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
.catalog-heading { margin-bottom: 1.5rem; }
.catalog-heading-actions { gap: .75rem; }
.catalog-eyebrow { display: inline-flex; align-items: center; gap: .6rem; color: var(--catalog-accent); font-size: .8rem; letter-spacing: .1em; font-weight: 700; }
.catalog-heading h2 { margin: .4rem 0; font-size: clamp(1.75rem, 3vw, 2.2rem); font-weight: 700; letter-spacing: -.035em; }
.catalog-heading p, .catalog-panel-heading p { color: var(--catalog-muted); margin: 0; }
.catalog-button, .row-action, .icon-button { display: inline-flex; align-items: center; justify-content: center; gap: .55rem; border: 1px solid var(--catalog-border); border-radius: 9px; padding: .65rem .9rem; min-height: 42px; background: var(--catalog-surface); color: var(--catalog-ink); font: inherit; font-size: .95rem; font-weight: 600; text-decoration: none; cursor: pointer; }
.catalog-button:hover, .row-action:hover { border-color: var(--catalog-accent); color: var(--catalog-accent); }
.catalog-button.primary { background: var(--admin-primary); border-color: var(--admin-primary); color: #FFFFFF; }
.catalog-button.destructive { background: var(--admin-danger); border-color: var(--admin-danger); color: #000000; }
.catalog-button.subtle { background: transparent; }
.catalog-button:disabled, .row-action:disabled, .icon-button:disabled { opacity: .5; cursor: not-allowed; }
.icon-button { width: 42px; padding: .5rem; flex-shrink: 0; }
.catalog-panel { border: 1px solid var(--catalog-border); border-radius: 16px; background: var(--catalog-surface); overflow: hidden; margin-bottom: 1.5rem; box-shadow: 0 3px 12px #182d4008; }
.catalog-panel-heading { padding: 1.25rem 1.4rem; border-bottom: 1px solid var(--catalog-border); }
.catalog-panel-heading h3 { font-size: 1.125rem; margin: 0 0 .25rem; font-weight: 700; }
.catalog-panel-heading p { font-size: .9rem; }
.catalog-filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; padding: 1.4rem; }
.search-field { grid-column: span 3; }
.advanced-filters { grid-column: 1 / -1; border-top: 1px solid var(--catalog-border); padding-top: 1rem; }
.advanced-filters summary { color: var(--catalog-accent); font-size: .95rem; font-weight: 600; cursor: pointer; min-height: 32px; }
.advanced-filters summary > i { margin: 0 .4rem; }
.advanced-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; padding-top: 1rem; }
.filter-active { display: inline-block; background: var(--catalog-inset); border: 1px solid var(--catalog-border); border-radius: 6px; padding: .1rem .45rem; font-size: .8rem; margin-left: .4rem; }
.product-workspace label { display: block; font-size: .9rem; font-weight: 600; margin-bottom: .4rem; }
.product-workspace input:not([type="checkbox"]), .product-workspace select, .product-workspace textarea { width: 100%; min-width: 0; min-height: 44px; padding: .65rem .75rem; border: 1px solid var(--catalog-border); border-radius: 8px; background: var(--catalog-surface); color: var(--catalog-ink); font: inherit; }
.product-workspace input::placeholder { color: var(--catalog-muted); }
.search-input { position: relative; }
.search-input > i { position: absolute; top: 50%; left: .9rem; transform: translateY(-50%); color: var(--catalog-muted); }
.product-workspace .search-input input { padding-left: 2.5rem; }
.product-workspace .page-size { display: flex; align-items: center; gap: .6rem; font-weight: 400; color: var(--catalog-muted); margin: 0; }
.page-size select { width: 75px; }
.count-badge { display: inline-block; margin-left: .4rem; padding: .15rem .5rem; border-radius: 6px; background: var(--catalog-inset); color: var(--catalog-muted); font-size: .875rem; }
.product-table-wrap { overflow-x: auto; }
.product-table { width: 100%; border-collapse: collapse; }
.product-table th { background: var(--catalog-inset); color: var(--catalog-muted); padding: .9rem 1.25rem; text-align: left; font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; white-space: nowrap; }
.product-table td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--catalog-border); font-size: .95rem; vertical-align: middle; }
.product-row:last-child td { border-bottom: 0; }
.product-row:hover { background: var(--catalog-inset); }
.product-identity { display: flex; align-items: center; gap: .85rem; min-width: 200px; }
.product-identity > div:last-child { min-width: 0; }
.product-thumbnail { width: 48px; height: 48px; flex-shrink: 0; display: grid; place-items: center; border-radius: 11px; color: var(--catalog-accent); background: var(--catalog-inset); overflow: hidden; }
.product-thumbnail img { width: 100%; height: 100%; object-fit: contain; }
.product-name { display: block; color: var(--catalog-ink); background: transparent; border: 0; padding: 0; font: inherit; font-weight: 600; text-align: left; overflow-wrap: anywhere; }
.product-name:hover { color: var(--catalog-accent); text-decoration: underline; }
.sku-label { display: block; font-size: .8rem; color: var(--catalog-muted); font-family: ui-monospace, monospace; margin-top: .3rem; overflow-wrap: anywhere; }
.price-cell { font-weight: 600; white-space: nowrap; }
.stock-count { display: block; font-weight: 700; font-variant-numeric: tabular-nums; }
.stock-label { display: block; font-size: .8rem; white-space: nowrap; }
.stock-label.in_stock { color: var(--catalog-success); }
.stock-label.low_stock { color: var(--catalog-warning); }
.stock-label.out_of_stock { color: var(--catalog-danger); }
.status-pill { display: inline-flex; align-items: center; gap: .4rem; background: var(--catalog-inset); color: var(--catalog-muted); border-radius: 6px; padding: .25rem .6rem; font-size: .875rem; white-space: nowrap; }
.status-pill.active { background: color-mix(in srgb, var(--catalog-success) 10%, var(--catalog-surface)); color: var(--catalog-success); }
.status-pill > span { width: 6px; height: 6px; background: currentColor; border-radius: 50%; }
.product-actions { display: flex; gap: .4rem; }
.row-action { font-size: .875rem; padding: .45rem .6rem; white-space: nowrap; }
.row-action.danger { color: var(--catalog-danger); }
.row-action.danger:hover { border-color: var(--catalog-danger); }
.catalog-pagination { padding: 1rem 1.4rem; background: var(--catalog-inset); border-top: 1px solid var(--catalog-border); color: var(--catalog-muted); font-size: .9rem; }
.catalog-pagination nav { gap: .75rem; }
.list-state { text-align: center; padding: 3rem 1.25rem !important; color: var(--catalog-muted); }
.list-state > i { display: block; color: var(--catalog-accent); font-size: 1.75rem; margin-bottom: .9rem; }
.list-state strong, .list-state span { display: block; }
.list-state strong { font-size: 1.1rem; color: var(--catalog-ink); margin-bottom: .3rem; }
.list-state .catalog-button { margin-top: 1rem; }
.catalog-feedback { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem; margin-bottom: 1rem; border-radius: 10px; border: 1px solid var(--catalog-border); }
.catalog-feedback.success { color: var(--catalog-success); background: color-mix(in srgb, var(--catalog-success) 8%, var(--catalog-surface)); }
.catalog-feedback.warning { color: var(--catalog-warning); background: color-mix(in srgb, var(--catalog-warning) 8%, var(--catalog-surface)); }
.catalog-feedback > span { flex: 1; }
.product-dialog { width: min(880px, calc(100% - 2rem)); max-height: 90dvh; padding: 0; border: 1px solid var(--catalog-border); border-radius: 18px; background: var(--catalog-surface); color: var(--catalog-ink); overflow: hidden; box-shadow: 0 24px 80px #0c1c3555; margin: auto; }
.product-dialog[open] { display: flex; flex-direction: column; }
.product-dialog::backdrop { background: #0c1c3580; backdrop-filter: blur(3px); }
.product-dialog.delete-dialog { width: min(560px, calc(100% - 2rem)); }
.dialog-heading { padding: 1.4rem; border-bottom: 1px solid var(--catalog-border); flex-wrap: nowrap; align-items: flex-start; flex-shrink: 0; }
.dialog-heading h2 { font-size: 1.4rem; font-weight: 700; margin: .4rem 0 0; overflow-wrap: anywhere; }
.dialog-scroll { padding: 1.4rem; overflow-y: auto; min-height: 0; }
.dialog-footer { flex-shrink: 0; justify-content: flex-end; padding: 1rem 1.4rem; border-top: 1px solid var(--catalog-border); background: var(--catalog-inset); }
.editor-note, .field-hint { color: var(--catalog-muted); font-size: .875rem; }
.editor-group { border: 0; padding: 0; margin: 1.5rem 0 0; min-width: 0; }
.editor-group legend, .detail-group h3 { font-size: 1.05rem; font-weight: 700; margin: 0 0 1rem; padding: 0; float: none; }
.editor-grid, .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem 1.25rem; }
.field-wide { grid-column: 1 / -1; }
.field-hint, .field-error { display: block; margin-top: .3rem; }
.field-error { color: var(--catalog-danger); font-size: .875rem; }
.product-workspace [aria-invalid="true"] { border-color: var(--catalog-danger) !important; }
.product-options { display: flex; flex-wrap: wrap; gap: 1rem; }
.product-workspace .product-options label, .product-workspace .delete-confirmation { display: flex; gap: .65rem; align-items: flex-start; font-weight: 400; }
.product-workspace input[type="checkbox"] { width: 18px; height: 18px; flex-shrink: 0; margin-top: .2rem; accent-color: var(--admin-primary); }
.detail-summary { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; }
.detail-summary p { color: var(--catalog-muted); margin: .35rem 0 0; overflow-wrap: anywhere; }
.detail-image { width: 100px; height: 100px; flex-shrink: 0; display: grid; place-items: center; border-radius: 14px; color: var(--catalog-accent); background: var(--catalog-inset); font-size: 2rem; overflow: hidden; }
.detail-image img { width: 100%; height: 100%; object-fit: contain; }
.image-editor-preview { min-height: 180px; display: grid; place-items: center; background: var(--catalog-inset); border: 1px dashed var(--catalog-border); border-radius: 12px; padding: 1rem; margin: .5rem 0 1rem; }
.image-editor-preview img { display: block; max-width: 100%; max-height: 280px; object-fit: contain; }
.image-editor-preview > span { color: var(--catalog-muted); display: flex; gap: .6rem; align-items: center; }
.image-editor-controls { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-bottom: 1rem; }
.product-workspace .image-editor-controls label { display: inline-flex; margin: 0; }
.image-editor-controls label:has(+ input:focus-visible) { outline: 3px solid var(--catalog-accent); outline-offset: 3px; }
.detail-price { display: block; font-size: 1.65rem; margin-top: .4rem; }
.detail-group { border-top: 1px solid var(--catalog-border); padding-top: 1.25rem; margin-top: 1.25rem; }
.detail-grid { margin: 0; }
.detail-grid dt { color: var(--catalog-muted); font-size: .875rem; font-weight: 400; }
.detail-grid dd { margin: .2rem 0 0; white-space: pre-wrap; overflow-wrap: anywhere; }
.variant-list { display: grid; gap: .75rem; }
.variant-list article { display: flex; flex-wrap: wrap; gap: .5rem 1rem; padding: 1rem; background: var(--catalog-inset); border-radius: 9px; }
.variant-list strong { flex-basis: 100%; }
.image-records { padding-left: 1.25rem; overflow-wrap: anywhere; }
.image-records strong { display: block; font-size: .875rem; color: var(--catalog-accent); }
.delete-symbol { width: 56px; height: 56px; display: grid; place-items: center; border-radius: 14px; background: color-mix(in srgb, var(--catalog-danger) 10%, var(--catalog-surface)); color: var(--catalog-danger); font-size: 1.4rem; margin-bottom: 1rem; }
.delete-dialog h3 { font-size: 1.25rem; overflow-wrap: anywhere; }
.delete-dialog p { color: var(--catalog-muted); }
.delete-confirmation { padding: 1rem; background: var(--catalog-inset); border: 1px solid var(--catalog-border); border-radius: 9px; margin-top: 1.25rem; }
.product-workspace button:focus-visible, .product-workspace a:focus-visible, .product-workspace input:focus-visible, .product-workspace select:focus-visible, .product-workspace textarea:focus-visible, .product-workspace summary:focus-visible { outline: 3px solid var(--catalog-accent); outline-offset: 3px; }
.spinning { animation: catalog-spin 1s linear infinite; }
@keyframes catalog-spin { to { transform: rotate(360deg); } }
@media (max-width: 1199px) { .catalog-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }.search-field { grid-column: span 2; }.advanced-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 767px) {
  .product-workspace { padding: .25rem 0 1.5rem; }
  .catalog-heading-actions { width: 100%; }.catalog-heading-actions > * { flex: 1; }
  .catalog-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; padding: 1rem; }
  .search-field { grid-column: 1 / -1; }
  .catalog-filters > div:nth-child(2) { grid-column: 1 / -1; }
  .catalog-panel-heading { padding: 1rem; }.catalog-panel-heading .catalog-button { padding: .55rem .7rem; }
  .product-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); }
  .product-table, .product-table tbody { display: block; }
  .product-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .9rem 1.25rem; padding: 1.25rem; border-bottom: 1px solid var(--catalog-border); }
  .product-row:last-child { border-bottom: 0; }.product-table .product-row td { padding: 0; border: 0; min-width: 0; }
  .product-name-cell, .actions-cell { grid-column: 1 / -1; }
  .product-row td[data-label]::before { content: attr(data-label); display: block; font-size: .8rem; color: var(--catalog-muted); margin-bottom: .25rem; }
  .product-identity { min-width: 0; }.product-actions { padding-top: .5rem; }.product-actions > * { flex: 1; }
  .catalog-pagination { padding: 1rem; }.catalog-pagination nav { width: 100%; justify-content: space-between; gap: .5rem; }.catalog-pagination .catalog-button { padding: .55rem .7rem; }.page-size > span { display: none; }
  .product-dialog { max-height: 94dvh; width: calc(100% - 1rem); }.dialog-heading, .dialog-scroll { padding: 1rem; }.dialog-footer { padding: 1rem; gap: .6rem; }.dialog-footer > * { flex: 1; }
  .editor-grid, .detail-grid { grid-template-columns: minmax(0, 1fr); }.detail-summary { gap: 1rem; }.detail-image { width: 72px; height: 72px; }
}
@media (prefers-reduced-motion: reduce) { .spinning { animation: none; } }
</style>
