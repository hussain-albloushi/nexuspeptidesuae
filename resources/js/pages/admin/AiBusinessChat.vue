<template>
    <div class="ai-page">
        <div class="row g-3">
            <div class="col-lg-3">
                <section class="card ai-chat-sidebar h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <h3 class="card-title mb-0">Conversations</h3>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" @click="newConversation">
                            <i class="bi bi-plus-lg me-1"></i>New
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div v-if="loadingConversations" class="p-3 text-secondary small"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
                        <div
                            v-for="conversation in conversations"
                            :key="conversation.id"
                            class="ai-conversation-item"
                            :class="{ active: currentConversationId === conversation.id }"
                        >
                            <button type="button" class="ai-conversation-open" @click="openConversation(conversation.id)">
                                <span class="ai-conversation-title">{{ conversation.title }}</span>
                                <small class="text-secondary">{{ conversation.messages_count }} message(s)</small>
                            </button>
                            <button type="button" class="ai-delete" :aria-label="`Delete ${conversation.title}`" @click="removeConversation(conversation)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <p v-if="!conversations.length && !loadingConversations" class="text-secondary small p-3 mb-0">
                            No conversations yet. Ask your first question on the right.
                        </p>
                    </div>
                </section>
            </div>

            <div class="col-lg-9">
                <section class="card ai-chat-panel">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <h3 class="card-title mb-0">AI Business Chat</h3>
                        <span class="badge text-bg-light"><i class="bi bi-shield-check me-1"></i>Read-only analytics tools</span>
                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <label class="small text-secondary mb-0" for="chat-period">Window</label>
                            <select id="chat-period" v-model="period" class="form-select form-select-sm" style="max-width: 150px;">
                                <option v-for="preset in periods" :key="preset.value" :value="preset.value">{{ preset.label }}</option>
                                <option value="custom">Custom</option>
                            </select>
                            <template v-if="period === 'custom'">
                                <input v-model="start" type="date" class="form-control form-control-sm" style="max-width: 150px;" aria-label="Analysis start date" />
                                <input v-model="end" type="date" class="form-control form-control-sm" style="max-width: 150px;" aria-label="Analysis end date" />
                            </template>
                        </div>
                    </div>

                    <div ref="messagesEl" class="ai-chat-messages">
                        <div v-if="!messages.length" class="ai-chat-empty">
                            <span class="ai-chat-empty-icon"><i class="bi bi-stars"></i></span>
                            <h4 class="h5">Ask about your business</h4>
                            <p class="text-secondary">The assistant answers with figures computed from your stored orders, products and expenses. It never runs arbitrary SQL.</p>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <button v-for="prompt in suggestedPrompts" :key="prompt" type="button" class="btn btn-sm btn-outline-primary" @click="askSuggested(prompt)">
                                    {{ prompt }}
                                </button>
                            </div>
                        </div>

                        <div v-for="message in messages" :key="message.key" class="ai-message" :class="message.role === 'user' ? 'ai-message-user' : 'ai-message-assistant'">
                            <div class="ai-message-avatar">
                                <i class="bi" :class="message.role === 'user' ? 'bi-person' : (message.error ? 'bi-exclamation-triangle' : 'bi-stars')"></i>
                            </div>
                            <div class="ai-message-body" :class="{ 'ai-message-error': message.error }">
                                <template v-if="message.role === 'user'">
                                    <p class="mb-0">{{ message.content }}</p>
                                </template>
                                <template v-else>
                                    <AiMarkdown :content="message.content" />
                                    <div v-if="message.tools_used?.length" class="d-flex flex-wrap gap-1 mt-2">
                                        <span v-for="tool in message.tools_used" :key="tool" class="badge text-bg-light"><i class="bi bi-wrench me-1"></i>{{ titleCase(tool) }}</span>
                                    </div>
                                    <small v-if="message.created_at" class="text-secondary d-block mt-2">{{ formatDateTime(message.created_at) }}</small>
                                </template>
                            </div>
                        </div>

                        <div v-if="sending" class="ai-message ai-message-assistant">
                            <div class="ai-message-avatar"><i class="bi bi-stars"></i></div>
                            <div class="ai-message-body text-secondary">
                                <span class="spinner-border spinner-border-sm me-2"></span>Analysing your data…
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div v-if="configurationError" class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-3">
                            <i class="bi bi-key"></i>
                            <span class="flex-grow-1">{{ configurationError }}</span>
                            <router-link to="/admin/ai-settings" class="btn btn-sm btn-warning">Open AI Settings</router-link>
                        </div>

                        <form class="d-flex align-items-end gap-2" @submit.prevent="send">
                            <label class="visually-hidden" for="chat-question">Your question</label>
                            <textarea
                                id="chat-question"
                                v-model="question"
                                class="form-control"
                                rows="2"
                                maxlength="2000"
                                placeholder="Ask about revenue, products, profit, customers or inventory…"
                                :disabled="sending"
                                @keydown.enter.exact.prevent="send"
                            ></textarea>
                            <button type="submit" class="btn btn-primary" :disabled="sending || !question.trim()">
                                <i class="bi bi-send me-1"></i>Send
                            </button>
                        </form>
                        <small class="text-secondary d-block mt-2">
                            Answers are generated from computed analytics; verify important decisions. Press Enter to send, Shift+Enter for a new line.
                        </small>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue';
import axios from 'axios';
import AiMarkdown from './AiMarkdown.vue';
import { AI_PERIODS, aiErrorMessage, formatDateTime, titleCase } from './ai.js';

const periods = AI_PERIODS;
const period = ref('30d');
const start = ref('');
const end = ref('');

const conversations = ref([]);
const loadingConversations = ref(true);
const currentConversationId = ref(null);
const messages = ref([]);
const question = ref('');
const sending = ref(false);
const configurationError = ref('');
const messagesEl = ref(null);

const suggestedPrompts = [
    'How did revenue change versus the previous period?',
    'Which products have the lowest margin?',
    'Where should I focus to increase profit?',
    'What is my current stock risk?',
];

let localId = 0;

function addMessage(message) {
    messages.value.push({ key: `local-${++localId}`, tools_used: [], ...message });
}

async function scrollToBottom() {
    await nextTick();
    if (messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
}

async function loadConversations() {
    loadingConversations.value = true;
    try {
        const { data } = await axios.get('/api/ai/chats');
        conversations.value = data?.conversations || [];
    } catch {
        conversations.value = [];
    } finally {
        loadingConversations.value = false;
    }
}

function newConversation() {
    currentConversationId.value = null;
    messages.value = [];
    question.value = '';
    configurationError.value = '';
}

async function openConversation(id) {
    configurationError.value = '';
    try {
        const { data } = await axios.get(`/api/ai/chats/${id}`);
        currentConversationId.value = id;
        messages.value = (data?.messages || []).map((message) => ({
            key: `remote-${message.id}`,
            role: message.role,
            content: message.content,
            tools_used: message.tools_used || [],
            created_at: message.created_at,
        }));
        await scrollToBottom();
    } catch {
        configurationError.value = 'Could not open that conversation.';
    }
}

async function removeConversation(conversation) {
    try {
        await axios.delete(`/api/ai/chats/${conversation.id}`);
        conversations.value = conversations.value.filter((item) => item.id !== conversation.id);
        if (currentConversationId.value === conversation.id) {
            newConversation();
        }
    } catch {
        configurationError.value = 'Could not delete that conversation.';
    }
}

function askSuggested(prompt) {
    question.value = prompt;
    void send();
}

async function send() {
    const text = question.value.trim();
    if (!text || sending.value) return;
    if (period.value === 'custom' && (!start.value || !end.value)) {
        configurationError.value = 'Choose both dates for a custom analysis window.';
        return;
    }

    configurationError.value = '';
    sending.value = true;
    addMessage({ role: 'user', content: text });
    question.value = '';
    await scrollToBottom();

    const payload = { question: text };
    if (currentConversationId.value) payload.conversation_id = currentConversationId.value;
    if (period.value === 'custom') {
        payload.start = start.value;
        payload.end = end.value;
    } else {
        payload.period = period.value;
    }

    try {
        const { data } = await axios.post('/api/ai/chat', payload);
        currentConversationId.value = data.conversation_id || currentConversationId.value;
        addMessage({
            role: 'assistant',
            content: data.answer || 'No answer returned.',
            tools_used: data.tools_used || [],
            created_at: data.generated_at,
        });
        await loadConversations();
    } catch (e) {
        const response = e?.response;
        if (response?.data?.conversation_id) {
            currentConversationId.value = response.data.conversation_id;
        }
        if (response?.status === 422 && response?.data?.error === 'ai_not_configured') {
            configurationError.value = response.data.message || 'AI is not configured. Add an API key under AI Settings.';
        } else {
            configurationError.value = aiErrorMessage(response);
        }
        addMessage({ role: 'assistant', content: configurationError.value, error: true });
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
}

onMounted(loadConversations);
</script>

<style scoped>
.ai-page { max-width: 1500px; }
.ai-chat-sidebar { position: sticky; top: 1rem; max-height: calc(100vh - 2rem); overflow-y: auto; }
.ai-conversation-item { display: flex; align-items: stretch; border-bottom: 1px solid var(--admin-border); position: relative; }
.ai-conversation-item:hover, .ai-conversation-item.active { background: var(--admin-inset); }
.ai-conversation-open { display: flex; flex-direction: column; align-items: flex-start; gap: .15rem; width: 100%; text-align: left; border: 0; background: transparent; padding: .7rem 2.2rem .7rem .9rem; }
.ai-conversation-title { font-size: .85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.ai-delete { position: absolute; right: .5rem; top: .6rem; border: 0; background: transparent; color: var(--admin-muted); padding: .1rem .25rem; opacity: 0; transition: opacity .15s ease; }
.ai-conversation-item:hover .ai-delete, .ai-conversation-item.active .ai-delete { opacity: 1; }
.ai-delete:hover { color: var(--admin-danger, #b42318); }
.ai-chat-panel { display: flex; flex-direction: column; min-height: calc(100vh - 12rem); }
.ai-chat-messages { flex: 1; overflow-y: auto; padding: 1.2rem; min-height: 380px; max-height: calc(100vh - 26rem); display: flex; flex-direction: column; gap: 1rem; }
.ai-chat-empty { text-align: center; max-width: 560px; margin: auto; }
.ai-chat-empty-icon { display: inline-grid; place-items: center; width: 52px; height: 52px; border-radius: 16px; background: color-mix(in srgb, var(--admin-primary) 12%, var(--admin-surface)); color: var(--admin-primary); font-size: 1.5rem; margin-bottom: .75rem; }
.ai-message { display: flex; gap: .75rem; align-items: flex-start; }
.ai-message-user { flex-direction: row-reverse; }
.ai-message-avatar { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; background: var(--admin-inset); color: var(--admin-muted); flex-shrink: 0; }
.ai-message-assistant .ai-message-avatar { background: color-mix(in srgb, var(--admin-primary) 12%, var(--admin-surface)); color: var(--admin-primary); }
.ai-message-body { background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: 12px; padding: .75rem 1rem; max-width: min(760px, 85%); font-size: .9rem; }
.ai-message-user .ai-message-body { background: var(--admin-primary); border-color: var(--admin-primary); color: #fff; }
.ai-message-error { border-color: var(--admin-warning, #c27803); background: color-mix(in srgb, var(--admin-warning, #c27803) 8%, var(--admin-surface)); }
</style>
