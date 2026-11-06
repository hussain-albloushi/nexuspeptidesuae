/**
 * Static product catalogue — mirrors the 22 products published on
 * zenvypeptides.com (prices, discount badges, slugs, images, categories).
 *
 * Images are hot-linked for now; swap `image` for local paths once the
 * storefront is wired to the admin panel.
 */

const CDN = 'https://zenvypeptides.com/wp-content/uploads/';

export const categories = [
    {
        slug: 'peptides',
        name: 'Peptides',
        description:
            'Explore a curated selection of high-purity peptides designed to support accurate and reliable laboratory outcomes. Each product is quality-controlled and batch-traceable for consistent research performance.',
    },
    {
        slug: 'lab-supplies',
        name: 'Lab Supplies',
        description:
            'Essential laboratory supplies and reagents, quality-controlled and batch-traceable for consistent research performance.',
    },
];

/**
 * Shared boilerplate used by every product description, matching the
 * structure of the source site (Storage / Package Includes / Important Notice).
 */
const boilerplate = {
    storage:
        'Store in a cool, dry location away from direct sunlight. For long-term storage, follow established handling and storage guidelines.',
    noticeTitle: 'Important Notice',
    notice: 'For adult use only. Consult a healthcare professional before use.',
    noticeBody:
        'This product is intended for personal use by adults 18 years of age or older. It is **not** intended to diagnose, treat, cure, or prevent any disease. Purchasers are responsible for ensuring compliance with all applicable laws and regulations regarding the purchase, possession, and use of this product.',
};

const raw = [
    {
        slug: 'bacteriostatic-water-10ml-2',
        name: 'Bacteriostatic Water 10ml',
        regular: 19.95,
        price: 14.95,
        discount: 25,
        image: '2026/07/BAC-Water-10ml.png',
        category: 'peptides',
        tags: ['Bacteriostatic Water', 'Lab Supplies'],
        amount: '10ml',
        compound: '0.9% Benzyl Alcohol-preserved sterile water',
        intro:
            'sterile bacteriostatic water prepared for laboratory reconstitution and analytical applications.',
        research:
            'Widely used across research settings as a diluent for lyophilised compounds, it supports repeatable sample preparation when handled under standard aseptic technique.',
        features: [
            '10ml multi-dose sterile vial',
            '0.9% benzyl alcohol preservative',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'epitalon-10mg',
        name: 'Epitalon 10mg',
        regular: 39.95,
        price: 29.95,
        discount: 25,
        image: '2026/07/Epitalon-10mg.png',
        category: 'peptides',
        tags: ['Epitalon'],
        amount: '10mg',
        compound: 'Epithalon (Ala-Glu-Asp-Gly)',
        intro: 'lyophilised Epitalon tetrapeptide produced in a controlled manufacturing environment.',
        research:
            'Epitalon has been widely studied in laboratory research involving pineal signalling, telomerase activity and cellular ageing models.',
        features: [
            '10mg lyophilised Epitalon per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'ghk-cu-50mg',
        name: 'GHK-CU 50mg',
        regular: 39.95,
        price: 29.95,
        discount: 25,
        image: '2026/07/GHK-CU-50mg.png',
        category: 'peptides',
        tags: ['GHK-Cu'],
        amount: '50mg',
        compound: 'Glycyl-L-Histidyl-L-Lysine Copper Complex',
        intro: 'lyophilised GHK-Cu (Glycyl-L-Histidyl-L-Lysine Copper Complex)',
        research:
            'GHK-Cu is a naturally occurring copper-binding tripeptide that has been widely studied in laboratory research involving cellular signalling, extracellular matrix biology, tissue remodeling, and peptide-copper interactions. It is supplied exclusively for research, analytical, and educational purposes.',
        features: [
            '50mg lyophilised GHK-Cu per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'melanotan-2-10mg',
        name: 'Melanotan-2 10mg',
        regular: 39.95,
        price: 29.95,
        discount: 25,
        image: '2026/07/Melanotan-2-10mg.png',
        category: 'peptides',
        tags: ['Melanotan-II'],
        amount: '10mg',
        compound: 'Melanotan II (Ac-Nle-c[Asp-His-D-Phe-Arg-Trp-Lys]-NH2)',
        intro: 'lyophilised Melanotan II cyclic heptapeptide produced under controlled conditions.',
        research:
            'Melanotan II is a synthetic cyclic peptide analogue of alpha-MSH that has been extensively studied in laboratory research involving melanocortin receptor binding and pigmentary pathways.',
        features: [
            '10mg lyophilised Melanotan II per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'mots-c-10mg',
        name: 'Mots-C 10mg',
        regular: 49.95,
        price: 39.95,
        discount: 20,
        image: '2026/07/Mots-C-10mg.png',
        category: 'peptides',
        tags: ['Mots-C'],
        amount: '10mg',
        compound: 'Mitochondrial-derived peptide MOTS-c',
        intro: 'lyophilised MOTS-c mitochondrial-derived peptide.',
        research:
            'MOTS-c is a mitochondrial-derived peptide researched for its role in metabolic regulation, cellular energy homeostasis and stress-response signalling.',
        features: [
            '10mg lyophilised MOTS-c per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'cjc-1595-impamorelin-5mg-5mg',
        name: 'CJC 1595 / Impamorelin 5mg/5mg',
        regular: 49.95,
        price: 39.95,
        discount: 20,
        image: '2026/07/CJC-1295-Impamorelin-10mg.png',
        category: 'peptides',
        tags: ['CJC-1295', 'Ipamorelin'],
        amount: '5mg/5mg',
        compound: 'CJC-1295 (no DAC) / Ipamorelin blend',
        intro: 'a lyophilised blend of CJC-1295 and Ipamorelin produced in a controlled environment.',
        research:
            'This combination is frequently examined in laboratory research covering growth-hormone secretagogue pathways and synergistic peptide-receptor interactions.',
        features: [
            '5mg CJC-1295 and 5mg Ipamorelin per vial',
            'High-purity research-grade peptide blend',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'aod-9604-5mg',
        name: 'AOD-9604 5mg',
        regular: 49.95,
        price: 39.95,
        discount: 20,
        image: '2026/07/AOD-9604-5mg.png',
        category: 'peptides',
        tags: ['AOD-9604'],
        amount: '5mg',
        compound: 'AOD-9604 (hGH fragment 176-191)',
        intro: 'lyophilised AOD-9604 growth-hormone fragment.',
        research:
            'AOD-9604 is a modified C-terminal fragment of human growth hormone studied in laboratory research involving lipolytic signalling and metabolic pathways.',
        features: [
            '5mg lyophilised AOD-9604 per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'seamx-10mg',
        name: 'Semax 10mg',
        regular: 59.95,
        price: 44.95,
        discount: 25,
        image: '2026/07/831d4142-244c-40b9-a4ef-7cd7cfddee01.png',
        category: 'peptides',
        tags: ['Semax'],
        amount: '10mg',
        compound: 'Semax (Met-Glu-His-Phe-Pro-Gly-Pro)',
        intro: 'lyophilised Semax heptapeptide produced under controlled conditions.',
        research:
            'Semax is a synthetic peptide analogue studied in laboratory research involving neurotrophic signalling, cognitive pathways and peptide-mediated gene expression.',
        features: [
            '10mg lyophilised Semax per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'l-carnitine-5000mg',
        name: 'L-Carnitine 5000mg',
        regular: 69.95,
        price: 59.95,
        discount: 14,
        image: '2026/07/L-Carnitine-5000mg.png',
        category: 'peptides',
        tags: ['L-Carnitine'],
        amount: '5000mg',
        compound: 'L-Carnitine L-Tartrate',
        intro: 'research-grade L-Carnitine supplied in a controlled laboratory preparation.',
        research:
            'L-Carnitine is an amino-acid derivative studied in laboratory research involving mitochondrial fatty-acid transport and cellular energy metabolism.',
        features: [
            '5000mg L-Carnitine per container',
            'High-purity research-grade compound',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile packaging',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'tb-500-10mg',
        name: 'TB-500 10mg',
        regular: 69.95,
        price: 59.95,
        discount: 14,
        image: '2026/07/TB-500-10mg.png',
        category: 'peptides',
        tags: ['TB-500', 'Thymosin Beta-4'],
        amount: '10mg',
        compound: 'Thymosin Beta-4 fragment (TB-500)',
        intro: 'lyophilised TB-500 thymosin beta-4 fragment.',
        research:
            'TB-500 is a synthetic fragment studied in laboratory research covering actin regulation, cell migration and tissue-remodelling pathways.',
        features: [
            '10mg lyophilised TB-500 per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'bpc-157-10mg',
        name: 'BPC-157 10mg',
        regular: 69.95,
        price: 59.95,
        discount: 14,
        image: '2026/07/BPC-157-10mg.png',
        category: 'peptides',
        tags: ['BPC-157'],
        amount: '10mg',
        compound: 'Body Protection Compound-157 (GEPPPGKPAPPGP)',
        intro: 'lyophilised BPC-157 pentadecapeptide produced in a controlled manufacturing environment.',
        research:
            'BPC-157 is a synthetic peptide studied in laboratory research involving angiogenic signalling, gut-pathway models and soft-tissue repair mechanisms.',
        features: [
            '10mg lyophilised BPC-157 per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'nad-500mg',
        name: 'NAD 500mg',
        regular: 69.95,
        price: 59.95,
        discount: 14,
        image: '2026/07/NAD-500mg.png',
        category: 'peptides',
        tags: ['NAD+'],
        amount: '500mg',
        compound: 'Nicotinamide Adenine Dinucleotide',
        intro: 'research-grade NAD+ supplied under controlled laboratory conditions.',
        research:
            'NAD+ is a coenzyme studied extensively in laboratory research involving redox reactions, sirtuin activity and cellular energy metabolism.',
        features: [
            '500mg NAD+ per container',
            'High-purity research-grade compound',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile packaging',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'bacteriostatic-bac-water-10ml-cloned-1344',
        name: 'GLP-3/R 10mg',
        regular: 79.95,
        price: 69.95,
        discount: 13,
        image: '2026/07/GLP-3-image-10-mg-1.jpg',
        category: 'lab-supplies',
        tags: ['GLP-3/R', 'Retatrutide'],
        amount: '10mg',
        compound: 'GLP-3/R triple-agonist blend',
        intro: 'lyophilised GLP-3/R research blend produced under controlled conditions.',
        research:
            'GLP-3/R is a multi-receptor agonist blend examined in laboratory research covering incretin receptor pathways and metabolic signalling.',
        features: [
            '10mg lyophilised GLP-3/R blend per vial',
            'High-purity research-grade compound',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'tesamorelin-10mg',
        name: 'Tesamorelin 10mg',
        regular: 79.95,
        price: 69.95,
        discount: 13,
        image: '2026/07/Tesa-10mg.png',
        category: 'peptides',
        tags: ['Tesamorelin'],
        amount: '10mg',
        compound: 'Tesamorelin (His-Ala-Asp-Ala-Ile-Phe-Thr-Asn-Ser-Tyr-Arg-Lys-Val-Leu-Gly-Gln-Leu-Ser-Ala-Arg-Lys-Leu-Leu-Gln-Asp-Ile-Met-Ser-Arg)',
        intro: 'lyophilised Tesamorelin GHRH analogue.',
        research:
            'Tesamorelin is a stabilised growth-hormone-releasing-hormone analogue studied in laboratory research involving pituitary signalling and IGF-1 pathways.',
        features: [
            '10mg lyophilised Tesamorelin per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'hcg-10000iu',
        name: 'HCG 10,000iu',
        regular: 89.95,
        price: 79.95,
        discount: 11,
        image: '2026/07/HCG-10000iu.png',
        category: 'peptides',
        tags: ['HCG'],
        amount: '10,000iu',
        compound: 'Human Chorionic Gonadotropin',
        intro: 'research-grade HCG supplied under controlled laboratory conditions.',
        research:
            'HCG is a glycoprotein hormone studied in laboratory research covering gonadotropin receptor binding and steroidogenic signalling pathways.',
        features: [
            '10,000iu HCG per container',
            'High-purity research-grade compound',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile packaging',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'glutathione-1500mg-2',
        name: 'Glutathione 1500mg',
        regular: 98.75,
        price: 79.95,
        discount: 19,
        image: '2026/07/Glutathione-1500mg-1.png',
        category: 'peptides',
        tags: ['Glutathione'],
        amount: '1500mg',
        compound: 'L-Gamma-Glutamyl-L-Cysteinyl-Glycine',
        intro: 'research-grade Glutathione supplied under controlled laboratory conditions.',
        research:
            'Glutathione is a tripeptide antioxidant studied in laboratory research involving redox balance, glutathione-S-transferase activity and cellular defence pathways.',
        features: [
            '1500mg Glutathione per container',
            'High-purity research-grade compound',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile packaging',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'seramorelin-10mg-3',
        name: 'Seramorelin 10mg',
        regular: 119.95,
        price: 99.95,
        discount: 17,
        image: '2026/07/Seramorelin-10mg.png',
        category: 'peptides',
        tags: ['Seramorelin'],
        amount: '10mg',
        compound: 'Seramorelin (GRF 1-29)',
        intro: 'lyophilised Seramorelin GHRH analogue produced under controlled conditions.',
        research:
            'Seramorelin is a 29-residue growth-hormone-releasing-hormone analogue studied in laboratory research involving somatotropic axis signalling.',
        features: [
            '10mg lyophilised Seramorelin per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'glow-70mg',
        name: 'Glow 70mg',
        regular: 119.95,
        price: 99.95,
        discount: 17,
        image: '2026/07/Glow-70mg.png',
        category: 'peptides',
        tags: ['Glow Blend'],
        amount: '70mg',
        compound: 'Glow blend (GHK-Cu / BPC-157 / TB-500)',
        intro: 'a lyophilised Glow peptide blend produced in a controlled manufacturing environment.',
        research:
            'The Glow blend combines three research peptides commonly examined together for extracellular-matrix, signalling and tissue-remodelling pathways.',
        features: [
            '70mg lyophilised Glow blend per vial',
            'High-purity research-grade peptide blend',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'tesamorelin-30mg',
        name: 'Tesamorelin 30mg',
        regular: 129.95,
        price: 119.95,
        discount: 8,
        image: '2026/07/Tesa-30mg.png',
        category: 'peptides',
        tags: ['Tesamorelin'],
        amount: '30mg',
        compound: 'Tesamorelin GHRH analogue',
        intro: 'lyophilised Tesamorelin GHRH analogue at a higher research quantity.',
        research:
            'Tesamorelin is a stabilised growth-hormone-releasing-hormone analogue studied in laboratory research involving pituitary signalling and IGF-1 pathways.',
        features: [
            '30mg lyophilised Tesamorelin per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'mots-c-30mg',
        name: 'Mots-C 30mg',
        regular: 139.95,
        price: 129.95,
        discount: 7,
        image: '2026/07/Mots-C-30mg.png',
        category: 'peptides',
        tags: ['Mots-C'],
        amount: '30mg',
        compound: 'Mitochondrial-derived peptide MOTS-c',
        intro: 'lyophilised MOTS-c mitochondrial-derived peptide at a higher research quantity.',
        research:
            'MOTS-c is a mitochondrial-derived peptide researched for its role in metabolic regulation, cellular energy homeostasis and stress-response signalling.',
        features: [
            '30mg lyophilised MOTS-c per vial',
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'retatrutide-30mg',
        name: 'GLP-3/R 30mg',
        regular: 179.95,
        price: 179.95,
        discount: 0,
        image: '2026/07/GLP-3-image-30-mg.jpg',
        category: 'lab-supplies',
        tags: ['GLP-3/R', 'Retatrutide'],
        amount: '30mg',
        compound: 'GLP-3/R triple-agonist blend',
        intro: 'lyophilised GLP-3/R research blend at a higher research quantity.',
        research:
            'GLP-3/R is a multi-receptor agonist blend examined in laboratory research covering incretin receptor pathways and metabolic signalling.',
        features: [
            '30mg lyophilised GLP-3/R blend per vial',
            'High-purity research-grade compound',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
    {
        slug: 'glp-3-r-60mg',
        name: 'GLP-3/R 60mg',
        regular: 349.99,
        price: 298.95,
        discount: 15,
        image: '2026/09/GLP-3-image-60-mg.jpg',
        category: 'lab-supplies',
        tags: ['GLP-3/R', 'Retatrutide'],
        amount: '60mg',
        compound: 'GLP-3/R triple-agonist blend',
        intro: 'lyophilised GLP-3/R research blend supplied at the largest research quantity.',
        research:
            'GLP-3/R is a multi-receptor agonist blend examined in laboratory research covering incretin receptor pathways and metabolic signalling.',
        features: [
            '60mg lyophilised GLP-3/R blend per vial',
            'High-purity research-grade compound',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile vial',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
    },
];

/** Markdown-ish → HTML for the small amount of emphasis we use. */
function emphasise(text) {
    return text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}

/** Build the long-form description exactly as the source site structures it. */
function buildDescription(p) {
    return {
        intro: `Our <strong>${p.name}</strong> is manufactured to high-quality standards and intended exclusively for <strong>laboratory and scientific research applications</strong>. Each vial contains <strong>${p.amount} of ${p.intro}</strong>, produced in a controlled manufacturing environment to ensure consistency, purity, and reliability for qualified research professionals.`,
        heading: `${p.name}`,
        subheading: `Premium Research-Grade ${p.name}`,
        research: p.research,
        features: p.features,
        storage: boilerplate.storage,
        package: `One (1) vial containing ${p.amount} ${p.compound}`,
        noticeTitle: boilerplate.noticeTitle,
        notice: boilerplate.notice,
        noticeBody: emphasise(boilerplate.noticeBody),
    };
}

export const products = raw.map((p) => ({
    ...p,
    image: CDN + p.image,
    categoryLabel: categories.find((c) => c.slug === p.category)?.name || 'Peptides',
    description: buildDescription(p),
}));

/** Original catalogue order (as shown on the homepage). */
export const allPeptides = products;

/** Price-ascending order used by the shop and category listings. */
export const byPriceAsc = [...products].sort((a, b) => a.price - b.price);

export function findBySlug(slug) {
    return products.find((p) => p.slug === slug) || null;
}

export function relatedTo(product, count = 8) {
    if (!product) return [];
    const sameCat = products.filter((p) => p.category === product.category && p.slug !== product.slug);
    const rest = products.filter((p) => p.category !== product.category && p.slug !== product.slug);
    return [...sameCat, ...rest].slice(0, count);
}

export function formatMoney(value) {
    // Note: Most components now use the currency store's format() method.
    // This helper remains as a fallback, now defaulting to AED format.
    return `AED ${Number(value).toFixed(2)}`;
}
