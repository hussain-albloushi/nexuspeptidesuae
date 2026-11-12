<script setup>
import { computed, ref } from 'vue';
import { useContentStore } from '../stores/content';
import { swalToast } from '../services/alerts';

const content = useContentStore();
const help = computed(() => content.help);
const site = computed(() => content.site);

const form = ref({
    firstName: '',
    lastName: '',
    email: '',
    mobile: '',
    subject: '',
    message: '',
});

const openFaq = ref(0);

function submit() {
    const f = form.value;
    if (!f.firstName || !f.lastName || !f.email || !f.message) {
        swalToast('Please complete all required fields.', 'error');
        return;
    }
    form.value = { firstName: '', lastName: '', email: '', mobile: '', subject: '', message: '' };
    swalToast(`Your message has been sent to ${site.value.name}.`);
}
</script>

<template>
    <div>
        <section class="wd-page-title py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-white/60">
                    <router-link to="/" class="hover:text-white">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-white">Help Center</span>
                </nav>
                <h1 class="text-3xl text-white md:text-4xl">{{ help.title }}</h1>
            </div>
        </section>

        <section class="wd-container py-14 md:py-16">
            <div class="grid gap-12 lg:grid-cols-2 lg:gap-16">
                <!-- Contact details + form -->
                <div>
                    <p class="leading-relaxed text-ink">
                        {{ help.intro }}
                    </p>

                    <div class="mt-8 grid gap-6 sm:grid-cols-2">
                        <div class="rounded-card border border-line p-5">
                            <h3 class="font-widget text-base text-muted">Support Email</h3>
                            <a
                                :href="`mailto:${site.supportEmail}`"
                                class="mt-2 block text-sm text-navy hover:text-secondary"
                            >
                                {{ site.supportEmail }}
                            </a>
                        </div>

                        <div class="rounded-card border border-line p-5">
                            <h3 class="font-widget text-base text-muted">
                                {{ help.companyName }}
                            </h3>
                            <address class="mt-2 text-sm not-italic text-ink">
                                <span v-for="line in site.address" :key="line" class="block">
                                    {{ line }}
                                </span>
                            </address>
                        </div>
                    </div>

                    <!-- Form -->
                    <form class="mt-8 space-y-5" @submit.prevent="submit">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-ink" for="first">
                                    First Name
                                </label>
                                <input id="first" v-model="form.firstName" type="text" class="wd-field" required />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-ink" for="last">
                                    Last Name
                                </label>
                                <input id="last" v-model="form.lastName" type="text" class="wd-field" required />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-ink" for="cemail">
                                    Email
                                </label>
                                <input id="cemail" v-model="form.email" type="email" class="wd-field" required />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-ink" for="mobile">
                                    Mobile
                                </label>
                                <input id="mobile" v-model="form.mobile" type="tel" class="wd-field" />
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="subject">
                                Subject
                            </label>
                            <input id="subject" v-model="form.subject" type="text" class="wd-field" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="message">
                                Message
                            </label>
                            <textarea id="message" v-model="form.message" rows="5" class="wd-field" required></textarea>
                        </div>

                        <button type="submit" class="wd-btn">Send to {{ site.name }}</button>
                    </form>
                </div>

                <!-- FAQ -->
                <div>
                    <h2 class="text-3xl">{{ help.faqTitle }}</h2>
                    <p class="mt-4 leading-relaxed text-ink">
                        {{ help.faqIntro }}
                    </p>

                    <div class="mt-8 divide-y divide-line border-y border-line">
                        <div v-for="(f, i) in help.faqs" :key="f.q">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 py-5 text-left"
                                :aria-expanded="openFaq === i"
                                @click="openFaq = openFaq === i ? -1 : i"
                            >
                                <span class="font-semibold text-ink">{{ f.q }}</span>
                                <i
                                    class="fa-solid text-primary transition-transform"
                                    :class="openFaq === i ? 'fa-minus' : 'fa-plus'"
                                ></i>
                            </button>

                            <div v-show="openFaq === i" class="pb-5 pr-8 leading-relaxed text-ink">
                                {{ f.a }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
