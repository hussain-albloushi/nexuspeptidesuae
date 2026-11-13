// Purchase-order money math, kept out of the component so it can be unit tested.
// The server recomputes every total on save; these values are display-only.

export function lineTotal(row) {
    const quantity = Number(row?.quantity) || 0;
    const unitCost = Number(row?.unit_cost) || 0;
    const discount = Number(row?.discount) || 0;
    const tax = Number(row?.tax) || 0;

    return Math.max(0, quantity * unitCost - discount) + tax;
}

export function orderTotal(items = [], charges = {}) {
    return items.reduce((sum, row) => sum + lineTotal(row), 0)
        + (Number(charges.shipping_cost) || 0)
        + (Number(charges.customs_cost) || 0)
        + (Number(charges.additional_charges) || 0);
}
