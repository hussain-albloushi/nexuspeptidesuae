<template>
  <div ref="content" v-show="visible" class="admin-alert-dialog-content" :class="contextClass"><slot /></div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { adminAlertOptions, Swal } from './adminAlerts';

defineOptions({ inheritAttrs: false });
const props = defineProps({ busy: Boolean, contextClass: String, label: { type: String, default: 'Confirm action' } });
const emit = defineEmits(['cancel']);
const content = ref(null);
const visible = ref(false);
let placeholder;
let parent;
let popup;

async function showModal() {
    if (visible.value) return;
    visible.value = true;
    await nextTick();
    parent = content.value.parentNode;
    placeholder = document.createComment('admin confirmation');
    parent.insertBefore(placeholder, content.value);
    await Swal.fire(adminAlertOptions({
        html: content.value,
        showConfirmButton: false,
        returnFocus: false,
        width: 640,
        customClass: { container: 'admin-swal-container', popup: 'admin-swal-popup admin-swal-form', htmlContainer: 'admin-swal-form-body' },
        allowOutsideClick: false,
        allowEscapeKey: () => {
            if (!props.busy) emit('cancel', new Event('cancel', { cancelable: true }));
            return false;
        },
        didOpen: (element) => {
            popup = element;
            const title = element.querySelector('h2');
            if (title?.id) element.setAttribute('aria-labelledby', title.id);
            else element.setAttribute('aria-label', props.label);
            element.querySelector('[autofocus]')?.focus();
        },
        willClose: () => {
            if (placeholder?.parentNode && content.value) {
                placeholder.parentNode.insertBefore(content.value, placeholder);
                placeholder.remove();
            }
            visible.value = false;
            popup = null;
        },
    }));
}

function close() {
    if (popup && Swal.getPopup() === popup) Swal.close();
}
onBeforeUnmount(close);
defineExpose({ showModal, close, get open() { return visible.value; } });
</script>
