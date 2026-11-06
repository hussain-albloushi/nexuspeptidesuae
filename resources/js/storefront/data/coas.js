/** Batch-specific Certificates of Analysis, as listed on /coa */
const CDN = 'https://zenvypeptides.com/wp-content/uploads/';

export const coas = [
    { name: 'BPC-157 10mg',    image: '2026/05/BPC-157-10mg-1.png',    batch: '2026-06', date: '02-06-2026', purity: '99.782', pdf: '2026/06/BPC-157-10mg-1.pdf' },
    { name: 'BPC-157 5mg',     image: '2026/05/BPC-157-5mg-1.png',     batch: '2026-06', date: '02-06-2026', purity: '99.694', pdf: '2026/06/BPC-157-5mg-3.pdf' },
    { name: 'GHK-Cu 100mg',    image: '2026/05/GHKCU-100mg.png',       batch: '2026-06', date: '02-06-2026', purity: '99.552', pdf: '2026/06/GHK-Cu-100mg-1.pdf' },
    { name: 'GHK-CU 50mg',     image: '2026/05/GHK-CU-50mg-3.png',     batch: '2026-06', date: '02-06-2026', purity: '99.578', pdf: '2026/06/GHK-Cu-50mg.pdf' },
    { name: 'Glow Blend 70mg', image: '2026/05/GLOW-70mg.png',         batch: '2026-06', date: '02-06-2026', purity: 'N/A',    pdf: '2026/06/GLOW-70mg.pdf' },
    { name: 'Ipamorelin 10mg', image: '2026/05/ipamorelin-10mg-3.png', batch: '2026-06', date: '02-06-2026', purity: '99.820', pdf: '2026/06/Ipamorelin-10mg.pdf' },
    { name: 'Ipamorelin 5mg',  image: '2026/05/ipamorelin-5mg-2.png',  batch: '2026-06', date: '02-06-2026', purity: '99.765', pdf: '2026/06/Ipamorelin-5mg.pdf' },
    { name: 'Klow Blend 80mg', image: '2026/05/KLOW-80mg.png',         batch: '2026-06', date: '02-06-2026', purity: 'N/A',    pdf: '2026/06/Klow-Blend-80mg.pdf' },
    { name: 'Melanotan I 10mg',  image: '2026/05/mt1-10mg.png',        batch: '2026-06', date: '02-06-2026', purity: '99.418', pdf: '2026/06/Melanotan-I-10mg.pdf' },
    { name: 'Melanotan II 10mg', image: '2026/05/mt2-10mg.png',        batch: '2026-06', date: '02-06-2026', purity: '99.849', pdf: '2026/06/Melanotan-II-10mg.pdf' },
    { name: 'Retatrutide 10mg', image: '2026/05/reta-10mg-1.png',      batch: '2026-06', date: '02-06-2026', purity: '99.580', pdf: '2026/06/Retatrutide-10mg.pdf' },
    { name: 'Retatrutide 20mg', image: '2026/05/reta-20mg.png',        batch: '2026-06', date: '02-06-2026', purity: '99.835', pdf: '2026/06/Retatrutide-20mg.pdf' },
    { name: 'Retatrutide 30mg', image: '2026/05/reta-30mg-2.png',      batch: '2026-06', date: '02-06-2026', purity: '99.901', pdf: '2026/06/Retatrutide-30mg.pdf' },
    { name: 'TB-500 10mg', image: '2026/05/TB-500-10mg.png', batch: '2026-06', date: '02-06-2026', purity: '99.729', pdf: '2026/06/TB-500-10mg.pdf' },
    { name: 'TB-500 5mg',  image: '2026/05/TB-500-5mg.png',  batch: '2026-06', date: '02-06-2026', purity: '99.694', pdf: '2026/06/TB-500-5mg.pdf' },
    { name: 'Tesamorelin 10mg', image: '2026/05/tesamorelin-10mg-2.png', batch: '2026-06', date: '02-06-2026', purity: '99.043', pdf: '2026/06/Tesamorelin-10mg.pdf' },
    { name: 'Tesamorelin 5mg',  image: '2026/05/tesamorelin-5mg-2.png',  batch: '2026-06', date: '02-06-2026', purity: '99.612', pdf: '2026/06/Tesamorelin-5mg.pdf' },
].map((c) => ({ ...c, image: CDN + c.image, pdf: CDN + c.pdf }));

export default coas;
