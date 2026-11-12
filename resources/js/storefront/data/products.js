/**
 * Storefront product catalogue.
 *
 * The rich per-compound copy (tags, descriptions, features) is built below
 * from the static definitions, but the admin-managed values win at runtime:
 * the blade layout publishes the active, public products from the database as
 * window.__STOREFRONT_PRODUCTS__ and those entries override the static name,
 * price, image, and sale pricing. Products removed (or deactivated/hidden) in
 * the admin disappear from the storefront. When the payload is missing or
 * empty — e.g. the database is briefly unavailable — the static catalogue
 * renders unchanged as a fallback.
 *
 * Keep the static prices below in sync with the database `products` table
 * (admin → Products): the static values only act as the fallback path.
 * Wishlist/cart persists slugs in localStorage — old slugs resolve to nothing
 * and are filtered out, so no migration is needed.
 */

export const categories = [
    {
        slug: 'peptides',
        name: 'Peptides',
        description:
            'Explore our full line of Nexus research peptides — high-purity, batch-traceable compounds supplied exclusively for laboratory and analytical research.',
    },
];

const boilerplate = {
    storage:
        'Store in a cool, dry location away from direct sunlight. For long-term storage, follow established handling and storage guidelines.',
    noticeTitle: 'Important Notice',
    notice: 'For adult use only. Consult a healthcare professional before use.',
    noticeBody:
        'This product is intended for personal use by adults 18 years of age or older. It is **not** intended to diagnose, treat, cure, or prevent any disease. Purchasers are responsible for ensuring compliance with all applicable laws and regulations regarding the purchase, possession, and use of this product.',
};

/** Per-compound copy used to build descriptions for both formats. */
const compoundCopy = {
    retatrutide: {
        compound: 'Retatrutide triple-agonist (GLP-1 / GIP / Glucagon)',
        intro: 'Retatrutide GLP-1/GIP/Glucagon triple-agonist',
        tags: ['Retatrutide'],
        research:
            'Retatrutide is a multi-receptor agonist studied in laboratory research involving incretin receptor pathways, metabolic signalling and energy-homeostasis models.',
    },
    'mots-c': {
        compound: 'Mitochondrial-derived peptide MOTS-c',
        intro: 'MOTS-c mitochondrial-derived peptide',
        tags: ['MOTS-C'],
        research:
            'MOTS-c is a mitochondrial-derived peptide researched for its role in metabolic regulation, cellular energy homeostasis and stress-response signalling.',
    },
    'bpc-tb': {
        compound: 'BPC-157 / TB-500 blended peptide',
        intro: 'BPC-157 and TB-500 blended peptide',
        tags: ['BPC-157', 'TB-500'],
        research:
            'This blended formulation is studied in laboratory research covering angiogenic signalling, actin regulation, cell migration and tissue-remodelling pathways.',
    },
    'tb-500': {
        compound: 'Thymosin Beta-4 fragment (TB-500)',
        intro: 'TB-500 thymosin beta-4 fragment',
        tags: ['TB-500', 'Thymosin Beta-4'],
        research:
            'TB-500 is a synthetic fragment studied in laboratory research covering actin regulation, cell migration and tissue-remodelling pathways.',
    },
    'cjc-ipa': {
        compound: 'CJC-1295 (no DAC) / Ipamorelin blend',
        intro: 'CJC-1295 and Ipamorelin co-formulated blend',
        tags: ['CJC-1295', 'Ipamorelin'],
        research:
            'This combination is frequently examined in laboratory research covering growth-hormone secretagogue pathways and synergistic peptide-receptor interactions.',
    },
    selank: {
        compound: 'Selank (Thr-Lys-Pro-Arg-Pro-Gly-Pro)',
        intro: 'Selank heptapeptide',
        tags: ['Selank'],
        research:
            'Selank is a synthetic heptapeptide analogue studied in laboratory research involving anxiolytic, neurotrophic and peptidergic signalling pathways.',
    },
    semax: {
        compound: 'Semax (Met-Glu-His-Phe-Pro-Gly-Pro)',
        intro: 'Semax heptapeptide',
        tags: ['Semax'],
        research:
            'Semax is a synthetic peptide analogue studied in laboratory research involving neurotrophic signalling, cognitive pathways and peptide-mediated gene expression.',
    },
    tirzepatide: {
        compound: 'Tirzepatide GLP-1/GIP dual-agonist',
        intro: 'Tirzepatide GLP-1/GIP dual-agonist',
        tags: ['Tirzepatide'],
        research:
            'Tirzepatide is a dual GIP/GLP-1 receptor agonist examined in laboratory research involving incretin signalling and metabolic signalling pathways.',
    },
    tesamorelin: {
        compound: 'Tesamorelin GHRH analogue',
        intro: 'Tesamorelin GHRH analogue',
        tags: ['Tesamorelin'],
        research:
            'Tesamorelin is a stabilised growth-hormone-releasing-hormone analogue studied in laboratory research involving pituitary signalling and IGF-1 pathways.',
    },
};

/**
 * The 36 products: format-specific name (Pen|Vial), slug matching the DB,
 * price from products_nexus01.txt, and a local image.
 */
const defs = [
    // Injector pen format (higher tier prices)
    ['retatrutide-10mg-pen', 'Retatrutide 10mg (Pen)', 449, 'pen', '/storage/nexus_peptides_product_images/01_retatrutide_10mg.jpg', '10mg', 'retatrutide'],
    ['retatrutide-20mg-pen', 'Retatrutide 20mg (Pen)', 649, 'pen', '/storage/nexus_peptides_product_images/02_retatrutide_20mg.jpg', '20mg', 'retatrutide'],
    ['retatrutide-40mg-pen', 'Retatrutide 40mg (Pen)', 999, 'pen', '/storage/nexus_peptides_product_images/03_retatrutide_40mg.jpg', '40mg', 'retatrutide'],
    ['retatrutide-60mg-pen', 'Retatrutide 60mg (Pen)', 1299, 'pen', '/storage/nexus_peptides_product_images/04_retatrutide_60mg.jpg', '60mg', 'retatrutide'],
    ['mots-c-10mg-pen', 'MOTS-C 10mg (Pen)', 299, 'pen', '/storage/nexus_peptides_product_images/05_mots_c_10mg.jpg', '10mg', 'mots-c'],
    ['mots-c-40mg-pen', 'MOTS-C 40mg (Pen)', 649, 'pen', '/storage/nexus_peptides_product_images/06_mots_c_40mg.jpg', '40mg', 'mots-c'],
    ['bpc-157-5mg-tb-500-5mg-pen', 'BPC-157 5mg + TB-500 5mg (Pen)', 399, 'pen', '/storage/nexus_peptides_product_images/07_bpc_157_5mg_tb_500_5mg.jpg', '5mg + 5mg', 'bpc-tb'],
    ['bpc-157-10mg-tb-500-10mg-pen', 'BPC-157 10mg + TB-500 10mg (Pen)', 599, 'pen', '/storage/nexus_peptides_product_images/08_bpc_157_10mg_tb_500_10mg.jpg', '10mg + 10mg', 'bpc-tb'],
    ['tb-500-10mg-pen', 'TB-500 10mg (Pen)', 429, 'pen', '/storage/nexus_peptides_product_images/09_tb_500_10mg.jpg', '10mg', 'tb-500'],
    ['cjc-1295-5mg-ipamorelin-5mg-pen', 'CJC-1295 5mg + Ipamorelin 5mg (Pen)', 429, 'pen', '/storage/nexus_peptides_product_images/10_cjc_1295_5mg_ipamorelin_5mg.jpg', '5mg + 5mg', 'cjc-ipa'],
    ['selank-10mg-pen', 'Selank 10mg (Pen)', 329, 'pen', '/storage/nexus_peptides_product_images/11_selank_10mg.jpg', '10mg', 'selank'],
    ['semax-10mg-pen', 'Semax 10mg (Pen)', 329, 'pen', '/storage/nexus_peptides_product_images/12_semax_10mg.jpg', '10mg', 'semax'],
    ['tirzepatide-5mg-pen', 'Tirzepatide 5mg (Pen)', 279, 'pen', '/storage/nexus_peptides_product_images/13_tirzepatide_5mg.jpg', '5mg', 'tirzepatide'],
    ['tirzepatide-15mg-pen', 'Tirzepatide 15mg (Pen)', 429, 'pen', '/storage/nexus_peptides_product_images/14_tirzepatide_15mg.jpg', '15mg', 'tirzepatide'],
    ['tirzepatide-30mg-pen', 'Tirzepatide 30mg (Pen)', 649, 'pen', '/storage/nexus_peptides_product_images/15_tirzepatide_30mg.jpg', '30mg', 'tirzepatide'],
    ['tesamorelin-5mg-pen', 'Tesamorelin 5mg (Pen)', 329, 'pen', '/storage/nexus_peptides_product_images/16_tesamorelin_5mg.jpg', '5mg', 'tesamorelin'],
    ['tesamorelin-10mg-pen', 'Tesamorelin 10mg (Pen)', 479, 'pen', '/storage/nexus_peptides_product_images/17_tesamorelin_10mg.jpg', '10mg', 'tesamorelin'],
    ['tesamorelin-20mg-pen', 'Tesamorelin 20mg (Pen)', 799, 'pen', '/storage/nexus_peptides_product_images/18_tesamorelin_20mg.jpg', '20mg', 'tesamorelin'],

    // Glass vial format (lower tier prices)
    ['retatrutide-10mg-vial', 'Retatrutide 10mg (Vial)', 279, 'vial', '/storage/product_images_no_prices/retatrutide_10mg.png', '10mg', 'retatrutide'],
    ['retatrutide-20mg-vial', 'Retatrutide 20mg (Vial)', 429, 'vial', '/storage/product_images_no_prices/retatrutide_20mg.png', '20mg', 'retatrutide'],
    ['retatrutide-40mg-vial', 'Retatrutide 40mg (Vial)', 699, 'vial', '/storage/product_images_no_prices/retatrutide_40mg.png', '40mg', 'retatrutide'],
    ['retatrutide-60mg-vial', 'Retatrutide 60mg (Vial)', 899, 'vial', '/storage/product_images_no_prices/retatrutide_60mg.png', '60mg', 'retatrutide'],
    ['mots-c-10mg-vial', 'MOTS-C 10mg (Vial)', 229, 'vial', '/storage/product_images_no_prices/mots_c_10mg.png', '10mg', 'mots-c'],
    ['mots-c-40mg-vial', 'MOTS-C 40mg (Vial)', 549, 'vial', '/storage/product_images_no_prices/mots_c_40mg.png', '40mg', 'mots-c'],
    ['bpc-157-5mg-tb-500-5mg-vial', 'BPC-157 5mg + TB-500 5mg (Vial)', 329, 'vial', '/storage/product_images_no_prices/bpc157_5mg_tb500_5mg.png', '5mg + 5mg', 'bpc-tb'],
    ['bpc-157-10mg-tb-500-10mg-vial', 'BPC-157 10mg + TB-500 10mg (Vial)', 499, 'vial', '/storage/product_images_no_prices/bpc157_10mg_tb500_10mg.png', '10mg + 10mg', 'bpc-tb'],
    ['tb-500-10mg-vial', 'TB-500 10mg (Vial)', 329, 'vial', '/storage/product_images_no_prices/tb500_10mg.png', '10mg', 'tb-500'],
    ['cjc-1295-5mg-ipamorelin-5mg-vial', 'CJC-1295 5mg + Ipamorelin 5mg (Vial)', 329, 'vial', '/storage/product_images_no_prices/cjc1295_5mg_ipamorelin_5mg.png', '5mg + 5mg', 'cjc-ipa'],
    ['selank-10mg-vial', 'Selank 10mg (Vial)', 229, 'vial', '/storage/product_images_no_prices/selank_10mg.png', '10mg', 'selank'],
    ['semax-10mg-vial', 'Semax 10mg (Vial)', 229, 'vial', '/storage/product_images_no_prices/semax_10mg.png', '10mg', 'semax'],
    ['tirzepatide-5mg-vial', 'Tirzepatide 5mg (Vial)', 179, 'vial', '/storage/product_images_no_prices/tirzepatide_5mg.png', '5mg', 'tirzepatide'],
    ['tirzepatide-15mg-vial', 'Tirzepatide 15mg (Vial)', 329, 'vial', '/storage/product_images_no_prices/tirzepatide_15mg.png', '15mg', 'tirzepatide'],
    ['tirzepatide-30mg-vial', 'Tirzepatide 30mg (Vial)', 499, 'vial', '/storage/product_images_no_prices/tirzepatide_30mg.png', '30mg', 'tirzepatide'],
    ['tesamorelin-5mg-vial', 'Tesamorelin 5mg (Vial)', 229, 'vial', '/storage/product_images_no_prices/tesamorelin_5mg.png', '5mg', 'tesamorelin'],
    ['tesamorelin-10mg-vial', 'Tesamorelin 10mg (Vial)', 379, 'vial', '/storage/product_images_no_prices/tesamorelin_10mg.png', '10mg', 'tesamorelin'],
    ['tesamorelin-20mg-vial', 'Tesamorelin 20mg (Vial)', 649, 'vial', '/storage/product_images_no_prices/tesamorelin_20mg.png', '20mg', 'tesamorelin'],
];

const raw = defs.map(([slug, name, price, format, image, amount, compoundKey]) => {
    const copy = compoundCopy[compoundKey];

    return {
        slug,
        name,
        regular: price,
        price,
        discount: 0,
        image,
        category: 'peptides',
        tags: [...copy.tags, format === 'pen' ? 'Pen' : 'Vial'],
        amount,
        compound: copy.compound,
        intro: copy.intro,
        research: copy.research,
        features: [
            `${amount} ${copy.intro} per ${format === 'pen' ? 'pre-filled injector pen' : 'vial'}`,
            'High-purity research-grade peptide',
            'Manufactured under strict quality control standards',
            'Securely sealed sterile packaging',
            'Intended for laboratory and analytical research',
            'Batch tested for identity and purity',
        ],
        format,
    };
});

/** Markdown-ish → HTML for the small amount of emphasis we use. */
function emphasise(text) {
    return text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}

/** Build the long-form description exactly as the source site structures it. */
function buildDescription(p) {
    const isPen = p.format === 'pen';
    const container = isPen ? 'pre-filled injector pen' : 'vial';
    return {
        intro: `Our <strong>${p.name}</strong> is manufactured to high-quality standards and intended exclusively for <strong>laboratory and scientific research applications</strong>. Each ${container} contains <strong>${p.amount} of ${p.intro}</strong>, produced in a controlled manufacturing environment to ensure consistency, purity, and reliability for qualified research professionals.`,
        heading: `${p.name}`,
        subheading: `Premium Research-Grade ${p.name}`,
        research: p.research,
        features: p.features,
        storage: boilerplate.storage,
        package: `One (1) ${container} containing ${p.amount} ${p.compound}`,
        noticeTitle: boilerplate.noticeTitle,
        notice: boilerplate.notice,
        noticeBody: emphasise(boilerplate.noticeBody),
    };
}

/**
 * Overlay the admin-published catalogue (database) over the static values.
 * `compare_at_price` becomes the crossed-out regular price and `discount`
 * the percentage shown on the sale badge, matching how the storefront
 * components render them.
 */
const publishedBySlug = new Map(
    (typeof window !== 'undefined' && Array.isArray(window.__STOREFRONT_PRODUCTS__)
        ? window.__STOREFRONT_PRODUCTS__
        : []
    )
        .filter((p) => p && typeof p.slug === 'string')
        .map((p) => [p.slug, p]),
);

const catalogue = publishedBySlug.size
    ? raw
        .filter((p) => publishedBySlug.has(p.slug))
        .map((p) => {
            const live = publishedBySlug.get(p.slug);
            const price = Number(live.price);
            const compare = Number(live.compare_at_price);
            const hasCompare = Number.isFinite(price) && Number.isFinite(compare) && compare > price;
            const effectivePrice = Number.isFinite(price) ? price : p.price;

            return {
                ...p,
                name: typeof live.name === 'string' && live.name.trim() !== '' ? live.name : p.name,
                price: effectivePrice,
                regular: hasCompare ? compare : effectivePrice,
                discount: hasCompare ? Math.round(((compare - effectivePrice) / compare) * 100) : 0,
                image: typeof live.image === 'string' && live.image !== '' ? live.image : p.image,
            };
        })
    : raw;

export const products = catalogue.map((p) => ({
    ...p,
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
