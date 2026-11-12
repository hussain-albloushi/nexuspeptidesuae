/**
 * Safe defaults for every storefront content group.
 *
 * The admin panel can publish structured content from the database. Until that
 * payload is available — or when a group is unpublished — these values keep
 * every page rendering exactly as it did before the CMS was introduced.
 */
import { pages, site } from './pages';
import { faqs } from './faqs';
import { coas } from './coas';

const standards = [
    {
        icon: 'fa-solid fa-vial-circle-check',
        title: 'Third-Party Testing',
        body: 'Every batch is independently tested by accredited laboratories to confirm purity, identity, and consistency, ensuring research-grade standards.',
    },
    {
        icon: 'fa-solid fa-award',
        title: 'Research Grade Quality',
        body: 'All peptides are manufactured and handled to strict research specifications, supporting accurate and controlled scientific outcomes.',
    },
    {
        icon: 'fa-solid fa-magnifying-glass',
        title: 'Transparent Sourcing',
        body: 'We maintain full transparency across sourcing and production, with ethical practices and complete batch traceability for research integrity.',
    },
    {
        icon: 'fa-solid fa-earth-americas',
        title: 'Global Shipping',
        body: 'We provide fast, tracked international delivery, with secure and professional packaging to ensure research materials arrive safely to laboratories worldwide.',
    },
];

const legal = (key) => ({
    title: pages[key].title,
    blocks: pages[key].blocks.map((block) =>
        block.h ? { kind: 'heading', text: block.h } : { kind: 'paragraph', text: block.p },
    ),
});

export const contentDefaults = {
    site: {
        name: site.name,
        logo: site.logo,
        tagline: site.tagline,
        topbar: site.topbar,
        supportEmail: site.supportEmail,
        address: site.address,
        seoTitle: 'Nexus Peptide - UAE',
        seoDescription:
            'Nexus Peptides is a US supplier of premium research peptides with verified purity, certification, and full traceability.',
        copyright: site.copyright,
        ruo: site.ruo,
        legalNotice: site.legalNotice,
        nav: site.nav,
        footerNav: site.footerNav,
        legalNav: site.legalNav,
        socials: site.socials,
        newsletterTitle: site.newsletter.title,
        newsletterBody: site.newsletter.body,
        standards,
        features: site.features,
    },

    store: {
        currency: 'AED',
        symbolStyle: 'svg',
        symbolPosition: 'before',
        symbolText: 'AED',
        decimals: 2,
        vatEnabled: true,
        vatRate: 5,
        vatIncludedInPrice: false,
        vatLabel: 'VAT',
        shippingMode: 'flat',
        shippingOptions: [
            { name: 'Standard Shipping', cost: 50 },
            { name: 'Express Shipping', cost: 120 },
        ],
        codFee: 0,
        returnWindowDays: 14,
        ageConfirmation: true,
    },

    home: {
        heroKicker: site.heroKicker,
        heroTitle: site.heroTitle,
        heroBody: site.heroBody,
        heroCta: site.heroCta,
        heroImage: site.heroImage,
        standardsTitle: 'Built on Standards You Can Verify',
        productsTitle: 'Find the Right Peptide for Your Research',
        productsIntro:
            'Explore a curated selection of high-purity peptides designed to support accurate and reliable laboratory outcomes. Each product is quality-controlled and batch-traceable for consistent research performance.',
        productsCta: 'View all products',
        trustedTitle: 'Trusted Quality, Proven Standards',
        trustedBody:
            'Every order is backed by batch-specific COAs, strict quality control processes, and reliable technical support. We focus on consistency, transparency, and dependable service to support professional research environments.',
        trustedItems: [
            'Certificate of Analysis with every order',
            'Strict Quality Control & Testing',
            'Ethically Sourced & manufactured',
            'Dedicated Support when you need it',
        ],
        trustedCta: 'View certificates',
        trustedImage: 'https://zenvypeptides.com/wp-content/uploads/2026/09/123.png',
        purityKicker: 'Premium Quality Is Our Standard',
        purityTitle: '99% Purity, Precision Products & Advanced Solutions',
        puritySubtitle: 'Products Consistently Pure That You Can Trust',
        purityParagraphs: [
            'Nexus Peptides is a US-based supplier committed to delivering high-purity research peptides for professional laboratory and scientific use. Founded on the belief that reliable research starts with reliable materials, we hold every product to strict standards of purity, consistency, and quality control from formulation through final packaging.',
            "Every batch we produce is manufactured under controlled laboratory conditions and backed by rigorous testing protocols, giving researchers the confidence to focus on their work rather than second-guessing their materials. We believe transparency isn't optional in this industry, it's the foundation of trust between supplier and researcher.",
            'Our mission is simple: to be a dependable partner for the scientific community by combining stringent quality assurance with responsive service and clear, accessible documentation. From sourcing to shipping, Nexus Peptides is built around one goal, supporting research that researchers can stand behind.',
        ],
        purityNotice: 'Products for Research Use Only. Not for human or animal consumption.',
        purityImage: 'https://zenvypeptides.com/wp-content/uploads/2026/09/321.png',
    },

    about: {
        title: 'About Us',
        kicker: 'Who We Are',
        heading: 'Advancing Peptide Research with Precision and Integrity',
        body: 'Nexus Peptides is a US-based supplier of high-purity, COA-verified research peptides, trusted by laboratories and researchers across the country. Every product is independently tested to confirm purity, identity, and consistency, and is supported with full batch traceability and accredited laboratory documentation to ensure dependable, reproducible results. Built on a research-first approach, we focus on strict quality control, transparent sourcing, and controlled manufacturing standards to support accurate laboratory outcomes.',
        cta: 'Explore Catalogue',
        image: 'https://zenvypeptides.com/wp-content/uploads/2026/07/569ce316-e32d-42ad-96cd-aa9ed39e5909.png',
        whyTitle: 'Why Choose Nexus Peptides?',
        whyIntro:
            'Each peptide is rigorously tested for purity, accuracy, and consistency to ensure dependable laboratory results. From controlled production to secure delivery, Nexus Peptides supports reliable scientific research at every stage.',
        qualityKicker: 'Premium Quality Is Our Standard',
        qualityTitle: 'Advancing Research Through Quality',
        qualitySubtitle: '99% Purity Every Time',
        qualityParagraphs: [
            'Quality is at the core of everything we do. Every product supplied by Nexus Peptides undergoes rigorous quality verification processes to help ensure consistency, reliability, and research suitability.',
            'Our commitment includes comprehensive batch documentation, verified testing procedures, and strict handling protocols designed to maintain product integrity from production through delivery. By prioritising quality at every stage, we help researchers access materials they can trust for their laboratory work.',
        ],
        qualityImage: 'https://zenvypeptides.com/wp-content/uploads/2026/07/b69dec56-63c2-4f91-a619-13e10beaaa89.jpg',
    },

    help: {
        title: 'Nexus Peptides Support',
        intro:
            'Our dedicated support and dispatch team operates Monday to Friday, 9 AM – 8 PM (GMT), assisting with orders, tracking updates, and general customer enquiries across nationwide.',
        companyName: 'Nexus Peptides | An Advanced Health Company',
        faqTitle: 'Frequently Asked Questions',
        faqIntro:
            'Find clear answers about our research peptides, COAs, storage guidelines, ordering process, delivery information, and policies. This section is designed to ensure transparency, traceability, and strict research-use compliance.',
        faqs,
    },

    catalogue: {
        catalogueTitle: 'Peptide Research Catalogue',
        catalogueIntro:
            'Explore a curated selection of high-purity peptides designed to support accurate and reliable laboratory outcomes. Each product is quality-controlled and batch-traceable for consistent research performance.',
        shopTitle: 'Peptide Shop',
        emptyTitle: 'No products found',
        emptyBody: 'Try adjusting your filters or check back soon for new research compounds.',
    },

    coa: {
        title: 'Certificates of Analysis (COA)',
        intro:
            'View batch-specific Certificates of Analysis for each product to verify purity and testing standards. Select a product to access the latest COA associated with its current batch, ensuring full transparency and traceability.',
        coas: coas.map((coa) => ({
            name: coa.name,
            batch: coa.batch,
            date: coa.date,
            purity: coa.purity,
            image: coa.image,
            file: coa.pdf,
        })),
    },

    legal_terms: legal('terms'),
    legal_privacy: legal('privacy'),
    legal_returns: legal('returns'),
    legal_shipping: legal('shipping'),
    legal_disclaimer: legal('disclaimer'),
    store: {
        currency: 'AED',
        symbolStyle: 'svg',
        symbolPosition: 'before',
        symbolText: 'AED',
        decimals: 2,
        vatEnabled: true,
        vatRate: 5,
        vatIncludedInPrice: false,
        vatLabel: 'VAT (5%)',
        shippingMode: 'flat',
        flatRate: 25,
        freeShippingThreshold: 300,
        shippingOptions: [],
        codFee: 0,
        returnWindowDays: 14,
        ageConfirmation: true,
    },
};

export default contentDefaults;
