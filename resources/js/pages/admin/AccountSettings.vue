<template>
  <div>
    <section class="card mb-4">
      <div class="card-header"><h3 class="card-title">Profile</h3></div>
      <div class="card-body">
        <p class="text-secondary small">
          The name and email shown in the admin header and used to sign in.
        </p>
        <form @submit.prevent="saveProfile">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="accountName">Name *</label>
              <input id="accountName" v-model="profile.name" required maxlength="255" class="form-control" autocomplete="name" />
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="accountEmail">Email *</label>
              <input id="accountEmail" v-model="profile.email" type="email" required maxlength="255" class="form-control" autocomplete="email" />
              <div class="form-text">Changing your email signs you out of verification until you confirm the new address.</div>
            </div>
          </div>
          <div v-if="profile.error" class="alert alert-danger" role="alert">{{ profile.error }}</div>
          <div v-if="profile.success" class="alert alert-success" role="alert">{{ profile.success }}</div>
          <button type="submit" class="btn btn-primary" :disabled="profile.saving">
            {{ profile.saving ? 'Saving…' : 'Save profile' }}
          </button>
        </form>
      </div>
    </section>

    <section class="card mb-4">
      <div class="card-header"><h3 class="card-title">Change password</h3></div>
      <div class="card-body">
        <form @submit.prevent="savePassword">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="currentPassword">Current password *</label>
              <input id="currentPassword" v-model="password.current_password" type="password" required class="form-control" autocomplete="current-password" />
            </div>
            <div class="col-md-6 mb-3"></div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="newPassword">New password *</label>
              <input id="newPassword" v-model="password.password" type="password" required minlength="8" class="form-control" autocomplete="new-password" />
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="confirmPassword">Confirm new password *</label>
              <input id="confirmPassword" v-model="password.password_confirmation" type="password" required minlength="8" class="form-control" autocomplete="new-password" />
            </div>
          </div>
          <div v-if="password.error" class="alert alert-danger" role="alert">{{ password.error }}</div>
          <div v-if="password.success" class="alert alert-success" role="alert">{{ password.success }}</div>
          <button type="submit" class="btn btn-primary" :disabled="password.saving">
            {{ password.saving ? 'Saving…' : 'Update password' }}
          </button>
        </form>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, reactive } from 'vue';
import axios from 'axios';

const profile = reactive({ name: '', email: '', saving: false, error: '', success: '' });
const password = reactive({ current_password: '', password: '', password_confirmation: '', saving: false, error: '', success: '' });

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/me');
    profile.name = data.name || '';
    profile.email = data.email || '';
  } catch {
    profile.error = 'Unable to load your account details.';
  }
});

async function saveProfile() {
  profile.saving = true;
  profile.error = '';
  profile.success = '';
  try {
    const { data } = await axios.put('/api/me', { name: profile.name, email: profile.email });
    profile.name = data.name;
    profile.email = data.email;
    const stored = JSON.parse(localStorage.getItem('user') || '{}');
    localStorage.setItem('user', JSON.stringify({ ...stored, name: data.name, email: data.email }));
    profile.success = 'Profile updated.';
  } catch (err) {
    profile.error = errorMessage(err);
  } finally {
    profile.saving = false;
  }
}

async function savePassword() {
  password.saving = true;
  password.error = '';
  password.success = '';
  try {
    const { data } = await axios.put('/api/me/password', {
      current_password: password.current_password,
      password: password.password,
      password_confirmation: password.password_confirmation,
    });
    Object.assign(password, { current_password: '', password: '', password_confirmation: '' });
    password.success = data.message || 'Password updated.';
  } catch (err) {
    password.error = errorMessage(err);
  } finally {
    password.saving = false;
  }
}

function errorMessage(err) {
  const data = err?.response?.data;
  if (data?.errors) return Object.values(data.errors).flat().join(' ');
  return data?.message || 'Something went wrong. Please try again.';
}
</script>
