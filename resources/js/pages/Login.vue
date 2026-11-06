<template>
  <div class="login-page bg-body-secondary">
    <main class="login-box">
      <h1 class="login-logo">
        <router-link to="/admin"><b>Nexus</b>Peptides</router-link>
      </h1>
      <div class="card">
        <div class="card-body login-card-body">
          <p class="login-box-msg">Sign in to your admin panel</p>

          <div v-if="error" class="alert alert-danger py-2 fs-7" role="alert">{{ error }}</div>

          <form @submit.prevent="login">
            <label class="visually-hidden" for="loginEmail">Email</label>
            <div class="input-group mb-3">
              <input id="loginEmail" v-model="form.email" type="email" class="form-control" placeholder="Email" autocomplete="username" required />
              <div class="input-group-text"><span class="bi bi-envelope"></span></div>
            </div>

            <label class="visually-hidden" for="loginPassword">Password</label>
            <div class="input-group mb-3">
              <input id="loginPassword" v-model="form.password" type="password" class="form-control" placeholder="Password" autocomplete="current-password" required />
              <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
            </div>

            <div class="row">
              <div class="col-7">
                <div class="form-check">
                  <input id="loginRemember" class="form-check-input" type="checkbox" />
                  <label class="form-check-label" for="loginRemember">Remember Me</label>
                </div>
              </div>
              <div class="col-5">
                <button type="submit" class="btn btn-primary w-100" :disabled="loading">
                  {{ loading ? 'Signing in…' : 'Sign In' }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const router = useRouter();
const form = ref({ email: '', password: '' });
const error = ref('');
const loading = ref(false);

async function login() {
  loading.value = true;
  error.value = '';
  try {
    const r = await axios.post('/api/login', form.value);
    if (r.data?.user?.role !== 'admin') {
      error.value = 'This account does not have admin access.';
      return;
    }
    localStorage.setItem('token', r.data.access_token);
    localStorage.setItem('user', JSON.stringify(r.data.user));
    router.push('/admin');
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid credentials';
  } finally {
    loading.value = false;
  }
}
</script>
