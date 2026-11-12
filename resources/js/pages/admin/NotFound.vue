<template>
    <div class="card">
        <div class="card-body text-center py-5">
            <p class="display-3 fw-bold mb-0 text-primary-emphasis">404</p>
            <h3 class="mt-2">Page not found</h3>
            <p class="text-secondary small mx-auto" style="max-width: 26rem;">
                The page you're looking for doesn't exist or may have been moved. Check the URL, or pick one of
                the options below.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                <router-link to="/admin" class="btn btn-primary px-4">
                    <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i> Go to dashboard
                </router-link>
                <button type="button" class="btn btn-outline-secondary px-4" @click="goBack">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Go back
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();

function goBack() {
    if (window.history.length > 1) router.back();
    else router.push('/admin');
}
</script>
