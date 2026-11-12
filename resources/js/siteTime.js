/**
 * All timestamps are written by the website in the store timezone (Settings →
 * Store timezone) and stored as plain wall-clock values. Render them in that
 * same zone so the admin panel and storefront show one clock, no matter which
 * timezone the visitor's device uses.
 */

const fallbackTimezone = 'Asia/Dubai';

export function siteTimezone() {
    return (typeof window !== 'undefined' && window.__SITE_TIMEZONE__) || fallbackTimezone;
}

/**
 * Values that carry an offset/Z are real instants and are converted into site
 * time. Naive `Y-m-d H:i:s` strings are already site wall time, so they are
 * displayed verbatim (parsed as UTC) instead of being shifted again.
 */
function parseSiteDate(value) {
    if (!value) return null;

    const text = String(value);
    const hasZone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(text);
    const date = new Date(hasZone ? text : `${text.replace(' ', 'T')}Z`);

    if (Number.isNaN(date.getTime())) return null;

    return { date, timeZone: hasZone ? siteTimezone() : 'UTC' };
}

export function formatSiteDate(value, options = {}, empty = 'Not recorded', locales = undefined) {
    const parsed = parseSiteDate(value);
    if (!parsed) return empty;

    return new Intl.DateTimeFormat(locales, { ...options, timeZone: parsed.timeZone }).format(parsed.date);
}
