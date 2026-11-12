<template>
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="strokeWidth" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path v-for="(path, index) in (icons[name] || icons.box)" :key="index" :d="path" />
  </svg>
</template>

<script setup>
defineProps({ name: { type: String, default: 'box' }, size: { type: [Number, String], default: 18 }, strokeWidth: { type: [Number, String], default: 1.7 } });
const icons = {
  dashboard: ['M3 3h7v7H3z', 'M14 3h7v7h-7z', 'M3 14h7v7H3z', 'M14 14h7v7h-7z'],
  orders: ['M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6H19a2 2 0 0 0 2-1.6L22 7H6', 'M10 21h.01M19 21h.01'],
  users: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
  box: ['m12 3 9 5-9 5-9-5 9-5Z', 'M3 8v9l9 5 9-5V8M12 13v9M7.5 5.5l9 5'],
  tags: ['m20 13-7 7-11-11V2h7l11 11Z', 'M6 6h.01'],
  inventory: ['M3 21V8l9-6 9 6v13M3 10h18M7 21v-7h10v7M7 17h10'],
  document: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Z', 'M14 2v6h6M8 13h8M8 17h8'],
  truck: ['M1 3h14v13H1z', 'M15 8h4l3 4v4h-7M5 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4M18 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4'],
  ticket: ['M4 5h16v5a2 2 0 0 0 0 4v5H4v-5a2 2 0 0 0 0-4V5Z', 'M15 5v2M15 11v2M15 17v2'],
  billing: ['M3 5h18v14H3z', 'M3 10h18M7 15h3'],
  chart: ['M3 3v18h18M7 14v3M12 9v8M17 5v12'],
  settings: ['m9 3 1-1h4l1 3 3 1 3-1 2 3-2 3 1 3 2 1-2 3-3-1-3 1-1 3h-4l-1-3-3-1-3 1-2-3 2-3-1-3-2-1 2-3 3 1 3-1 1-2Z', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6'],
  store: ['M3 9h18l-2-6H5L3 9ZM4 9v12h16V9M9 21v-7h6v7'],
  search: ['M21 21l-4.35-4.35', 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16'],
  menu: ['M4 6h16M4 12h16M4 18h16'],
  bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4'],
  moon: ['M21 12.8A9 9 0 0 1 11.2 3 9 9 0 1 0 21 12.8Z'],
  palette: ['M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 0-4h-1a1 1 0 0 1 0-2h3a6 6 0 0 0 6-6c0-3.3-4-6-9-6Z', 'M7.5 9h.01M11 6.5h.01M15.5 8h.01M6.5 13h.01'],
  bolt: ['m13 2-10 12h8l-1 8 11-12h-8l1-8Z'],
  chevron: ['m9 5 7 7-7 7'],
  logout: ['M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4M14 7l5 5-5 5M8 12h11'],
};
</script>
