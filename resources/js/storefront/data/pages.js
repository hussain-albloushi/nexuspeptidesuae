/**
 * Static content pages.
 *
 * Block shapes:
 *   { h: 'Section heading' }   → bold heading
 *   { p: 'Paragraph text' }    → body paragraph
 */

export const pages = {
    terms: {
        title: 'Terms & Conditions',
        blocks: [
            { h: '1. Overview' },
            { p: 'By accessing or purchasing from Nexus Peptides, you agree to these Terms & Conditions. These terms apply to all visitors, users, and customers. If you do not agree, you should not use the website or place an order.' },

            { h: '2. Research Use Only' },
            { p: 'All products supplied by Nexus Peptides are strictly intended for laboratory and scientific research purposes only. They are not for human or veterinary use, ingestion, injection, or any medical, diagnostic, or therapeutic application. Products must not be used in clinical, cosmetic, or performance-related contexts. Any indication of intended misuse will result in order cancellation. Buyers are responsible for ensuring compliance with all applicable laws and laboratory safety regulations.' },

            { h: '3. Age and Professional Requirement' },
            { p: 'You must be at least 21 years old to purchase from Nexus Peptides. By placing an order, you confirm that you are authorised and competent to handle research materials. Orders placed under false information or for prohibited use will be refused.' },

            { h: '4. Order Acceptance' },
            { p: 'All orders are subject to acceptance and review by Nexus Peptides. We reserve the right to cancel or refuse any order where misuse or regulatory concerns are identified. Order details cannot be changed once dispatch has been completed. Customers are responsible for providing accurate shipping information.' },

            { h: '5. Pricing and Payment' },
            { p: 'All prices are displayed in AED. Payments are processed securely through trusted third-party providers. {{ name }} does not store or access payment card information. Orders are only processed once payment has been successfully confirmed.' },

            { h: '6. Shipping and Delivery' },
            { p: 'Nexus Peptides aims to dispatch orders promptly, typically the same business day for orders placed before 2 PM EST. Delivery times are estimates and may vary depending on courier performance or external factors. We are not responsible for delays caused by customs, courier disruptions, or other events outside our control.' },

            { h: '7. Returns and Refunds' },
            { p: 'Returns are only considered within 7 days of delivery and only in cases of verified damage, incorrect items, or courier-confirmed issues. Items must remain sealed and unused. Refunds will not be issued for opened products, incorrect address details, or uncollected parcels. All claims are subject to verification.' },

            { h: '8. Intellectual Property' },
            { p: 'All content, branding, product names, and materials on the Nexus Peptides website are the property of Nexus Peptides and may not be copied, reproduced, or distributed without permission.' },

            { h: '9. Limitation of Liability' },
            { p: 'All products are supplied strictly for research use. Nexus Peptides accepts no liability for any misuse, handling outside laboratory conditions, or unauthorised application. The buyer assumes full responsibility for safe handling and compliance. Liability is limited to the value of the purchased goods.' },

            { h: '10. Indemnification' },
            { p: 'By purchasing from Nexus Peptides, you agree to indemnify and hold harmless Nexus Peptides, its team, and partners against any claims or damages arising from misuse, legal violations, or breach of these terms.' },

            { h: '11. Compliance and Safety' },
            { p: 'Customers must handle all materials in accordance with applicable laboratory safety standards and regulations. Safety data sheets may be provided upon request. Nexus Peptides does not provide medical advice or usage instructions.' },

            { h: '12. Governing Law' },
            { p: 'These Terms & Conditions are governed by the laws of the United States and the state in which Nexus Peptides operates. Any disputes will be subject to the jurisdiction of that state’s courts.' },

            { h: '13. Agreement at Checkout' },
            { p: 'By completing checkout, customers confirm they are purchasing for research purposes only, are over 21, and agree to all Terms & Conditions before payment is processed.' },

            { h: '14. Updates' },
            { p: 'Nexus Peptides may update these Terms & Conditions at any time. The latest version will always apply from the moment it is published.' },

            { h: '15. Contact' },
            { p: 'For any queries or support, contact: support@nexuspeptides.com' },
        ],
    },

    privacy: {
        title: 'Privacy Policy',
        blocks: [
            { p: 'Nexus Peptides is committed to protecting your privacy and ensuring that all personal data is handled securely, transparently, and in accordance with applicable data protection laws. This Privacy Policy explains how your information is collected, used, and protected when you interact with our website and services.' },

            { h: '1. Who We Are' },
            { p: 'Nexus Peptides is a US-based supplier of research-use-only peptides. This Privacy Policy applies to all users and customers who access or purchase from our website. By using our services, you acknowledge and agree to the practices described in this policy.' },

            { h: '2. Information We Collect' },
            { p: 'Nexus Peptides may collect personal information such as your name, email address, billing and delivery details, and contact number when you place an order or contact support. We also process order-related information required to fulfil your purchase, along with secure payment processing handled by third-party providers. In addition, we may collect technical data such as IP address, browser type, device information, and website usage analytics to improve performance and security. Any information voluntarily submitted via contact forms or email may also be stored for support purposes. We do not store payment card details on our servers at any time.' },

            { h: '3. How We Use Your Information' },
            { p: 'Nexus Peptides uses collected data to process and fulfil orders, provide customer support, and ensure smooth website operation. Information may also be used to improve our services, enhance user experience, and maintain security standards. In certain cases, data may be used to detect or prevent fraudulent activity. Marketing communications are only sent if you have explicitly opted in. We do not sell or rent personal data to third parties.' },

            { h: '4. Legal Basis for Processing' },
            { p: 'We process personal data under lawful bases including contractual necessity (order fulfilment), legal obligations (such as accounting and tax compliance), legitimate business interests (such as improving services and preventing fraud), and user consent where applicable, such as marketing preferences.' },

            { h: '5. Cookies and Tracking Technologies' },
            { p: 'Nexus Peptides uses cookies to enhance website functionality, improve user experience, and analyse traffic patterns. Cookies help maintain shopping cart functionality, remember user preferences, and monitor website performance. Users may manage or disable cookies through their browser settings, although this may affect certain features of the website.' },

            { h: '6. Data Sharing' },
            { p: 'Personal data may be shared only with trusted third-party service providers involved in order processing, including payment gateways, courier services, analytics tools, and customer support systems. These providers are required to handle data securely and comply with applicable data protection regulations. Your information is only shared when necessary to deliver services or complete transactions.' },

            { h: '7. Data Security' },
            { p: 'Nexus Peptides implements appropriate security measures to protect your data, including encrypted connections, secure hosting environments, and restricted access controls. Payment information is handled exclusively by secure third-party processors. While we take strong precautions, no online system can guarantee absolute security.' },

            { h: '8. Your Rights' },
            { p: 'Depending on your state of residence, you may have the right to access, update, delete, or restrict the use of your personal data, as well as the right to opt out of the sale or sharing of personal information where applicable (for example, under state privacy laws such as the California Consumer Privacy Act (CCPA)). You may also withdraw consent for marketing communications at any time. To exercise any of these rights, you can contact support at support@nexuspeptides.com.' },

            { h: '9. Data Retention' },
            { p: 'Personal data is retained only for as long as necessary to fulfil its intended purpose. Order and transaction records may be retained for legal and accounting requirements, typically up to six years. Marketing data is retained until you opt out, and analytics data is stored in accordance with provider policies.' },

            { h: '10. International Data Transfers' },
            { p: 'Some data may be processed by service providers located outside the United States. In such cases, appropriate safeguards are implemented to ensure data protection standards remain consistent and secure.' },

            { h: '11. Third-Party Links' },
            { p: 'Our website may include links to external websites. Nexus Peptides is not responsible for the privacy practices or content of third-party sites.' },

            { h: '12. Policy Updates' },
            { p: 'Nexus Peptides may update this Privacy Policy from time to time to reflect operational, legal, or regulatory changes. Any updates will be published on this page with immediate effect.' },

            { h: 'Contact Information' },
            { p: 'For any privacy-related inquiries, please contact us at support@nexuspeptides.com' },
        ],
    },

    returns: {
        title: 'Return & Refunds',
        blocks: [
            { p: 'Due to the nature of research-use-only laboratory products, Nexus Peptides cannot accept returns once items have been shipped and opened. For hygiene, safety, and quality control reasons, any opened, used, or tampered products are not eligible for return or resale.' },

            { p: 'Customers are responsible for ensuring all order details, including product selection and delivery address, are correct before completing checkout. If your order arrives damaged, incorrect, or there is a delivery-related issue, you must contact Nexus Peptides support immediately. Items should not be returned without prior approval. All issues must be reported within 7 days of delivery and supported with clear photographic or video evidence, including the condition of the sealed packaging before opening where applicable.' },

            { h: 'Refunds & Replacements' },
            { p: 'Nexus Peptides may offer a refund or replacement only in verified cases such as damaged goods in transit, incorrect items received, or confirmed courier-related delivery failure. Lost parcels may also be reviewed after completion of a courier investigation. Each case is assessed individually in line with applicable consumer protection standards and internal verification procedures. Refunds or replacements will not be issued if more than 7 days have passed since delivery, if incorrect address details were provided, if parcels were refused or uncollected, or if items have been opened, used, or altered in any way.' },

            { h: 'Missing or Damaged Items' },
            { p: 'All orders are securely packaged to ensure integrity during transit. If a missing item is reported, proof must be provided showing the unopened and sealed package, including visible shipping labels. Once packaging has been opened or tampered with, verification is no longer possible and claims cannot be processed. Due to fraud prevention controls, supporting evidence such as clear images or video of the unboxing process may be required for validation.' },

            { h: 'Important Conditions' },
            { p: 'Claims must be submitted within 7 days of delivery. Items must remain in original sealed condition until the issue is reviewed. Courier investigations may be required for lost or delayed parcels, and refunds or replacements cannot be processed while investigations are ongoing. For support, contact: support@nexuspeptides.com' },
        ],
    },

    shipping: {
        title: 'Shipping & Delivery',
        blocks: [
            { p: 'At Nexus Peptides, we prioritise fast, secure, and reliable delivery for all research-use-only peptide orders. Every shipment is handled with care to ensure efficient dispatch, full tracking, and consistent service across the United States and selected international destinations.' },

            { h: 'Dispatch Times' },
            { p: 'Nexus Peptides processes orders quickly to minimise delays. Orders placed before 2:00 PM EST (Monday–Friday) are usually dispatched the same business day, while orders placed after this time are processed on the next business day. Orders placed on Fridays after cut-off or over the weekend are dispatched on Monday. During peak periods, dispatch may occasionally extend by one business day due to higher order volume. Once your order is dispatched, tracking details are sent via email so you can monitor your shipment in real time.' },

            { h: 'US Delivery' },
            { p: 'Nexus Peptides offers fast and reliable domestic shipping through USPS and UPS services, including Priority Mail, Priority Mail Express, and UPS Ground. Priority services typically arrive within 1–3 business days, while Priority Mail Express provides a guaranteed next-day or two-day option depending on destination. All US orders are shipped discreetly and securely. While we aim for rapid delivery, occasional delays may occur due to courier handling or peak periods.' },

            { h: 'International Shipping' },
            { p: 'Nexus Peptides also ships internationally to selected destinations worldwide. Delivery times typically range from 5–10 business days depending on customs processing and local postal services. All international timelines are estimates and may vary based on destination country procedures.' },

            { h: 'Customs & Import Information' },
            { p: 'International orders may be subject to local customs duties, taxes, or handling fees depending on the destination country. These charges are not included at checkout and remain the responsibility of the customer. Customs clearance times vary by region and are outside of Nexus Peptides’ control. Delays during customs processing do not indicate a lost parcel.' },

            { h: 'Tracking & Delivery Support' },
            { p: 'All orders include tracking where applicable. In case of delays or delivery issues, courier investigations are initiated to resolve the matter efficiently. Domestic cases typically take up to 15 business days, while international cases may take up to 30 business days. For assistance, contact us at support@nexuspeptides.com.' },
        ],
    },

    disclaimer: {
        title: 'Disclaimer - RUO',
        blocks: [
            { p: 'All products offered by Nexus Peptides are strictly for research and laboratory use only and are not intended for human consumption, medical, therapeutic, diagnostic, or clinical purposes. Nexus Peptides does not support or condone any misuse of research materials under any circumstances.' },

            { p: 'These products are not medical or pharmaceutical products and must not be represented or used as such in any form. The purchaser is solely responsible for ensuring full compliance with all applicable laws, regulations, and ethical standards relating to research materials.' },

            { p: 'By purchasing from Nexus Peptides, you accept full responsibility for the safe storage, handling, and lawful use of all products. Nexus Peptides shall not be held liable for any misuse, improper handling, or consequences arising from use outside legitimate research environments.' },

            { p: 'Your purchase confirms that you understand and agree to these terms.' },
        ],
    },
};

/** Footer / shared site copy. */
export const site = {
    name: 'Nexus Peptides',
    logo: '/storage/Nexus_Peptides_UAE_Biotech_Logo_preview.png',
    tagline:
        'Nexus Peptides is a US supplier of premium research peptides with verified purity, certification, and full traceability.',
    topbar: 'For Lab & Research Use Only (RUO) - Not for Human Consumption',
    heroKicker: 'For Research Use Only – RUO',
    heroTitle: 'Purity You Can Verify. Research You Can Trust.',
    heroBody:
        'Nexus Peptides delivers high-purity research compounds built for accuracy and reproducibility. Every batch is independently tested and fully traceable, giving laboratories a dependable foundation for advanced scientific work.',
    heroCta: 'Shop Peptides',
    heroImage: '/site-images/hero-peptides.jpg',
    address: ['6706 West Fairview Ave', 'Boise, Idaho USA 83704', '(208) 274-8200'],
    supportEmail: 'info@nexuspeptides.com',
    copyright: '© 2026 Nexus Peptides – All rights reserved.',
    ruo: 'For Research Use Only (RUO) – Not intended for human consumption.',
    legalNotice:
        'All Nexus Peptides products are supplied strictly for laboratory research use only, not for human or veterinary use, consumption, or injection, and not approved by the FDA to diagnose, treat, cure, or prevent any disease. Nothing on this website constitutes medical advice or usage guidance. By purchasing, you confirm the products are for legitimate research purposes and agree to comply with all applicable laws, assuming full responsibility for safe handling and use. Products are supplied “as is,” with Nexus Peptides accepting no liability for any misuse.',
    socials: [
        { name: 'Instagram', icon: 'fa-brands fa-instagram', url: '#' },
        { name: 'Facebook', icon: 'fa-brands fa-facebook-f', url: '#' },
        { name: 'Youtube', icon: 'fa-brands fa-youtube', url: '#' },
        { name: 'X-twitter', icon: 'fa-brands fa-x-twitter', url: '#' },
    ],
    nav: [
        { label: 'Home', to: '/' },
        { label: 'All Peptides', to: '/all-peptides' },
        { label: 'About Us', to: '/about-us' },
        { label: 'Help Center', to: '/help-center' },
    ],
    footerNav: [
        { label: 'Home', to: '/' },
        { label: 'All Peptides', to: '/all-peptides' },
        { label: 'About us', to: '/about-us' },
        { label: 'Help Center', to: '/help-center' },
    ],
    legalNav: [
        { label: 'Terms & Conditions', to: '/terms-conditions' },
        { label: 'Privacy Policy', to: '/privacy-policy' },
        { label: 'Return & Refunds', to: '/return-refunds' },
        { label: 'Shipping & Delivery', to: '/shipping-delivery' },
        { label: 'Disclaimer', to: '/disclaimer' },
    ],
    newsletter: {
        title: 'Research Updates, Simplified',
        body: 'Get the latest on high-purity peptides, testing standards, and research updates.',
    },
    /** The four-up strip repeated across every page of the source site. */
    features: [
        {
            title: 'Secure Checkout',
            body: 'Advanced encryption ensures every transaction is fully protected.',
            icon: 'fa-solid fa-shield-halved',
        },
        {
            title: 'Discreet Packaging',
            body: 'Orders are shipped in plain packaging to maintain complete confidentiality.',
            icon: 'fa-solid fa-box',
        },
        {
            title: 'Fast Delivery',
            body: 'Reliable and timely shipping through our trusted delivery partners.',
            icon: 'fa-solid fa-truck-fast',
        },
        {
            title: 'Satisfaction Guarantee',
            body: 'Dedicated to delivering consistent quality and dependable service every time.',
            icon: 'fa-solid fa-circle-check',
        },
    ],
};

export default pages;
