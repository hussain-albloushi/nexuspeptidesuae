<script setup>
import { computed, ref } from 'vue';
import { useContentStore } from '../stores/content';
import { swalToast } from '../services/alerts';

const content = useContentStore();
const site = computed(() => content.site);

/**
 * Copyright line with a live year so the footer never goes stale.
 * Accepts an explicit {{year}} / {year} placeholder; otherwise the first
 * 4-digit year in the stored text is swapped for the current one.
 */
const copyright = computed(() => {
    const text = site.value.copyright || '';
    const year = String(new Date().getFullYear());

    if (/{\{?year\}?}/.test(text)) {
        return text.replace(/\{\{?year\}?\}/g, year);
    }

    return text.replace(/(?:19|20)\d{2}/, year);
});

const email = ref('');

function subscribe() {
    const value = email.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        swalToast('Please enter a valid email address.', 'error');
        return;
    }
    email.value = '';
    swalToast('Thanks for subscribing!');
}
</script>

<template>
    <footer class="bg-background">
        <div class="wd-container grid gap-10 py-14 md:grid-cols-2 lg:grid-cols-4">
            <!-- Brand -->
            <div>
                <img :src="site.logo" :alt="site.name" class="h-16 w-auto" />
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-neutral-500">
                    {{ site.tagline }}
                </p>
                <div class="mt-5 flex gap-3">
                    <a
                        v-for="s in site.socials"
                        :key="s.name"
                        :href="s.url"
                        :aria-label="s.name"
                        class="grid h-9 w-9 place-items-center rounded-full bg-neutral-100 text-ink transition hover:bg-secondary hover:text-white"
                    >
                        <i :class="s.icon"></i>
                    </a>
                </div>
            </div>

            <!-- Navigation -->
            <div>
                <h3 class="font-widget text-lg capitalize text-muted">Navigation</h3>
                <ul class="mt-4 space-y-2.5">
                    <li v-for="item in site.footerNav" :key="item.to">
                        <router-link
                            :to="item.to"
                            class="text-sm text-ink transition hover:text-secondary"
                        >
                            {{ item.label }}
                        </router-link>
                    </li>
                </ul>
            </div>

            <!-- Legal -->
            <div>
                <h3 class="font-widget text-lg capitalize text-muted">Legal &amp; Policies</h3>
                <ul class="mt-4 space-y-2.5">
                    <li v-for="item in site.legalNav" :key="item.to">
                        <router-link
                            :to="item.to"
                            class="text-sm text-ink transition hover:text-secondary"
                        >
                            {{ item.label }}
                        </router-link>
                    </li>
                </ul>
            </div>

            <!-- Newsletter -->
            <div>
                <h3 class="font-widget text-lg capitalize text-muted">
                    {{ site.newsletterTitle }}
                </h3>
                <p class="mt-4 text-sm leading-relaxed text-neutral-500">
                    {{ site.newsletterBody }}
                </p>

                <form class="mt-4 flex flex-col gap-3" @submit.prevent="subscribe">
                    <label class="sr-only" for="newsletter-email">Email</label>
                    <input
                        id="newsletter-email"
                        v-model="email"
                        type="email"
                        placeholder="Enter Your Email"
                        class="wd-field !py-3 !px-5 text-sm"
                    />
                    <button type="submit" class="wd-btn !py-3 text-sm">
                        Subscribe
                    </button>
                </form>
            </div>
        </div>

        <!-- Copyright -->
        <div class="border-t border-black/10">
            <div class="wd-container py-7">
                <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ copyright }}</p>
                        <p class="mt-1 text-sm font-semibold text-ink">
                            {{ site.ruo }}
                        </p>
                    </div>
                    <div class="max-w-5xl">
                        <p class="text-xs leading-relaxed text-neutral-500">
                            {{ site.legalNotice }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>
