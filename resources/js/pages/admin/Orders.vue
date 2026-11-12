<template>
  <div class="orders-workspace">
    <header class="orders-heading">
      <div><span class="orders-eyebrow"><i class="fa-solid fa-receipt" aria-hidden="true"></i> SALES WORKSPACE</span><h2>Order management</h2><p>Follow every order from checkout to delivery.</p></div>
      <div class="heading-actions"><button type="button" class="order-button" aria-label="Export matching orders as CSV" :disabled="loading || exportBusy || !pagination.total" @click="exportOrders('csv')"><i class="fa-solid fa-file-csv" aria-hidden="true"></i><span class="desktop-action-label">Export CSV</span><span class="mobile-action-label">CSV</span></button><button type="button" class="order-button" aria-label="Print matching orders or save as PDF" :disabled="loading || exportBusy || !pagination.total" @click="exportOrders('print')"><i class="fa-regular fa-file-pdf" aria-hidden="true"></i><span class="desktop-action-label">Print / PDF</span><span class="mobile-action-label">PDF</span></button><button type="button" class="order-button primary" :disabled="loading" @click="refresh"><i class="fa-solid fa-arrows-rotate" :class="{ spinning: loading }" aria-hidden="true"></i> Refresh</button></div>
    </header>
    <div v-if="success" class="order-feedback success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>{{ success }}</span><button type="button" class="icon-button" aria-label="Dismiss success message" @click="success = ''"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
    <div v-if="exportError" class="order-feedback warning" role="alert">{{ exportError }}</div>
    <div v-if="exportBusy" class="export-progress" role="status"><i class="fa-solid fa-circle-notch spinning" aria-hidden="true"></i> Preparing export…</div>

    <section class="orders-panel" aria-labelledby="orders-search-title">
      <div class="panel-heading"><div><h3 id="orders-search-title">Find an order</h3><p>Search by order number, ID, customer name, or email.</p></div><button type="button" class="order-button" :disabled="!hasFilters" @click="resetFilters"><i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Reset filters</button></div>
      <form class="order-filters" role="search" @submit.prevent="refresh">
        <div class="order-search-field"><label for="order-search">Search orders</label><div class="search-control"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="order-search" v-model="filters.q" type="search" maxlength="255" autocomplete="off" placeholder="Order ID, customer name, or email…" /></div></div>
        <div><label for="order-status">Order status</label><select id="order-status" v-model="filters.status"><option value="">All statuses</option><option v-for="status in orderStatuses" :key="status" :value="status">{{ orderLabel(status) }}</option></select></div>
        <div><label for="order-payment">Payment state</label><select id="order-payment" v-model="filters.payment_status"><option value="">Any payment state</option><option v-for="state in paymentStates" :key="state" :value="state">{{ orderLabel(state) }}</option></select></div>
        <details class="date-filters" :open="Boolean(filters.start_date || filters.end_date)"><summary><i class="fa-regular fa-calendar" aria-hidden="true"></i> Date range <span v-if="filters.start_date || filters.end_date">· Applied</span></summary><div class="date-filter-grid"><div><label for="order-start">From date</label><input id="order-start" v-model="filters.start_date" type="date" :max="filters.end_date || undefined" /></div><div><label for="order-end">To date</label><input id="order-end" v-model="filters.end_date" type="date" :min="filters.start_date || undefined" /></div></div></details>
        <button type="submit" class="visually-hidden">Search orders</button>
      </form>
      <div class="filter-note"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Dates include the full day in the store timezone. Exports include all matching orders.</div>
    </section>

    <section class="orders-panel" aria-labelledby="order-list-title" :aria-busy="loading">
      <div class="panel-heading"><div><h3 id="order-list-title">Orders <span v-if="!loading && !error" class="count-badge">{{ pagination.total }}</span></h3><p aria-live="polite">{{ loading ? 'Updating your orders…' : error ? 'The list could not be updated.' : pagination.total + ' matching orders' }}</p></div><label class="page-size" for="order-page-size">Show <select id="order-page-size" v-model.number="perPage"><option v-for="size in [10, 20, 50, 100]" :key="size" :value="size">{{ size }}</option></select><span>per page</span></label></div>
      <div v-if="selected.length" class="bulk-toolbar" aria-label="Bulk order actions">
        <strong>{{ selected.length }} selected on this page</strong>
        <label class="visually-hidden" for="bulk-order-status">Bulk status</label>
        <select id="bulk-order-status" v-model="bulkStatus"><option value="">Choose a new status</option><option v-for="status in commonTransitions" :key="status" :value="status">{{ orderLabel(status) }}</option></select>
        <button type="button" class="order-button primary" :disabled="!bulkStatus || busy" @click="prepareChange(selected, bulkStatus)">Review update</button>
        <button type="button" class="order-button" :disabled="exportBusy" @click="exportOrders('csv', true)">Selected CSV</button>
        <button type="button" class="order-button" :disabled="exportBusy" @click="exportOrders('print', true)">Selected PDF</button>
        <button type="button" class="order-button" @click="selectedIds = []">Clear selection</button>
        <span v-if="!commonTransitions.length" class="bulk-note">These orders have no common next status. Update them individually.</span>
      </div>
      <div class="mobile-sort"><label for="mobile-order-sort">Sort by</label><select id="mobile-order-sort" :value="sort" @change="sortBy($event.target.value)"><option v-for="column in orderColumns" :key="column.key" :value="column.key">{{ column.label }}</option></select><button type="button" class="order-button" :aria-label="direction === 'asc' ? 'Sort descending' : 'Sort ascending'" @click="direction = direction === 'asc' ? 'desc' : 'asc'"><i :class="direction === 'asc' ? 'fa-solid fa-arrow-up-wide-short' : 'fa-solid fa-arrow-down-wide-short'" aria-hidden="true"></i>{{ direction === 'asc' ? 'Ascending' : 'Descending' }}</button></div>
      <div class="orders-table-wrap">
        <table class="orders-table">
          <caption class="visually-hidden">Orders. Use column headings to sort and row checkboxes to select orders for bulk actions.</caption>
          <thead><tr><th scope="col" class="selection-cell"><input type="checkbox" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" :disabled="loading || !orders.length" aria-label="Select all orders on this page" @change="toggleAll" /></th><th v-for="column in orderColumns" :key="column.key" scope="col" :aria-sort="sort === column.key ? direction === 'asc' ? 'ascending' : 'descending' : 'none'"><button type="button" class="sort-button" @click="sortBy(column.key)">{{ column.label }}<i :class="sort === column.key ? direction === 'asc' ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down' : 'fa-solid fa-sort'" aria-hidden="true"></i></button></th><th scope="col">Actions</th></tr></thead>
          <tbody v-if="loading" aria-label="Loading orders"><tr v-for="row in 6" :key="row" class="skeleton-row"><td v-for="column in 8" :key="column"><span class="order-skeleton"></span></td></tr></tbody>
          <tbody v-else-if="error"><tr><td colspan="8" class="list-state"><div role="alert"><strong>Unable to load orders</strong><p>{{ error }}</p></div><button type="button" class="order-button" @click="refresh">Try again</button></td></tr></tbody>
          <tbody v-else-if="!orders.length"><tr><td colspan="8" class="list-state"><i class="fa-solid fa-box-open" aria-hidden="true"></i><strong>{{ hasFilters ? 'No orders match your search' : 'No orders yet' }}</strong><p>{{ hasFilters ? 'Try another search term or clear the filters.' : 'New orders will appear here after checkout.' }}</p><button v-if="hasFilters" type="button" class="order-button" @click="resetFilters">Clear filters</button></td></tr></tbody>
          <tbody v-else><tr v-for="order in orders" :key="order.id" class="order-row" :class="{ selected: selectedIds.includes(order.id) }">
            <td class="selection-cell"><input type="checkbox" :checked="selectedIds.includes(order.id)" :aria-label="'Select order ' + (order.order_number || order.id)" @change="toggleSelection(order.id)" /></td>
            <td class="order-number-cell"><button type="button" class="order-link" @click="openOrder(order.id)">#{{ order.order_number || order.id }}</button><small>ID {{ order.id }}</small></td>
            <td data-label="Customer"><strong class="customer-name">{{ order.user?.name || 'Customer unavailable' }}</strong><small>{{ order.user?.email || 'No email recorded' }}</small></td>
            <td data-label="Date"><span class="date-label">{{ orderDate(order.created_at) }}</span><small>{{ orderTime(order.created_at) }}</small></td>
            <td data-label="Status">
              <span class="order-badge" :class="order.status"><i class="status-dot" aria-hidden="true"></i>{{ orderLabel(order.status) }}</span>
              <select v-if="order.allowed_transitions?.length" class="row-status-select" :value="order.status" :disabled="busy" :aria-label="'Update status for order ' + (order.order_number || order.id)" @change="reviewRowStatus(order, $event)">
                <option v-for="status in orderStatuses" :key="status" :value="status" :disabled="status !== order.status && !order.allowed_transitions.includes(status)">{{ orderLabel(status) }}</option>
              </select>
              <small v-else>Final status</small>
            </td>
            <td data-label="Payment"><span class="payment-badge" :class="order.payment_status">{{ orderLabel(order.payment_status || 'pending') }}</span><button v-if="order.status === 'delivered' && ['pending', 'unpaid'].includes(order.payment_status)" type="button" class="row-button payment-reconcile" :disabled="busy" :aria-label="'Record payment received for order ' + order.order_number" @click="preparePayment(order)">Mark as paid</button></td>
            <td data-label="Total" class="order-total">{{ orderMoney(order.total_amount) }}</td>
            <td class="order-actions-cell"><div class="row-actions"><button type="button" class="row-button" :aria-label="'View order ' + order.order_number" @click="openOrder(order.id)"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button><button type="button" class="row-button" :disabled="exportBusy" :aria-label="'Print invoice for order ' + order.order_number" @click="printInvoice(order)"><i class="fa-solid fa-print" aria-hidden="true"></i> Invoice</button><button v-if="order.allowed_transitions?.includes('cancelled')" type="button" class="row-button danger" :aria-label="'Cancel order ' + order.order_number" @click="prepareChange([order], 'cancelled')"><i class="fa-solid fa-ban" aria-hidden="true"></i> Cancel</button></div></td>
          </tr></tbody>
        </table>
      </div>
      <footer class="orders-pagination"><span aria-live="polite">{{ loading ? 'Loading…' : 'Showing ' + (pagination.from || 0) + '–' + (pagination.to || 0) + ' of ' + pagination.total }}</span><nav aria-label="Order pagination"><button type="button" class="order-button" :disabled="loading || page <= 1" @click="page--"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Previous</button><span>Page {{ page }} of {{ pagination.last_page }}</span><button type="button" class="order-button" :disabled="loading || page >= pagination.last_page" @click="page++">Next <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></nav></footer>
    </section>

    <dialog ref="orderDrawer" class="order-drawer" aria-labelledby="order-drawer-title" @cancel.prevent="closeDrawer" @close="closeOrder">
      <header class="drawer-heading"><div><span class="orders-eyebrow">ORDER DETAILS</span><h2 id="order-drawer-title">{{ detail ? '#' + (detail.order_number || detail.id) : 'Loading order…' }}</h2><p v-if="detail">{{ orderDate(detail.created_at) }} · {{ orderTime(detail.created_at) }}</p></div><button type="button" class="icon-button" :disabled="busy" aria-label="Close order details" @click="closeDrawer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
      <div v-if="detailLoading" class="drawer-scroll" role="status"><div v-for="row in 6" :key="row" class="detail-skeleton"><span class="order-skeleton"></span></div><p>Loading order details…</p></div>
      <div v-else-if="detailError" class="drawer-scroll"><div class="order-feedback warning" role="alert">{{ detailError }}</div><button type="button" class="order-button" @click="reloadOrder">Retry details</button></div>
      <div v-else-if="detail" class="drawer-scroll">
        <div v-if="success" class="order-feedback success" role="status">{{ success }}</div><div v-if="exportError" class="order-feedback warning" role="alert">{{ exportError }}</div>
        <div class="detail-topline"><span class="order-badge" :class="detail.status"><i class="status-dot" aria-hidden="true"></i>{{ orderLabel(detail.status) }}</span><strong>{{ orderMoney(detail.total_amount) }}</strong></div>
        <div v-if="detail.status === 'delivered' && ['pending', 'unpaid'].includes(detail.payment_status)" class="order-feedback warning">
          <span>Payment is awaiting collection confirmation.</span><button type="button" class="order-button primary" :disabled="busy" @click="preparePayment(detail)">Mark as paid</button>
        </div>
        <section class="detail-section"><h3><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Fulfillment</h3>
          <form v-if="detail.allowed_transitions?.length" class="status-form" @submit.prevent="prepareChange([detail], nextStatus)"><div><label for="detail-next-status">Next order status</label><select id="detail-next-status" v-model="nextStatus" required><option value="">Choose status</option><option v-for="status in detail.allowed_transitions" :key="status" :value="status">{{ orderLabel(status) }}</option></select></div><button type="submit" class="order-button primary" :disabled="!nextStatus || busy">Review update</button></form>
          <p v-else>No further status transitions are available.</p><dl class="detail-grid"><div><dt>Shipping state</dt><dd>{{ orderLabel(detail.shipping_status) }}</dd></div><div><dt>Tracking number</dt><dd>{{ detail.tracking_number || 'Not recorded' }}</dd></div></dl>
        </section>
        <div class="detail-columns"><section class="detail-section"><h3><i class="fa-regular fa-user" aria-hidden="true"></i> Customer</h3><strong>{{ detail.user?.name || 'Customer unavailable' }}</strong><p>{{ detail.user?.email || 'No email recorded' }}</p><small>Customer ID: {{ detail.user_id || 'Not recorded' }}</small></section><section class="detail-section"><h3><i class="fa-regular fa-credit-card" aria-hidden="true"></i> Payment</h3><span class="payment-badge" :class="detail.payment_status">{{ orderLabel(detail.payment_status || 'pending') }}</span><p>Method: {{ paymentMethodLabel(detail.payment_method) }}</p><strong>{{ orderMoney(detail.total_amount) }}</strong></section></div>
        <section v-if="['paid', 'partially_refunded', 'partially_paid'].includes(detail.payment_status)" class="detail-section"><h3><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> Refunds</h3>
          <form v-if="refundForm.open" class="status-form" @submit.prevent="confirmRefund"><div><label for="refund-amount">Refund amount (AED)</label><input id="refund-amount" v-model.number="refundForm.amount" type="number" min="0.01" step="0.01" required :disabled="busy" /></div><div><label for="refund-notes">Where &amp; why *</label><input id="refund-notes" v-model="refundForm.notes" maxlength="1000" minlength="5" required :disabled="busy" placeholder="e.g. Refunded via Stripe dashboard, damaged item" /></div><button type="submit" class="order-button primary" :disabled="busy">{{ busy ? 'Saving…' : 'Record refund' }}</button><button type="button" class="order-button" :disabled="busy" @click="refundForm.open = false">Cancel</button></form>
          <button v-else type="button" class="order-button" :disabled="busy" @click="prepareRefund(detail)">Record refund</button>
          <small>Bookkeeping only — this does not move money. Perform the actual refund with your payment provider (e.g. the Stripe dashboard) first; the customer is emailed a note.</small>
        </section>
        <div class="detail-columns"><section class="detail-section"><h3>Shipping address</h3><dl v-if="addressFields(detail.shipping_address).length" class="address-list"><div v-for="field in addressFields(detail.shipping_address)" :key="field.label"><dt>{{ field.label }}</dt><dd>{{ field.value }}</dd></div></dl><p v-else>No shipping address recorded.</p></section><section class="detail-section"><h3>Billing address</h3><dl v-if="addressFields(detail.billing_address).length" class="address-list"><div v-for="field in addressFields(detail.billing_address)" :key="field.label"><dt>{{ field.label }}</dt><dd>{{ field.value }}</dd></div></dl><p v-else>No billing address recorded.</p></section></div>
        <section class="detail-section"><h3><i class="fa-solid fa-box-open" aria-hidden="true"></i> Order items</h3><div class="item-table-wrap"><table class="item-table"><thead><tr><th scope="col">Item</th><th scope="col">Qty</th><th scope="col">Unit price</th><th scope="col">Amount</th></tr></thead><tbody><tr v-for="item in detail.items" :key="item.id"><td>{{ item.name }}<small>Product ID: {{ item.product_id || 'Unavailable' }}</small></td><td>{{ item.quantity }}</td><td>{{ orderMoney(item.price) }}</td><td>{{ orderMoney(item.total_price) }}</td></tr><tr v-if="!detail.items?.length"><td colspan="4">No item details recorded.</td></tr></tbody></table></div><dl class="totals-list"><div><dt>Subtotal</dt><dd>{{ orderMoney(detail.subtotal) }}</dd></div><div><dt>Discount</dt><dd>{{ orderMoney(detail.discount_amount) }}</dd></div><div><dt>Shipping</dt><dd>{{ orderMoney(detail.shipping_amount) }}</dd></div><div v-if="Number(detail.cod_fee) > 0"><dt>Cash on delivery</dt><dd>{{ orderMoney(detail.cod_fee) }}</dd></div><div><dt>Tax (recorded)</dt><dd>{{ orderMoney(detail.tax_amount) }}</dd></div><div class="grand-total"><dt>Order total</dt><dd>{{ orderMoney(detail.total_amount) }}</dd></div></dl><small>Amounts are as recorded. Tax may already be included in the subtotal.</small></section>
        <section class="detail-section"><h3>Notes</h3><h4>Customer notes</h4><p class="order-notes">{{ detail.notes || 'No customer notes.' }}</p><h4>Internal notes</h4><p class="order-notes">{{ detail.internal_notes || 'No internal notes.' }}</p></section>
        <section v-if="detail?.return_requests?.length" class="detail-section"><h3><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Return requests</h3>
          <div v-for="ret in detail.return_requests" :key="ret.id" class="order-notes">
            <p><span class="payment-badge" :class="ret.status === 'approved' ? 'refunded' : ret.status === 'rejected' ? 'failed' : ''">{{ orderLabel(ret.status) }}</span> <strong>Return request</strong> <small>{{ dateTime(ret.created_at) }}</small></p>
            <ul class="change-preview"><li v-for="line in ret.items" :key="line.id"><strong>{{ line.quantity }} × {{ line.order_item?.name || 'Item unavailable' }}</strong><span>{{ orderMoney(line.order_item ? line.order_item.price * line.quantity : 0) }}</span></li><li v-if="!ret.items?.length"><strong>No items selected</strong><span>requested before item selection was available</span></li></ul>
            <p v-if="ret.reason" class="order-notes">“{{ ret.reason }}”</p>
            <p v-if="ret.status === 'requested' && !ret.items?.length" class="order-notes">This request has no item selection. Reject it so the customer can choose which items to return.</p>
            <p v-if="ret.status === 'requested' && ret.items?.length" class="order-notes">Refund to issue: {{ orderMoney(defaultRefund(detail, ret)) }} <small>({{ isFullyReturned(detail, ret) ? 'full order total, including shipping and COD' : 'selected item prices only; shipping and COD are not refunded' }})</small></p>
            <p v-if="ret.refund_amount" class="order-notes">Refunded: {{ orderMoney(ret.refund_amount) }}</p>
            <p v-if="ret.decision_notes" class="order-notes">Decision note: {{ ret.decision_notes }}</p>
            <form v-if="ret.status === 'requested' && ret.items?.length" class="status-form" @submit.prevent="decideReturn(detail, ret, 'approved')"><div><label :for="'refund-amount-' + ret.id">Refund amount (AED)</label><input :id="'refund-amount-' + ret.id" v-model.number="decisionFor(detail, ret).amount" type="number" min="0.01" step="0.01" required :disabled="busy" /></div><div><label :for="'return-notes-' + ret.id">Decision note (optional)</label><input :id="'return-notes-' + ret.id" v-model="decisionFor(detail, ret).notes" maxlength="1000" :disabled="busy" /></div><label class="confirm-checkbox"><input v-model="decisionFor(detail, ret).received" type="checkbox" required :disabled="busy" /> I confirm the returned item(s) were received and inspected.</label><button type="submit" class="order-button primary" :disabled="busy">{{ busy ? 'Saving…' : 'Approve & refund' }}</button><button type="button" class="order-button destructive" :disabled="busy" @click="decideReturn(detail, ret, 'rejected')">Reject</button></form>
            <button v-else-if="ret.status === 'requested'" type="button" class="order-button destructive" :disabled="busy" @click="decideReturn(detail, ret, 'rejected')">Reject</button>
          </div>
        </section>
        <section class="detail-section"><h3><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Order history</h3><ol class="order-history"><li><strong>Order created</strong><small>{{ dateTime(detail.created_at) }}</small></li><li v-for="entry in detail.status_history" :key="entry.id"><strong>{{ orderLabel(entry.old_status) }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> {{ orderLabel(entry.new_status) }}</strong><small>{{ dateTime(entry.created_at) }}</small><p v-if="entry.notes" class="order-notes">{{ entry.notes }}</p></li></ol></section>
      </div>
      <footer class="drawer-footer"><button type="button" class="order-button" :disabled="busy" @click="closeDrawer">Close</button><button v-if="detail" type="button" class="order-button primary" aria-label="Print invoice or save as PDF" :disabled="exportBusy" @click="printInvoice(detail)"><i class="fa-solid fa-print" aria-hidden="true"></i> Invoice / PDF</button></footer>
    </dialog>

    <AdminAlertDialog ref="confirmDialog" :busy="busy" context-class="orders-workspace" @cancel.prevent="closeConfirmation">
      <form @submit.prevent="confirmChange"><header class="drawer-heading"><div><span class="orders-eyebrow">{{ confirmation.status === 'cancelled' ? 'CONFIRM CANCELLATION' : 'REVIEW STATUS UPDATE' }}</span><h2 id="order-confirm-title">{{ confirmation.status === 'cancelled' ? 'Cancel ' : 'Update ' }}{{ confirmation.targets.length === 1 ? 'order #' + (confirmation.targets[0]?.order_number || confirmation.targets[0]?.id) : confirmation.targets.length + ' orders' }}?</h2></div><button type="button" class="icon-button" :disabled="busy" aria-label="Close confirmation" @click="closeConfirmation"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
        <div class="confirmation-body"><div v-if="actionError" ref="actionFeedback" tabindex="-1" class="order-feedback warning" role="alert">{{ actionError }}</div><ul class="change-preview"><li v-for="order in confirmation.targets" :key="order.id"><strong>#{{ order.order_number || order.id }}</strong><span>{{ orderLabel(order.status) }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> {{ orderLabel(confirmation.status) }}</span></li></ul>
          <p v-if="confirmation.status === 'delivered'" class="cancellation-note">Delivery records the order as delivered, not as paid. If the customer pays cash on delivery, confirm receipt afterwards with "Mark as paid".</p>
          <label for="status-change-notes">{{ confirmation.status === 'cancelled' ? 'Cancellation reason *' : 'History note (optional)' }}</label><textarea id="status-change-notes" v-model="confirmation.notes" rows="3" maxlength="2000" :required="confirmation.status === 'cancelled'" :disabled="busy" placeholder="Add context for the order history"></textarea>
          <template v-if="confirmation.status === 'shipped' && confirmation.targets.length === 1"><label for="shipment-tracking">Tracking number (optional)</label><input id="shipment-tracking" v-model="confirmation.tracking" maxlength="255" :disabled="busy" /></template>
          <template v-if="confirmation.status === 'cancelled'"><p class="cancellation-note">Unshipped stock with recorded sale movements will be restored. Shipped goods will not be restocked automatically. Payment state is unchanged; refunds are handled separately.</p><label class="confirm-checkbox"><input v-model="cancelConfirmed" type="checkbox" required :disabled="busy" /> I confirm cancellation of {{ confirmation.targets.length === 1 ? 'this order' : 'these orders' }}.</label></template>
        </div>
        <footer class="drawer-footer"><button type="button" class="order-button" :disabled="busy" autofocus @click="closeConfirmation">Go back</button><button type="submit" class="order-button" :class="confirmation.status === 'cancelled' ? 'destructive' : 'primary'" :disabled="busy || (confirmation.status === 'cancelled' && !cancelConfirmed)">{{ busy ? 'Updating…' : confirmation.status === 'cancelled' ? 'Confirm cancellation' : 'Confirm update' }}</button></footer>
      </form>
    </AdminAlertDialog>
    <AdminAlertDialog ref="paymentDialog" :busy="busy" context-class="orders-workspace" @cancel.prevent="closePayment">
      <form @submit.prevent="confirmPayment">
        <header class="drawer-heading"><div><h2 id="payment-confirm-title">Confirm payment received</h2><p>Order #{{ paymentOrder?.order_number }}</p></div></header>
        <div class="confirmation-body"><div v-if="actionError" class="order-feedback warning" role="alert">{{ actionError }}</div><p>Record {{ orderMoney(paymentOrder?.total_amount) }} as paid. Payment method: {{ paymentMethodLabel(paymentOrder?.payment_method) }}.</p><p>Confirm receipt using your payment provider or collection records. This records payment receipt; it does not charge the customer.</p><label class="confirm-checkbox"><input v-model="paymentConfirmed" type="checkbox" required :disabled="busy" /> I confirm the full payment was received.</label></div>
        <footer class="drawer-footer"><button type="button" class="order-button" :disabled="busy" autofocus @click="closePayment">Go back</button><button type="submit" class="order-button primary" :disabled="busy || !paymentConfirmed">{{ busy ? 'Saving…' : 'Confirm paid' }}</button></footer>
      </form>
    </AdminAlertDialog>
  </div>
</template>

<script setup>
import AdminAlertDialog from './AdminAlertDialog.vue';
import { notifyAdmin } from './adminAlerts';
import { nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { addressFields, orderColumns, orderLabel, orderMoney, orderStatuses, paymentMethodLabel, paymentStates, useOrders } from './orders.js';

const { filters, orders, loading, error, success, page, perPage, sort, direction, pagination, selectedIds, selected, allSelected, commonTransitions, hasFilters, detail, detailLoading, detailError, busy, actionError, exportBusy, exportError, refresh, resetFilters, sortBy, toggleSelection, toggleAll, loadOrder, closeOrder, updateStatuses, collectPayment, recordRefund, respondReturn, exportOrders, printInvoice } = useOrders(useRoute());

// Refund recording (manual bookkeeping — the money moves with the provider).
const refundForm = reactive({ open: false, amount: null, notes: '' });
function prepareRefund(order) {
  refundForm.open = true;
  refundForm.amount = Number(order.total_amount);
  refundForm.notes = '';
}
async function confirmRefund() {
  if (await recordRefund(detail.value, refundForm.amount, refundForm.notes)) refundForm.open = false;
}

// Return request decisions, keyed by request id.
const returnDecisions = reactive({});

/** Sum of item prices for a return request (shipping and COD excluded). */
function returnRefund(ret) {
  return Number((ret.items || [])
    .reduce((sum, line) => sum + Number(line.order_item?.price ?? 0) * line.quantity, 0)
    .toFixed(2));
}

/** Whether approving this request returns every item on the order. */
function isFullyReturned(order, ret) {
  const returned = new Map();
  for (const request of order?.return_requests || []) {
    if (request.status === 'rejected' || request.id === ret.id) continue;
    for (const line of request.items || []) {
      returned.set(line.order_item_id, (returned.get(line.order_item_id) || 0) + line.quantity);
    }
  }
  for (const line of ret.items || []) {
    returned.set(line.order_item_id, (returned.get(line.order_item_id) || 0) + line.quantity);
  }
  return (order?.items || []).every((item) => (returned.get(item.id) || 0) >= item.quantity);
}

/** Default refund: item prices for a partial return, order balance for a full one. */
function defaultRefund(order, ret) {
  const balance = Number.isFinite(order?.remaining_refundable)
    ? Math.max(0, Number(order.remaining_refundable))
    : Math.max(0, Number(order?.total_amount || 0) - (order?.return_requests || [])
      .filter((request) => request.status === 'approved')
      .reduce((sum, request) => sum + Number(request.refund_amount || 0), 0));
  const amount = isFullyReturned(order, ret) ? balance : Math.min(returnRefund(ret), balance);
  return Number(amount.toFixed(2));
}

function decisionFor(order, ret) {
  if (!returnDecisions[ret.id]) returnDecisions[ret.id] = { amount: defaultRefund(order, ret), notes: '', received: false };
  return returnDecisions[ret.id];
}
async function decideReturn(order, ret, decision) {
  const state = decisionFor(order, ret);
  if (decision === 'approved' && !state.received) {
    notifyAdmin('error', 'Confirm that the returned items were received before issuing the refund.');
    return;
  }
  const payload = decision === 'approved'
    ? { refund_amount: state.amount, decision_notes: state.notes }
    : { decision_notes: state.notes };
  if (await respondReturn(order, ret.id, decision, payload)) delete returnDecisions[ret.id];
}

const paymentConfirmed = ref(false);
const paymentDialog = ref(null);
const paymentOrder = ref(null);
let paymentOpener;
function preparePayment(order) {
  paymentOpener = document.activeElement;
  paymentOrder.value = { ...order };
  paymentConfirmed.value = false;
  actionError.value = '';
  paymentDialog.value.showModal();
}
function closePayment() {
  if (busy.value) return;
  paymentDialog.value.close();
  if (paymentOpener?.isConnected) paymentOpener.focus();
  else if (orderDrawer.value.open) orderDrawer.value.querySelector('button')?.focus();
  else document.getElementById('order-search')?.focus();
}
async function confirmPayment() {
  if (paymentConfirmed.value && await collectPayment(paymentOrder.value)) closePayment();
}
const orderDrawer = ref(null);
const confirmDialog = ref(null);
const actionFeedback = ref(null);
const nextStatus = ref('');
const bulkStatus = ref('');
const cancelConfirmed = ref(false);
const confirmation = ref({ targets: [], status: '', notes: '', tracking: '' });
let detailId;
let detailOpener;
let confirmationOpener;
const orderDate = (value) => value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : 'Not recorded';
const orderTime = (value) => value ? new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
const dateTime = (value) => value ? new Date(value).toLocaleString() : 'Not recorded';
function reviewRowStatus(order, event) {
  const status = event.target.value;
  event.target.value = order.status;
  if (status !== order.status && order.allowed_transitions?.includes(status)) {
    prepareChange([order], status);
  }
}
watch(selectedIds, () => { bulkStatus.value = ''; });
async function openOrder(id) {
  success.value = '';
  detailId = id;
  detailOpener = document.activeElement;
  orderDrawer.value.showModal();
  await reloadOrder();
}
async function reloadOrder() {
  await loadOrder(detailId);
  nextStatus.value = detail.value?.allowed_transitions?.[0] || '';
}
function closeDrawer() {
  if (!closeOrder()) return;
  orderDrawer.value.close();
  if (detailOpener?.isConnected) detailOpener.focus();
  else document.getElementById('order-search')?.focus();
}
function prepareChange(targets, status) {
  if (!targets.length || !status) return;
  confirmationOpener = document.activeElement;
  confirmation.value = { targets: targets.map((order) => ({ id: order.id, order_number: order.order_number, status: order.status })), status, notes: '', tracking: targets.length === 1 ? targets[0].tracking_number || '' : '' };
  cancelConfirmed.value = false;
  actionError.value = '';
  confirmDialog.value.showModal();
}
function closeConfirmation() {
  if (busy.value) return;
  confirmDialog.value.close();
  if (confirmationOpener?.isConnected) confirmationOpener.focus();
  else if (orderDrawer.value.open) orderDrawer.value.querySelector('button')?.focus();
  else document.getElementById('order-search')?.focus();
}
async function confirmChange() {
  const change = confirmation.value;
  if (await updateStatuses(change.targets, change.status, change.notes, change.tracking, cancelConfirmed.value)) {
    nextStatus.value = detail.value?.allowed_transitions?.[0] || '';
    closeConfirmation();
  } else {
    await nextTick();
    actionFeedback.value?.focus();
  }
}
onBeforeUnmount(() => { paymentDialog.value?.close(); confirmDialog.value?.close(); orderDrawer.value?.close(); });
</script>

<style scoped>
.payment-reconcile { display: flex; margin-top: 7px; color: var(--admin-link); }
.orders-workspace .row-status-select { margin-top: 8px; min-width: 128px; min-height: 34px; padding: 5px 8px; font-size: 12px; cursor: pointer; }
.orders-workspace { color: var(--admin-text); font-family: 'Source Sans 3', 'Open Sans', system-ui, sans-serif; font-size: 1rem; line-height: 1.55; padding: .5rem 1rem 2.5rem; max-width: 1700px; margin: auto; }
.orders-heading, .heading-actions, .panel-heading, .orders-pagination, .orders-pagination nav, .drawer-heading, .drawer-footer, .detail-topline { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
.orders-heading { margin-bottom: 1.5rem; }.heading-actions { gap: .65rem; }
.mobile-action-label { display: none; }
.orders-eyebrow { display: inline-flex; align-items: center; gap: .6rem; font-size: .8rem; color: var(--admin-link); font-weight: 700; letter-spacing: .1em; }
.orders-heading h2 { font-size: clamp(1.75rem, 3vw, 2.2rem); font-weight: 700; letter-spacing: -.035em; margin: .4rem 0; }
.orders-heading p, .panel-heading p, .drawer-heading p { margin: 0; color: var(--admin-muted); }
.order-button, .row-button, .icon-button { display: inline-flex; justify-content: center; align-items: center; gap: .55rem; border: 1px solid var(--admin-border); border-radius: 9px; background: var(--admin-surface); color: var(--admin-text); min-height: 42px; padding: .65rem .9rem; font: inherit; font-size: .95rem; font-weight: 600; text-decoration: none; }
.order-button:hover, .row-button:hover, .icon-button:hover { border-color: var(--admin-primary); color: var(--admin-link); }
.order-button.primary { background: var(--admin-primary); border-color: var(--admin-primary); color: #fff; }
.order-button.destructive { background: var(--admin-danger); border-color: var(--admin-danger); color: #000; }
.orders-workspace button:disabled { opacity: .5; cursor: not-allowed; }
.icon-button { width: 42px; flex-shrink: 0; padding: .5rem; }
.orders-panel { background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: 16px; overflow: hidden; margin-bottom: 1.5rem; box-shadow: 0 3px 12px #0b4f8a08; }
.panel-heading { padding: 1.25rem 1.4rem; border-bottom: 1px solid var(--admin-border); }
.panel-heading h3 { font-size: 1.125rem; font-weight: 700; margin: 0 0 .25rem; }.panel-heading p { font-size: .9rem; }
.orders-workspace label { display: block; font-size: .9rem; font-weight: 600; margin-bottom: .4rem; }
.orders-workspace input:not([type="checkbox"]), .orders-workspace select, .orders-workspace textarea { display: block; min-width: 0; width: 100%; min-height: 44px; border: 1px solid var(--admin-border); border-radius: 8px; background: var(--admin-surface); color: var(--admin-text); font: inherit; padding: .65rem .75rem; }
.orders-workspace input[type="checkbox"] { width: 18px; height: 18px; flex-shrink: 0; accent-color: var(--admin-primary); vertical-align: middle; }
.order-filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; padding: 1.4rem; }.order-search-field { grid-column: span 2; }
.date-filters { grid-column: 1 / -1; border-top: 1px solid var(--admin-border); padding-top: .9rem; }.date-filters summary { color: var(--admin-link); font-size: .95rem; font-weight: 600; cursor: pointer; }.date-filters summary i { margin: 0 .4rem; }.date-filters summary span { font-size: .85rem; color: var(--admin-muted); }.date-filter-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; max-width: 650px; padding-top: 1rem; }
.search-control { position: relative; }.search-control > i { position: absolute; top: 50%; transform: translateY(-50%); left: .9rem; color: var(--admin-muted); }.orders-workspace .search-control input { padding-left: 2.5rem; }
.filter-note { font-size: .875rem; color: var(--admin-muted); padding: 0 1.4rem 1.2rem; }.filter-note i { margin-right: .35rem; }
.orders-workspace .page-size { display: flex; align-items: center; gap: .6rem; margin: 0; color: var(--admin-muted); font-weight: 400; }.page-size select { width: 80px; }
.count-badge { display: inline-block; font-size: .875rem; border-radius: 6px; background: var(--admin-inset); color: var(--admin-muted); padding: .15rem .5rem; margin-left: .4rem; }
.bulk-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: .65rem; background: color-mix(in srgb, var(--admin-primary) 6%, var(--admin-surface)); padding: 1rem 1.25rem; border-bottom: 1px solid var(--admin-border); }.bulk-toolbar strong { font-size: .95rem; }.bulk-toolbar select { width: auto; max-width: 240px; }.bulk-note { flex-basis: 100%; color: var(--admin-muted); font-size: .875rem; }
.mobile-sort { display: none; }
.orders-table-wrap { overflow-x: auto; }.orders-table { width: 100%; border-collapse: collapse; }.orders-table th { padding: .9rem 1rem; text-align: left; background: var(--admin-inset); color: var(--admin-muted); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; font-weight: 600; white-space: nowrap; }
.orders-table td { padding: 1rem; border-bottom: 1px solid var(--admin-border); font-size: .95rem; vertical-align: middle; }.orders-table tbody tr:last-child td { border-bottom: 0; }.orders-table .selected { background: color-mix(in srgb, var(--admin-primary) 6%, var(--admin-surface)); }.order-row:hover { background: var(--admin-inset); }
.sort-button { display: inline-flex; gap: .65rem; align-items: center; font: inherit; text-transform: inherit; letter-spacing: inherit; font-weight: inherit; padding: 0; background: transparent; border: 0; color: inherit; }.sort-button i { font-size: .7rem; }.sort-button:hover { color: var(--admin-link); }
.order-link { padding: 0; background: transparent; border: 0; color: var(--admin-link); font: inherit; font-weight: 700; text-align: left; white-space: nowrap; }.order-link:hover { text-decoration: underline; }.orders-table small { display: block; color: var(--admin-muted); font-size: .8rem; margin-top: .25rem; }
.customer-name { display: block; font-weight: 600; }.date-label { white-space: nowrap; }.order-total { font-weight: 700; white-space: nowrap; }.row-actions { display: flex; gap: .4rem; }.row-button { padding: .45rem .6rem; font-size: .875rem; white-space: nowrap; }.row-button.danger { color: var(--admin-danger-text); }
.order-badge { --badge-color: var(--admin-primary); display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .65rem; border-radius: 7px; color: var(--admin-text); background: color-mix(in srgb, var(--badge-color) 11%, var(--admin-surface)); font-size: .875rem; font-weight: 600; white-space: nowrap; }.order-badge.pending { --badge-color: var(--admin-warning); }.order-badge.processing { --badge-color: var(--admin-primary); }.order-badge.shipped { --badge-color: var(--admin-secondary); }.order-badge.delivered { --badge-color: var(--admin-success); }.order-badge.cancelled { --badge-color: var(--admin-danger); }.status-dot { width: 7px; height: 7px; background: var(--badge-color); border-radius: 50%; }
.payment-badge { display: inline-block; font-size: .875rem; color: var(--admin-warning-text); white-space: nowrap; }.payment-badge.paid { color: var(--admin-success-text); }.payment-badge.failed { color: var(--admin-danger-text); }.payment-badge.refunded, .payment-badge.partially_refunded { color: var(--admin-link); }
.orders-pagination { padding: 1rem 1.4rem; border-top: 1px solid var(--admin-border); background: var(--admin-inset); font-size: .9rem; color: var(--admin-muted); }.orders-pagination nav { gap: .75rem; }
.list-state { text-align: center; padding: 3rem 1.25rem !important; color: var(--admin-muted); }.list-state > i { display: block; font-size: 2rem; color: var(--admin-link); margin-bottom: 1rem; }.list-state strong { display: block; color: var(--admin-text); font-size: 1.1rem; }.list-state p { margin: .4rem 0 1rem; }
.order-feedback { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; border: 1px solid var(--admin-border); }.order-feedback > span { flex: 1; }.order-feedback.success { border-color: var(--admin-success); background: color-mix(in srgb, var(--admin-success) 8%, var(--admin-surface)); }.order-feedback.warning { border-color: var(--admin-warning); background: color-mix(in srgb, var(--admin-warning) 9%, var(--admin-surface)); }.export-progress { color: var(--admin-link); margin-bottom: 1rem; }
.order-skeleton { display: block; height: 18px; min-width: 24px; border-radius: 5px; background: linear-gradient(90deg, var(--admin-inset) 20%, var(--admin-border) 50%, var(--admin-inset) 80%); background-size: 200% 100%; animation: order-shimmer 1.4s linear infinite; }.detail-skeleton { padding: 1rem 0; }
.order-drawer { width: min(860px, 100%); max-width: 100%; height: 100dvh; max-height: 100dvh; margin: 0 0 0 auto; padding: 0; border: 1px solid var(--admin-border); border-radius: 18px 0 0 18px; color: var(--admin-text); background: var(--admin-surface); overflow: hidden; }.order-drawer[open] { display: flex; flex-direction: column; }
.order-drawer::backdrop, .order-confirm-dialog::backdrop { background: #0b254780; backdrop-filter: blur(3px); }.drawer-heading { flex-wrap: nowrap; align-items: flex-start; padding: 1.4rem; border-bottom: 1px solid var(--admin-border); flex-shrink: 0; }.drawer-heading h2 { font-size: 1.5rem; font-weight: 700; letter-spacing: -.02em; margin: .4rem 0; overflow-wrap: anywhere; }.drawer-heading p { font-size: .9rem; }
.drawer-scroll { padding: 1.4rem; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }.drawer-footer { padding: 1rem 1.4rem; border-top: 1px solid var(--admin-border); background: var(--admin-inset); justify-content: flex-end; flex-shrink: 0; }
.detail-topline { margin-bottom: 1rem; }.detail-topline > strong { font-size: 1.75rem; }.detail-section { padding: 1.25rem 0; border-top: 1px solid var(--admin-border); min-width: 0; }.detail-section h3 { display: flex; gap: .6rem; align-items: center; font-size: 1.1rem; font-weight: 700; margin: 0 0 1rem; }.detail-section h3 i { color: var(--admin-link); }.detail-section h4 { font-size: .9rem; font-weight: 600; color: var(--admin-muted); }.detail-section p { overflow-wrap: anywhere; }.detail-section small { color: var(--admin-muted); font-size: .875rem; }
.status-form { display: flex; gap: .75rem; align-items: flex-end; margin-bottom: 1rem; }.status-form > div { flex: 1; }.detail-columns, .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }.detail-grid { margin: 1rem 0 0; }.detail-grid dt, .address-list dt { color: var(--admin-muted); font-weight: 400; font-size: .875rem; }.detail-grid dd, .address-list dd { margin: .2rem 0 .7rem; overflow-wrap: anywhere; }
.item-table-wrap { overflow-x: auto; }.item-table { width: 100%; border-collapse: collapse; }.item-table th, .item-table td { padding: .8rem .6rem; text-align: left; border-bottom: 1px solid var(--admin-border); font-size: .9rem; }.item-table th { color: var(--admin-muted); background: var(--admin-inset); white-space: nowrap; }.item-table td:not(:first-child) { white-space: nowrap; }.item-table small { display: block; }.totals-list { max-width: 360px; margin: 1rem 0 1rem auto; }.totals-list > div { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .45rem 0; }.totals-list dt { font-weight: 400; }.totals-list dd { margin: 0; font-weight: 600; }.totals-list .grand-total { border-top: 1px solid var(--admin-border); margin-top: .5rem; padding-top: .9rem; font-size: 1.15rem; }
.order-notes { white-space: pre-wrap; overflow-wrap: anywhere; }.order-history { list-style: none; padding: 0 0 0 1rem; border-left: 2px solid var(--admin-border); margin: 0 0 0 .3rem; }.order-history li { position: relative; padding: 0 0 1.25rem .5rem; }.order-history li::before { content: ''; width: 9px; height: 9px; border-radius: 50%; background: var(--admin-secondary); position: absolute; left: -22px; top: 7px; }.order-history li strong { display: block; font-size: .95rem; }.order-history small { display: block; margin: .3rem 0; }.order-history strong i { margin: 0 .35rem; font-size: .8rem; }
.order-confirm-dialog { width: min(620px, calc(100% - 1.5rem)); max-height: 90dvh; padding: 0; border: 1px solid var(--admin-border); border-radius: 16px; background: var(--admin-surface); color: var(--admin-text); overflow: auto; margin: auto; }.order-confirm-dialog form { margin: 0; }.confirmation-body { padding: 1.4rem; }.change-preview { list-style: none; padding: 0; margin: 0 0 1.25rem; max-height: 160px; overflow-y: auto; }.change-preview li { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; padding: .7rem 0; border-bottom: 1px solid var(--admin-border); font-size: .95rem; }.change-preview span { color: var(--admin-muted); }.change-preview i { margin: 0 .3rem; }.confirmation-body textarea { margin-bottom: 1rem; }.cancellation-note { color: var(--admin-muted); font-size: .9rem; margin: 1rem 0; }.orders-workspace .confirm-checkbox { display: flex; align-items: flex-start; gap: .7rem; background: var(--admin-inset); border: 1px solid var(--admin-border); padding: 1rem; border-radius: 9px; font-weight: 400; }.confirm-checkbox input { margin-top: .2rem; }
.orders-workspace :is(button, input, select, textarea, a, summary):focus-visible { outline: 3px solid var(--admin-primary); outline-offset: 3px; }.spinning { animation: order-spin 1s linear infinite; }@keyframes order-spin { to { transform: rotate(360deg); } }@keyframes order-shimmer { to { background-position: -200% 0; } }
@media (min-width: 768px) and (max-width: 1499px) { .orders-table th, .orders-table td { padding: .85rem .75rem; }.row-actions { width: 155px; flex-wrap: wrap; }.row-button { font-size: .8rem; padding: .4rem .5rem; }.orders-table small, .customer-name { max-width: 170px; overflow-wrap: anywhere; } }
@media (max-width: 1199px) { .order-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }.order-search-field { grid-column: 1 / -1; } }
@media (max-width: 767px) {
  .orders-workspace { padding: .25rem 0 1.5rem; }.heading-actions { width: 100%; }.heading-actions > * { flex: 1; padding: .55rem .7rem; font-size: .875rem; }
  .desktop-action-label { display: none; }.mobile-action-label { display: inline; }
  .order-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 1rem; gap: .85rem; }.panel-heading { padding: 1rem; }.filter-note { padding: 0 1rem 1rem; }.page-size > span { display: none; }
  .mobile-sort { display: flex; flex-wrap: wrap; align-items: center; gap: .65rem; padding: .9rem 1rem; border-bottom: 1px solid var(--admin-border); }.orders-workspace .mobile-sort label { margin: 0; }.mobile-sort select { width: auto; flex: 1; }.mobile-sort .order-button { padding: .6rem; font-size: .875rem; }
  .orders-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }.orders-table, .orders-table tbody { display: block; }.order-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .9rem 1rem; padding: 1.25rem; border-bottom: 1px solid var(--admin-border); }.orders-table .order-row td { min-width: 0; padding: 0; border: 0; }.order-row .selection-cell { grid-column: 1; grid-row: 1; }.order-number-cell { grid-column: 2; grid-row: 1; text-align: right; }.order-link { white-space: normal; overflow-wrap: anywhere; }.order-row td[data-label]::before { content: attr(data-label); display: block; color: var(--admin-muted); font-size: .8rem; margin-bottom: .25rem; }.orders-table small, .customer-name { overflow-wrap: anywhere; }.order-actions-cell { grid-column: 1 / -1; }.row-actions > * { flex: 1; }
  .skeleton-row { display: flex; gap: 1rem; padding: 1.25rem; }.skeleton-row td:nth-child(n+4) { display: none; }.orders-table .skeleton-row td { padding: 0; flex: 1; }
  .orders-pagination { padding: 1rem; }.orders-pagination nav { width: 100%; gap: .5rem; }.orders-pagination .order-button { padding: .55rem .7rem; }.bulk-toolbar { padding: 1rem; }.bulk-toolbar strong { flex-basis: 100%; }.bulk-toolbar select { width: 100%; max-width: none; }
  .order-drawer { border-radius: 0; }.drawer-heading, .drawer-scroll, .confirmation-body { padding: 1rem; }.drawer-footer { padding: 1rem; gap: .6rem; }.drawer-footer > * { flex: 1; }.drawer-heading h2 { font-size: 1.3rem; }.detail-columns, .detail-grid { grid-template-columns: minmax(0, 1fr); gap: .5rem; }.status-form { flex-wrap: wrap; }.status-form > div { flex-basis: 100%; }.detail-topline > strong { font-size: 1.5rem; }.order-confirm-dialog .drawer-footer { position: sticky; bottom: 0; }
}
@media (prefers-reduced-motion: reduce) { .spinning, .order-skeleton { animation: none; } }
</style>
