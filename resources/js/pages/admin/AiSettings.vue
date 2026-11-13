<template>
    <div class="ai-page">
        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card mb-4">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <h3 class="card-title mb-0">AI provider</h3>
                        <span v-if="keyStored" class="badge text-bg-success ms-auto"><i class="bi bi-shield-lock me-1"></i>API key stored</span>
                        <span v-else class="badge text-bg-warning ms-auto">No API key</span>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">
                            The assistant uses an OpenAI-compatible chat completions API. The API key is stored server-side and is never
                            returned to the browser. Numbers always come from your stored records; the model only interprets them.
                        </p>
                        <div class="form-check form-switch mb-3">
                            <input id="ai-enabled" v-model="form.ai_enabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="ai-enabled">
                                {{ form.ai_enabled ? 'AI interpretation enabled' : 'AI disabled — computed briefings are still shown' }}
                            </label>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-provider">Provider</label>
                                <select id="ai-provider" v-model="form.ai_provider" class="form-select">
                                    <option value="openai">OpenAI</option>
                                    <option value="openrouter">OpenRouter</option>
                                    <option value="anthropic">Anthropic-compatible</option>
                                    <option value="google">Google-compatible</option>
                                    <option value="ollama">Ollama / local</option>
                                </select>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-model">Model</label>
                                <input id="ai-model" v-model="form.ai_model" class="form-control" maxlength="255" placeholder="gpt-4o-mini" />
                            </div>
                            <div class="col-md-9 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-base-url">Base URL</label>
                                <input id="ai-base-url" v-model="form.ai_base_url" type="url" class="form-control" maxlength="255" placeholder="https://api.openai.com/v1" />
                                <small class="text-secondary">Use the OpenAI-compatible root, without <code>/chat/completions</code>.</small>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-secondary w-100" @click="applyProviderDefaults">Apply defaults</button>
                            </div>
                            <div class="col-md-9 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-api-key">API key</label>
                                <input
                                    id="ai-api-key"
                                    v-model="form.ai_api_key"
                                    type="password"
                                    class="form-control"
                                    maxlength="512"
                                    autocomplete="new-password"
                                    :placeholder="keyStored ? 'Stored •••••• — paste a new key to replace' : 'Paste the provider API key'"
                                />
                                <small class="text-secondary">Write-only. Leave blank to keep the stored key.</small>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-primary w-100" :disabled="testing" @click="testConnection">
                                    <span v-if="testing" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="bi bi-plug me-1"></i>Test
                                </button>
                            </div>
                        </div>
                        <div v-if="testResult" class="alert mb-0" :class="testResult.ok ? 'alert-success' : 'alert-danger'">
                            <i class="bi" :class="testResult.ok ? 'bi-check-circle' : 'bi-x-circle'"></i>
                            <span class="ms-1">{{ testResult.message }}</span>
                        </div>
                    </div>
                </section>

                <section class="card mb-4">
                    <div class="card-header"><h3 class="card-title">Generation</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-temperature">Temperature</label>
                                <input id="ai-temperature" v-model.number="form.ai_temperature" type="number" min="0" max="2" step="0.1" class="form-control" />
                                <small class="text-secondary">Lower is more factual. 0.2 recommended.</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-max-tokens">Max tokens</label>
                                <input id="ai-max-tokens" v-model.number="form.ai_max_tokens" type="number" min="1" max="32168" class="form-control" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-timeout">Timeout (seconds)</label>
                                <input id="ai-timeout" v-model.number="form.ai_timeout" type="number" min="1" max="120" class="form-control" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-language">Response language</label>
                                <select id="ai-language" v-model="form.ai_response_language" class="form-select">
                                    <option value="en">English</option>
                                    <option value="ar">Arabic</option>
                                    <option value="hi">Hindi</option>
                                    <option value="ur">Urdu</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card mb-4">
                    <div class="card-header"><h3 class="card-title">Analysis windows</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-default-period">Default analysis period</label>
                                <div class="input-group">
                                    <input id="ai-default-period" v-model.number="form.ai_default_analysis_period" type="number" min="1" max="365" class="form-control" />
                                    <span class="input-group-text">days</span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-forecast-period">Forecast window</label>
                                <div class="input-group">
                                    <input id="ai-forecast-period" v-model.number="form.ai_forecasting_period" type="number" min="1" max="365" class="form-control" />
                                    <span class="input-group-text">days</span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-rec-frequency">Recommendation refresh</label>
                                <div class="input-group">
                                    <input id="ai-rec-frequency" v-model.number="form.ai_recommendation_frequency" type="number" min="1" max="720" class="form-control" />
                                    <span class="input-group-text">hours</span>
                                </div>
                            </div>
                        </div>
                        <p class="small text-secondary mb-0">
                            Scheduled report delivery is configured on <router-link to="/admin/ai-reports">AI Reports</router-link>.
                        </p>
                    </div>
                </section>

                <section class="card">
                    <div class="card-header"><h3 class="card-title">Intelligence thresholds</h3></div>
                    <div class="card-body">
                        <p class="text-secondary small">These thresholds drive classifications and recommendations. They are compared against real values only — no estimate is substituted for missing data.</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-margin-threshold">Profit margin warning threshold</label>
                                <div class="input-group">
                                    <input id="ai-margin-threshold" v-model.number="form.ai_profit_margin_warning_threshold" type="number" min="0" max="100" step="0.5" class="form-control" />
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-secondary">Products below this gross margin are flagged.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-decline-threshold">Sales decline warning threshold</label>
                                <div class="input-group">
                                    <input id="ai-decline-threshold" v-model.number="form.ai_sales_decline_warning_threshold" type="number" min="-100" max="100" step="0.5" class="form-control" />
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-secondary">Negative percentage, e.g. −20 flags a 20% drop.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-min-sales">Minimum product sales</label>
                                <div class="input-group">
                                    <input id="ai-min-sales" v-model.number="form.ai_min_product_sales_threshold" type="number" min="0" class="form-control" />
                                    <span class="input-group-text">units</span>
                                </div>
                                <small class="text-secondary">Used to separate slow-moving products.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-uppercase small fw-semibold" for="ai-low-stock">Low stock threshold</label>
                                <div class="input-group">
                                    <input id="ai-low-stock" v-model.number="form.ai_low_stock_threshold" type="number" min="0" class="form-control" />
                                    <span class="input-group-text">units</span>
                                </div>
                                <small class="text-secondary">Global fallback when a product has no per-SKU threshold.</small>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="card mb-4">
                    <div class="card-header"><h3 class="card-title">Privacy &amp; safety</h3></div>
                    <div class="card-body small">
                        <ul class="ai-safety-list">
                            <li><i class="bi bi-shield-check"></i><div><strong>Fixed tool whitelist</strong><span>The assistant can only call predefined analytics tools. It cannot run SQL or read arbitrary tables.</span></div></li>
                            <li><i class="bi bi-calendar-range"></i><div><strong>Bounded date windows</strong><span>Chat requests are limited to validated date ranges; out-of-range tool calls are rejected.</span></div></li>
                            <li><i class="bi bi-person-lock"></i><div><strong>Admin only</strong><span>Every endpoint sits behind admin authorization.</span></div></li>
                            <li><i class="bi bi-calculator"></i><div><strong>Computed figures</strong><span>Revenue, margin and stock numbers are calculated in Laravel, never generated by the model.</span></div></li>
                            <li><i class="bi bi-key"></i><div><strong>Secret handling</strong><span>The API key is stored as a secret and never included in API responses.</span></div></li>
                        </ul>
                    </div>
                </section>

                <section class="card mb-4">
                    <div class="card-header"><h3 class="card-title">How fallback works</h3></div>
                    <div class="card-body small text-secondary">
                        <p>When no key is configured, AI is disabled, or the provider errors, the module still shows a deterministic computed briefing on every analysis page.</p>
                        <p class="mb-0">Recommendations, classifications, forecasts and reports are always generated locally from stored data.</p>
                    </div>
                </section>
            </div>
        </div>

        <div class="card-footer sticky-save d-flex flex-wrap align-items-center gap-3">
            <span v-if="dirty" class="text-warning fw-semibold"><i class="bi bi-exclamation-circle me-1"></i>Unsaved changes</span>
            <span v-else class="text-secondary small"><i class="bi bi-check-circle me-1"></i>AI settings are saved.</span>
            <span v-if="saveError" class="text-danger small">{{ saveError }}</span>
            <button type="button" class="btn btn-primary ms-auto" :disabled="saving" @click="save">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-check2-circle me-1"></i>
                {{ saving ? 'Saving…' : 'Save AI settings' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';

const providerDefaults = {
    openai: { base: 'https://api.openai.com/v1', model: 'gpt-4o-mini' },
    openrouter: { base: 'https://openrouter.ai/api/v1', model: 'openai/gpt-4o-mini' },
    anthropic: { base: 'https://api.anthropic.com/v1', model: 'claude-3-5-haiku-latest' },
    google: { base: 'https://generativelanguage.googleapis.com/v1beta/openai', model: 'gemini-2.0-flash' },
    ollama: { base: 'http://localhost:11434/v1', model: 'llama3.1' },
};

const form = ref({
    ai_provider: 'openai',
    ai_base_url: 'https://api.openai.com/v1',
    ai_model: 'gpt-4o-mini',
    ai_api_key: '',
    ai_enabled: true,
    ai_temperature: 0.2,
    ai_max_tokens: 2048,
    ai_timeout: 30,
    ai_response_language: 'en',
    ai_default_analysis_period: 30,
    ai_forecasting_period: 30,
    ai_recommendation_frequency: 24,
    ai_profit_margin_warning_threshold: 15,
    ai_sales_decline_warning_threshold: -20,
    ai_min_product_sales_threshold: 5,
    ai_low_stock_threshold: 10,
});

const keyStored = ref(false);
const saving = ref(false);
const testing = ref(false);
const testResult = ref(null);
const saveError = ref('');

let savedSnapshot = '';
const dirty = computed(() => JSON.stringify(form.value) !== savedSnapshot);

function snapshot() {
    savedSnapshot = JSON.stringify(form.value);
}

function warnBeforeLeave(event) {
    if (!dirty.value) return;
    event.preventDefault();
    event.returnValue = '';
}

function load() {
    axios.get('/api/ai/settings').then(({ data }) => {
        keyStored.value = Boolean(data.ai_api_key_set);
        form.value = {
            ai_provider: data.ai_provider || 'openai',
            ai_base_url: data.ai_base_url || 'https://api.openai.com/v1',
            ai_model: data.ai_model || 'gpt-4o-mini',
            ai_api_key: '',
            ai_enabled: data.ai_enabled !== false,
            ai_temperature: Number(data.ai_temperature ?? 0.2),
            ai_max_tokens: Number(data.ai_max_tokens ?? 2048),
            ai_timeout: Number(data.ai_timeout ?? 30),
            ai_response_language: data.ai_response_language || 'en',
            ai_default_analysis_period: Number(data.ai_default_analysis_period ?? 30),
            ai_forecasting_period: Number(data.ai_forecasting_period ?? 30),
            ai_recommendation_frequency: Number(data.ai_recommendation_frequency ?? 24),
            ai_profit_margin_warning_threshold: Number(data.ai_profit_margin_warning_threshold ?? 15),
            ai_sales_decline_warning_threshold: Number(data.ai_sales_decline_warning_threshold ?? -20),
            ai_min_product_sales_threshold: Number(data.ai_min_product_sales_threshold ?? 5),
            ai_low_stock_threshold: Number(data.ai_low_stock_threshold ?? 10),
        };
        snapshot();
    }).catch(() => {
        saveError.value = 'Could not load AI settings.';
    });
}

function applyProviderDefaults() {
    const preset = providerDefaults[form.value.ai_provider];
    if (!preset) return;
    form.value.ai_base_url = preset.base;
    form.value.ai_model = preset.model;
}

async function save() {
    saving.value = true;
    saveError.value = '';
    testResult.value = null;
    try {
        await axios.post('/api/ai/settings', { ...form.value });
        if (form.value.ai_api_key) keyStored.value = true;
        form.value.ai_api_key = '';
        snapshot();
    } catch (e) {
        const errors = e?.response?.data?.errors;
        saveError.value = errors ? Object.values(errors)[0]?.[0] : (e?.response?.data?.message || 'Could not save AI settings.');
    } finally {
        saving.value = false;
    }
}

async function testConnection() {
    testing.value = true;
    testResult.value = null;
    try {
        const { data } = await axios.post('/api/ai/settings/test');
        testResult.value = { ok: Boolean(data.ok), message: data.message || 'Connection test finished.' };
    } catch (e) {
        testResult.value = { ok: false, message: e?.response?.data?.message || 'Connection failed.' };
    } finally {
        testing.value = false;
    }
}

onMounted(() => {
    load();
    window.addEventListener('beforeunload', warnBeforeLeave);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeLeave);
});
</script>

<style scoped>
.ai-page { max-width: 1400px; }
.ai-safety-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .9rem; }
.ai-safety-list li { display: flex; gap: .6rem; }
.ai-safety-list i { color: var(--admin-primary); font-size: 1rem; margin-top: .1rem; }
.ai-safety-list div { display: flex; flex-direction: column; gap: .15rem; }
.ai-safety-list span { color: var(--admin-muted); }
.sticky-save { position: sticky; bottom: 0; z-index: 2; background: var(--admin-inset); border: 1px solid var(--admin-border); border-radius: 10px; padding: .75rem 1rem; }
</style>
