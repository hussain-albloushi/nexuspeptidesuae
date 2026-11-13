// Shared helpers for the AI Business Assistant admin pages.

export const AI_PERIODS = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: '7d', label: '7 days' },
    { value: '30d', label: '30 days' },
    { value: '90d', label: '90 days' },
    { value: 'this_month', label: 'This month' },
    { value: 'previous_month', label: 'Last month' },
    { value: 'this_year', label: 'This year' },
];

export function money(value, currency = 'AED') {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return `${currency} ${Number(value).toLocaleString('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function number(value, decimals = 0) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return Number(value).toLocaleString('en-AE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

export function percent(value, decimals = 1) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return `${Number(value).toFixed(decimals)}%`;
}

export function signedPercent(value, decimals = 1) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    const sign = Number(value) > 0 ? '+' : '';
    return `${sign}${Number(value).toFixed(decimals)}%`;
}

export function signedMoney(value, currency = 'AED') {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    const sign = Number(value) > 0 ? '+' : '';
    return `${sign}${money(value, currency)}`;
}

export function signedNumber(value, decimals = 0) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    const sign = Number(value) > 0 ? '+' : '';
    return `${sign}${number(value, decimals)}`;
}

export function trendMeta(percentDelta) {
    if (percentDelta === null || percentDelta === undefined || Number.isNaN(Number(percentDelta))) {
        return { icon: 'bi-dash', class: 'text-secondary', label: 'No comparison' };
    }
    if (Number(percentDelta) > 0) return { icon: 'bi-arrow-up-right', class: 'text-success', label: 'Up' };
    if (Number(percentDelta) < 0) return { icon: 'bi-arrow-down-right', class: 'text-danger', label: 'Down' };
    return { icon: 'bi-dash', label: 'Flat', class: 'text-secondary' };
}

export function periodQuery(period, start, end) {
    if (period === 'custom' && start && end) {
        return `start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
    }
    return `period=${encodeURIComponent(period || '30d')}`;
}

/**
 * Build an actionable assistant error from a failed response, including the
 * provider's own message (already sanitised server-side) when present.
 */
export function aiErrorMessage(response, fallback = 'The assistant could not answer. Please try again.') {
    const data = response?.data ?? {};
    const message = data.message || fallback;
    const detail = typeof data.detail === 'string' ? data.detail.trim() : '';

    return detail ? `${message} Provider said: ${detail}` : message;
}

/** Human label for snake_case / kebab-case API values. */
export function titleCase(value) {
    return String(value ?? '')
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

/** Format a KPI value according to its backend unit. */
export function kpiValue(value, unit = '', decimals = 2) {
    if (unit === 'AED') return money(value);
    if (unit === '%') return percent(value, decimals);
    return number(value, 0);
}

/** Format a signed KPI delta according to its backend unit. */
export function kpiDelta(value, unit = '', decimals = 2) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    const sign = Number(value) > 0 ? '+' : '';
    if (unit === 'AED') return `${sign}${money(value)}`;
    if (unit === '%') return `${sign}${Number(value).toFixed(decimals)}%`;
    return `${sign}${number(value, 0)}`;
}

/** Palette used by every AI chart for a consistent visual language. */
export const CHART_PALETTE = [
    '#009e5a', '#0b4f8a', '#c27803', '#b42318', '#6b7280',
    '#8b5cf6', '#0e7490', '#9333ea', '#f59e0b', '#10b981',
];

/** Resolve the admin theme colours once per render. */
export function chartTheme() {
    if (typeof document === 'undefined') {
        return { text: '#6b7280', grid: '#e5e7eb', primary: '#009e5a', secondary: '#0b4f8a' };
    }
    const styles = getComputedStyle(document.documentElement);
    const value = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;

    return {
        text: value('--admin-muted', '#6b7280'),
        grid: value('--admin-border', '#e5e7eb'),
        primary: value('--admin-primary', '#009e5a'),
        secondary: value('--admin-secondary', '#0b4f8a'),
    };
}

export function destroyCharts(charts) {
    while (charts.length) {
        charts.pop()?.destroy();
    }
}

export function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

export function formatDate(value) {
    if (!value) return '—';
    const date = toDate(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Parse date-only strings in local time so the calendar day never shifts. */
function toDate(value) {
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    return new Date(value);
}

/**
 * Minimal, safe markdown renderer for AI answers and generated reports.
 * HTML is escaped first; supports headings, bold, inline code, bullet lists
 * and GitHub-style tables. Never inserts raw model HTML.
 */
export function renderMarkdown(markdown) {
    const escape = (text) => String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const inline = (text) => escape(text)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');

    const lines = String(markdown ?? '').split(/\r?\n/);
    const out = [];
    let bullets = [];
    let table = [];

    const flushBullets = () => {
        if (!bullets.length) return;
        out.push(`<ul>${bullets.map((item) => `<li>${inline(item)}</li>`).join('')}</ul>`);
        bullets = [];
    };

    const flushTable = () => {
        if (table.length < 2) {
            table = [];
            return;
        }
        const rows = table.map((row) => row.split('|').slice(1, -1).map((cell) => cell.trim()));
        const separator = rows[1] && rows[1].every((cell) => /^:?-{2,}:?$/.test(cell));
        const header = rows[0];
        const body = separator ? rows.slice(2) : rows.slice(1);
        let html = '<div class="table-responsive"><table class="table table-sm ai-markdown-table"><thead><tr>';
        html += header.map((cell) => `<th>${inline(cell)}</th>`).join('') + '</tr></thead><tbody>';
        for (const row of body) {
            html += `<tr>${row.map((cell) => `<td>${inline(cell)}</td>`).join('')}</tr>`;
        }
        html += '</tbody></table></div>';
        out.push(html);
        table = [];
    };

    for (const raw of lines) {
        const line = raw.trimEnd();
        const trimmed = line.trim();

        if (trimmed.startsWith('|') && trimmed.endsWith('|') && trimmed.includes('|')) {
            flushBullets();
            table.push(trimmed);
            continue;
        }
        flushTable();

        if (trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
            bullets.push(trimmed.slice(2));
            continue;
        }
        flushBullets();

        if (trimmed === '') {
            out.push('');
        } else if (/^###\s+/.test(trimmed)) {
            out.push(`<h5>${inline(trimmed.replace(/^###\s+/, ''))}</h5>`);
        } else if (/^##\s+/.test(trimmed)) {
            out.push(`<h4>${inline(trimmed.replace(/^##\s+/, ''))}</h4>`);
        } else if (/^#\s+/.test(trimmed)) {
            out.push(`<h3>${inline(trimmed.replace(/^#\s+/, ''))}</h3>`);
        } else if (/^---+$/.test(trimmed)) {
            out.push('<hr>');
        } else {
            out.push(`<p>${inline(trimmed)}</p>`);
        }
    }
    flushBullets();
    flushTable();

    return out.join('\n');
}

export const CLASS_LABELS = {
    best_sellers: 'Best Sellers',
    high_profit: 'High Profit',
    low_profit: 'Low Profit',
    slow_moving: 'Slow-Moving',
    declining: 'Declining',
    high_revenue_low_profit: 'High Revenue / Low Profit',
    high_potential: 'High Potential',
    no_sales: 'No Sales',
    high_discounts: 'High Discounts',
    restock_needed: 'Restock Needed',
};

export const CLASS_BADGES = {
    best_sellers: 'text-bg-success',
    high_profit: 'text-bg-success',
    low_profit: 'text-bg-warning',
    slow_moving: 'text-bg-secondary',
    declining: 'text-bg-danger',
    high_revenue_low_profit: 'text-bg-warning',
    high_potential: 'text-bg-info',
    no_sales: 'text-bg-secondary',
    high_discounts: 'text-bg-warning',
    restock_needed: 'text-bg-danger',
};

export const STATUS_BADGES = {
    new: 'text-bg-primary',
    reviewed: 'text-bg-info',
    in_progress: 'text-bg-warning',
    completed: 'text-bg-success',
    dismissed: 'text-bg-secondary',
};

export const PRIORITY_BADGES = {
    critical: 'text-bg-danger',
    high: 'text-bg-warning',
    medium: 'text-bg-info',
    low: 'text-bg-secondary',
};
