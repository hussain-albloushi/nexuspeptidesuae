import Swal from 'sweetalert2';

const base = {
    buttonsStyling: false,
    customClass: {
        confirmButton: 'wd-btn wd-swal-confirm',
        cancelButton: 'wd-btn wd-swal-confirm',
        popup: 'wd-swal-popup',
    },
};

/** Full dialog (success / error / info). */
export function swal(options = {}) {
    return Swal.fire({ ...base, ...options });
}

/** Non-blocking toast in the site's primary colour. */
export function swalToast(title, icon = 'success') {
    return Swal.fire({
        ...base,
        title,
        icon,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2200,
        timerProgressBar: true,
        toast: true,
        customClass: {
            ...base.customClass,
            popup: 'wd-swal-popup swal2-toast',
        },
    });
}

/** Confirm/cancel prompt. Resolves true when confirmed. */
export function swalConfirm(title, text, confirmText = 'Yes, do it') {
    return Swal.fire({
        ...base,
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel',
    }).then((result) => Boolean(result.isConfirmed));
}

export default swal;
