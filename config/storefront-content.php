<?php

/*
|--------------------------------------------------------------------------
| Storefront content schema
|--------------------------------------------------------------------------
|
| This file is the single source of truth for the storefront content editor.
| Each group becomes one tab in the admin Content screen. Fields are rendered
| by the Vue editor, so adding an editable field is a config-only change.
|
| Field types: text, textarea, lines, image, file, select, repeater.
|
*/

return [

    'cache_key' => 'storefront:content',
    'cache_ttl' => 3600,
    'shipping_defaults' => [
        'shippingMode' => 'flat',
        'flatRate' => 25,
        'freeShippingThreshold' => 300,
        'codFee' => 0,
    ],

    'upload' => [
        'disk' => 'public',
        'path' => 'content',
        'max_kb' => 10240,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'],
    ],

    'groups' => [

        'site' => [
            'label' => 'Site & Footer',
            'icon' => 'bi-globe2',
            'description' => 'Brand identity, contact details, navigation, and footer content shown across every page.',
            'live_url' => '/',
            'sections' => [
                [
                    'title' => 'Brand identity',
                    'description' => 'These details appear in the header, footer, and browser tab.',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Store name', 'required' => true, 'max' => 80],
                        ['name' => 'logo', 'type' => 'image', 'label' => 'Logo', 'help' => 'A transparent PNG or WebP works best. Shown in the header, footer, and mobile menu.'],
                        ['name' => 'tagline', 'type' => 'textarea', 'label' => 'Footer tagline', 'max' => 240, 'rows' => 3, 'help' => 'A short sentence under the logo in the footer.'],
                        ['name' => 'topbar', 'type' => 'text', 'label' => 'Announcement bar', 'max' => 160, 'help' => 'The short research-use notice at the very top of the site.'],
                    ],
                ],
                [
                    'title' => 'Contact details',
                    'description' => 'Used in the Help Center, footer, and support links.',
                    'fields' => [
                        ['name' => 'supportEmail', 'type' => 'text', 'label' => 'Support email', 'required' => true, 'max' => 120],
                        ['name' => 'address', 'type' => 'lines', 'label' => 'Address & phone', 'help' => 'One line per row.', 'rows' => 4],
                    ],
                ],
                [
                    'title' => 'Search engine listing',
                    'description' => 'What search engines and browser tabs show for the storefront home page.',
                    'fields' => [
                        ['name' => 'seoTitle', 'type' => 'text', 'label' => 'Browser tab title', 'required' => true, 'max' => 70],
                        ['name' => 'seoDescription', 'type' => 'textarea', 'label' => 'Meta description', 'max' => 180, 'rows' => 3],
                    ],
                ],
                [
                    'title' => 'Footer legal copy',
                    'fields' => [
                        ['name' => 'copyright', 'type' => 'text', 'label' => 'Copyright line', 'max' => 120, 'help' => 'The year is always rendered dynamically — use {year} where the year should appear (any year already in the text is replaced with the current one).'],
                        ['name' => 'ruo', 'type' => 'text', 'label' => 'Research-use line', 'max' => 160],
                        ['name' => 'legalNotice', 'type' => 'textarea', 'label' => 'Full legal notice', 'rows' => 5, 'max' => 1200],
                    ],
                ],
                [
                    'title' => 'Navigation',
                    'description' => 'Links shown in the header, footer, and legal column.',
                    'fields' => [
                        [
                            'name' => 'nav', 'type' => 'repeater', 'label' => 'Main navigation',
                            'add_label' => 'Add navigation link', 'item_label' => 'label', 'max_items' => 8,
                            'fields' => [
                                ['name' => 'label', 'type' => 'text', 'label' => 'Label', 'required' => true, 'max' => 40],
                                ['name' => 'to', 'type' => 'text', 'label' => 'Link', 'required' => true, 'max' => 120, 'help' => 'A site path such as /about-us or /coa.'],
                            ],
                        ],
                        [
                            'name' => 'footerNav', 'type' => 'repeater', 'label' => 'Footer navigation',
                            'add_label' => 'Add footer link', 'item_label' => 'label', 'max_items' => 8,
                            'fields' => [
                                ['name' => 'label', 'type' => 'text', 'label' => 'Label', 'required' => true, 'max' => 40],
                                ['name' => 'to', 'type' => 'text', 'label' => 'Link', 'required' => true, 'max' => 120],
                            ],
                        ],
                        [
                            'name' => 'legalNav', 'type' => 'repeater', 'label' => 'Legal navigation',
                            'add_label' => 'Add legal link', 'item_label' => 'label', 'max_items' => 8,
                            'fields' => [
                                ['name' => 'label', 'type' => 'text', 'label' => 'Label', 'required' => true, 'max' => 40],
                                ['name' => 'to', 'type' => 'text', 'label' => 'Link', 'required' => true, 'max' => 120],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Social links',
                    'fields' => [
                        [
                            'name' => 'socials', 'type' => 'repeater', 'label' => 'Social icons',
                            'add_label' => 'Add social link', 'item_label' => 'name', 'max_items' => 8,
                            'fields' => [
                                ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 40],
                                ['name' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'sets' => ['brands', 'solid'], 'help' => 'Pick the social or contact icon. No code needed.'],
                                ['name' => 'url', 'type' => 'text', 'label' => 'Link', 'max' => 200],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Newsletter box',
                    'fields' => [
                        ['name' => 'newsletterTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 80],
                        ['name' => 'newsletterBody', 'type' => 'textarea', 'label' => 'Description', 'max' => 240, 'rows' => 3],
                    ],
                ],
                [
                    'title' => 'Quality standards cards',
                    'description' => 'The four cards used on the Home and About pages.',
                    'fields' => [
                        [
                            'name' => 'standards', 'type' => 'repeater', 'label' => 'Cards',
                            'add_label' => 'Add card', 'item_label' => 'title', 'max_items' => 6,
                            'fields' => [
                                ['name' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'help' => 'Pick an icon from the list. No code needed.'],
                                ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 80],
                                ['name' => 'body', 'type' => 'textarea', 'label' => 'Description', 'required' => true, 'max' => 300, 'rows' => 3],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Service strip',
                    'description' => 'The scrolling promises shown below the header on every page.',
                    'fields' => [
                        [
                            'name' => 'features', 'type' => 'repeater', 'label' => 'Items',
                            'add_label' => 'Add item', 'item_label' => 'title', 'max_items' => 8,
                            'fields' => [
                                ['name' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'help' => 'Pick an icon from the list. No code needed.'],
                                ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 80],
                                ['name' => 'body', 'type' => 'textarea', 'label' => 'Description', 'required' => true, 'max' => 240, 'rows' => 2],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'store' => [
            'label' => 'Store Settings',
            'icon' => 'bi-gear',
            'description' => 'Currency, VAT, shipping, returns, and checkout configuration for the UAE storefront.',
            'sections' => [
                [
                    'title' => 'Currency',
                    'description' => 'How prices and currency symbols are displayed across the storefront.',
                    'fields' => [
                        ['name' => 'currency', 'type' => 'currency', 'label' => 'Currency settings', 'required' => true],
                    ],
                ],
                [
                    'title' => 'VAT',
                    'description' => 'Value-added tax configuration for UAE storefront.',
                    'fields' => [
                        ['name' => 'vatEnabled', 'type' => 'select', 'label' => 'VAT enabled', 'required' => true, 'options' => [
                            ['value' => 'true', 'label' => 'Yes'],
                            ['value' => 'false', 'label' => 'No'],
                        ]],
                        ['name' => 'vatRate', 'type' => 'text', 'label' => 'VAT rate (%)', 'max' => 6, 'help' => 'Standard VAT rate, for example 5.'],
                        ['name' => 'vatIncludedInPrice', 'type' => 'select', 'label' => 'VAT included in price', 'required' => true, 'options' => [
                            ['value' => 'true', 'label' => 'Yes (prices shown include VAT)'],
                            ['value' => 'false', 'label' => 'No (VAT added at checkout)'],
                        ]],
                        ['name' => 'vatLabel', 'type' => 'text', 'label' => 'VAT display label', 'max' => 40, 'help' => 'Label shown next to VAT amounts, for example "VAT (5%)".'],
                    ],
                ],
                [
                    'title' => 'Shipping',
                    'description' => 'Shared by cart totals, checkout, and order calculations. Eligibility uses the item subtotal before coupons, tax, and shipping.',
                    'fields' => [
                        ['name' => 'shippingMode', 'type' => 'select', 'label' => 'Shipping mode', 'required' => true, 'options' => [
                            ['value' => 'flat', 'label' => 'Flat rate'],
                            ['value' => 'free', 'label' => 'Free shipping'],
                        ]],
                        ['name' => 'flatRate', 'type' => 'text', 'input_type' => 'number', 'min' => 0, 'step' => '0.01', 'label' => 'Delivery charge (AED)', 'help' => 'Charged below the free-shipping threshold when Flat rate is selected.'],
                        ['name' => 'freeShippingThreshold', 'type' => 'text', 'input_type' => 'number', 'min' => 0, 'step' => '0.01', 'label' => 'Free-shipping minimum subtotal (AED)', 'help' => 'Shipping is free at or above this amount. Leave blank to disable the threshold.'],
                    ],
                ],
                [
                    'title' => 'Cash on delivery',
                    'description' => 'Handling fee for cash-on-delivery payment, charged only when Cash on delivery is selected at checkout. Eligibility uses the item subtotal before coupons, tax, and shipping.',
                    'fields' => [
                        ['name' => 'codFee', 'type' => 'text', 'input_type' => 'number', 'min' => 0, 'step' => '0.01', 'label' => 'Cash on delivery fee (AED)', 'help' => 'Charged once per order when the customer pays cash on delivery. Leave 0 to disable.'],
                    ],
                ],
                [
                    'title' => 'Returns',
                    'description' => 'Return window and policy configuration.',
                    'fields' => [
                        ['name' => 'returnWindowDays', 'type' => 'text', 'label' => 'Return window (days)', 'max' => 4, 'help' => 'Number of days customers have to request a return after delivery (default 14).'],
                    ],
                ],
                [
                    'title' => 'Checkout',
                    'description' => 'Checkout flow settings, including age confirmation.',
                    'fields' => [
                        ['name' => 'ageConfirmation', 'type' => 'select', 'label' => 'Age confirmation (18+)', 'required' => true, 'options' => [
                            ['value' => 'true', 'label' => 'Require 18+ confirmation at checkout'],
                            ['value' => 'false', 'label' => 'Do not require age confirmation'],
                        ]],
                    ],
                ],
            ],
        ],

        'home' => [
            'label' => 'Home',
            'icon' => 'bi-house-door',
            'description' => 'The home page hero, product grid, quality section, and purity story.',
            'live_url' => '/',
            'sections' => [
                [
                    'title' => 'Hero',
                    'description' => 'The first thing visitors see on the home page.',
                    'fields' => [
                        ['name' => 'heroKicker', 'type' => 'text', 'label' => 'Small label above heading', 'max' => 80],
                        ['name' => 'heroTitle', 'type' => 'text', 'label' => 'Main heading', 'required' => true, 'max' => 140],
                        ['name' => 'heroBody', 'type' => 'textarea', 'label' => 'Intro paragraph', 'required' => true, 'max' => 500, 'rows' => 4],
                        ['name' => 'heroCta', 'type' => 'text', 'label' => 'Button text', 'max' => 40],
                        ['name' => 'heroImage', 'type' => 'image', 'label' => 'Hero image'],
                    ],
                ],
                [
                    'title' => 'Standards heading',
                    'fields' => [
                        ['name' => 'standardsTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 120],
                    ],
                ],
                [
                    'title' => 'Product grid',
                    'fields' => [
                        ['name' => 'productsTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 140],
                        ['name' => 'productsIntro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 400, 'rows' => 3],
                        ['name' => 'productsCta', 'type' => 'text', 'label' => 'Button text', 'max' => 40],
                    ],
                ],
                [
                    'title' => 'Trusted quality section',
                    'fields' => [
                        ['name' => 'trustedTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 140],
                        ['name' => 'trustedBody', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 500, 'rows' => 4],
                        ['name' => 'trustedItems', 'type' => 'lines', 'label' => 'Checklist items', 'help' => 'One item per row.', 'rows' => 5],
                        ['name' => 'trustedCta', 'type' => 'text', 'label' => 'Button text', 'max' => 40],
                        ['name' => 'trustedImage', 'type' => 'image', 'label' => 'Section image'],
                    ],
                ],
                [
                    'title' => 'Purity story',
                    'description' => 'The dark navy section near the bottom of the home page.',
                    'fields' => [
                        ['name' => 'purityKicker', 'type' => 'text', 'label' => 'Small label above heading', 'max' => 80],
                        ['name' => 'purityTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 160],
                        ['name' => 'puritySubtitle', 'type' => 'text', 'label' => 'Sub-heading', 'max' => 160],
                        ['name' => 'purityParagraphs', 'type' => 'lines', 'label' => 'Paragraphs', 'help' => 'One paragraph per row.', 'rows' => 8],
                        ['name' => 'purityNotice', 'type' => 'text', 'label' => 'Highlighted notice', 'max' => 200],
                        ['name' => 'purityImage', 'type' => 'image', 'label' => 'Section image'],
                    ],
                ],
            ],
        ],

        'about' => [
            'label' => 'About',
            'icon' => 'bi-info-circle',
            'description' => 'The About Us page story and quality sections.',
            'live_url' => '/about-us',
            'sections' => [
                [
                    'title' => 'Page header',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Page title', 'required' => true, 'max' => 80],
                    ],
                ],
                [
                    'title' => 'Who we are',
                    'fields' => [
                        ['name' => 'kicker', 'type' => 'text', 'label' => 'Small label above heading', 'max' => 60],
                        ['name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max' => 160],
                        ['name' => 'body', 'type' => 'textarea', 'label' => 'Paragraph', 'max' => 1200, 'rows' => 6],
                        ['name' => 'cta', 'type' => 'text', 'label' => 'Button text', 'max' => 40],
                        ['name' => 'image', 'type' => 'image', 'label' => 'Section image'],
                    ],
                ],
                [
                    'title' => 'Why choose section',
                    'description' => 'Uses the quality standards cards from the Site & Footer tab.',
                    'fields' => [
                        ['name' => 'whyTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 140],
                        ['name' => 'whyIntro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 400, 'rows' => 3],
                    ],
                ],
                [
                    'title' => 'Quality section',
                    'fields' => [
                        ['name' => 'qualityKicker', 'type' => 'text', 'label' => 'Small label above heading', 'max' => 80],
                        ['name' => 'qualityTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 140],
                        ['name' => 'qualitySubtitle', 'type' => 'text', 'label' => 'Sub-heading', 'max' => 140],
                        ['name' => 'qualityParagraphs', 'type' => 'lines', 'label' => 'Paragraphs', 'help' => 'One paragraph per row.', 'rows' => 6],
                        ['name' => 'qualityImage', 'type' => 'image', 'label' => 'Section image'],
                    ],
                ],
            ],
        ],

        'help' => [
            'label' => 'Help & FAQ',
            'icon' => 'bi-life-preserver',
            'description' => 'Support page copy and the frequently asked questions.',
            'live_url' => '/help-center',
            'sections' => [
                [
                    'title' => 'Page header',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Page title', 'required' => true, 'max' => 100],
                    ],
                ],
                [
                    'title' => 'Support introduction',
                    'fields' => [
                        ['name' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 600, 'rows' => 4],
                        ['name' => 'companyName', 'type' => 'text', 'label' => 'Company card heading', 'max' => 120],
                    ],
                ],
                [
                    'title' => 'Frequently asked questions',
                    'fields' => [
                        ['name' => 'faqTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 120],
                        ['name' => 'faqIntro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 400, 'rows' => 3],
                        [
                            'name' => 'faqs', 'type' => 'repeater', 'label' => 'Questions',
                            'add_label' => 'Add question', 'item_label' => 'q', 'max_items' => 30,
                            'fields' => [
                                ['name' => 'q', 'type' => 'text', 'label' => 'Question', 'required' => true, 'max' => 160],
                                ['name' => 'a', 'type' => 'textarea', 'label' => 'Answer', 'required' => true, 'max' => 1200, 'rows' => 4],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'catalogue' => [
            'label' => 'Catalogue pages',
            'icon' => 'bi-grid',
            'description' => 'Headings and empty-state copy for the All Peptides and Shop pages.',
            'live_url' => '/all-peptides',
            'sections' => [
                [
                    'title' => 'All Peptides page',
                    'fields' => [
                        ['name' => 'catalogueTitle', 'type' => 'text', 'label' => 'Page heading', 'max' => 120],
                        ['name' => 'catalogueIntro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 400, 'rows' => 3],
                    ],
                ],
                [
                    'title' => 'Shop page',
                    'fields' => [
                        ['name' => 'shopTitle', 'type' => 'text', 'label' => 'Page heading', 'max' => 120],
                    ],
                ],
                [
                    'title' => 'Empty state',
                    'description' => 'Shown when no products match the current filter.',
                    'fields' => [
                        ['name' => 'emptyTitle', 'type' => 'text', 'label' => 'Heading', 'max' => 100],
                        ['name' => 'emptyBody', 'type' => 'textarea', 'label' => 'Description', 'max' => 240, 'rows' => 3],
                    ],
                ],
            ],
        ],

        'coa' => [
            'label' => 'Certificates (COA)',
            'icon' => 'bi-file-earmark-check',
            'description' => 'Certificate of Analysis page copy and batch records.',
            'live_url' => '/coa',
            'sections' => [
                [
                    'title' => 'Page copy',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Page heading', 'required' => true, 'max' => 140],
                        ['name' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 500, 'rows' => 4],
                    ],
                ],
                [
                    'title' => 'Certificates',
                    'fields' => [
                        [
                            'name' => 'coas', 'type' => 'repeater', 'label' => 'Certificate rows',
                            'add_label' => 'Add certificate', 'item_label' => 'name', 'max_items' => 100,
                            'fields' => [
                                ['name' => 'name', 'type' => 'text', 'label' => 'Product name', 'required' => true, 'max' => 120],
                                ['name' => 'batch', 'type' => 'text', 'label' => 'Batch', 'max' => 40],
                                ['name' => 'date', 'type' => 'text', 'label' => 'Test date', 'max' => 40],
                                ['name' => 'purity', 'type' => 'text', 'label' => 'Purity', 'max' => 40],
                                ['name' => 'image', 'type' => 'image', 'label' => 'Certificate image'],
                                ['name' => 'file', 'type' => 'file', 'label' => 'Certificate PDF'],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'legal_terms' => [
            'label' => 'Terms & Conditions',
            'icon' => 'bi-file-text',
            'description' => 'The Terms & Conditions page.',
            'live_url' => '/terms-conditions',
            'sections' => [
                [
                    'title' => 'Page content',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Page title', 'required' => true, 'max' => 120],
                        ['name' => 'blocks', 'type' => 'repeater', 'label' => 'Content blocks', 'add_label' => 'Add block', 'item_label' => 'text', 'max_items' => 100, 'fields' => [
                            ['name' => 'kind', 'type' => 'select', 'label' => 'Block type', 'options' => [
                                ['value' => 'heading', 'label' => 'Heading'],
                                ['value' => 'paragraph', 'label' => 'Paragraph'],
                            ]],
                            ['name' => 'text', 'type' => 'textarea', 'label' => 'Text', 'required' => true, 'max' => 3000, 'rows' => 5],
                        ]],
                    ],
                ],
            ],
        ],

        'legal_privacy' => [
            'label' => 'Privacy Policy',
            'icon' => 'bi-shield-lock',
            'description' => 'The Privacy Policy page.',
            'live_url' => '/privacy-policy',
            'sections' => [
                [
                    'title' => 'Page content',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Page title', 'required' => true, 'max' => 120],
                        ['name' => 'blocks', 'type' => 'repeater', 'label' => 'Content blocks', 'add_label' => 'Add block', 'item_label' => 'text', 'max_items' => 100, 'fields' => [
                            ['name' => 'kind', 'type' => 'select', 'label' => 'Block type', 'options' => [
                                ['value' => 'heading', 'label' => 'Heading'],
                                ['value' => 'paragraph', 'label' => 'Paragraph'],
                            ]],
                            ['name' => 'text', 'type' => 'textarea', 'label' => 'Text', 'required' => true, 'max' => 3000, 'rows' => 5],
                        ]],
                    ],
                ],
            ],
        ],

        'legal_returns' => [
            'label' => 'Return & Refunds',
            'icon' => 'bi-arrow-counterclockwise',
            'description' => 'The Return & Refunds page.',
            'live_url' => '/return-refunds',
            'sections' => [
                [
                    'title' => 'Page content',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Page title', 'required' => true, 'max' => 120],
                        ['name' => 'blocks', 'type' => 'repeater', 'label' => 'Content blocks', 'add_label' => 'Add block', 'item_label' => 'text', 'max_items' => 100, 'fields' => [
                            ['name' => 'kind', 'type' => 'select', 'label' => 'Block type', 'options' => [
                                ['value' => 'heading', 'label' => 'Heading'],
                                ['value' => 'paragraph', 'label' => 'Paragraph'],
                            ]],
                            ['name' => 'text', 'type' => 'textarea', 'label' => 'Text', 'required' => true, 'max' => 3000, 'rows' => 5],
                        ]],
                    ],
                ],
            ],
        ],

        'legal_shipping' => [
            'label' => 'Shipping & Delivery',
            'icon' => 'bi-truck',
            'description' => 'The Shipping & Delivery page.',
            'live_url' => '/shipping-delivery',
            'sections' => [
                [
                    'title' => 'Page content',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Page title', 'required' => true, 'max' => 120],
                        ['name' => 'blocks', 'type' => 'repeater', 'label' => 'Content blocks', 'add_label' => 'Add block', 'item_label' => 'text', 'max_items' => 100, 'fields' => [
                            ['name' => 'kind', 'type' => 'select', 'label' => 'Block type', 'options' => [
                                ['value' => 'heading', 'label' => 'Heading'],
                                ['value' => 'paragraph', 'label' => 'Paragraph'],
                            ]],
                            ['name' => 'text', 'type' => 'textarea', 'label' => 'Text', 'required' => true, 'max' => 3000, 'rows' => 5],
                        ]],
                    ],
                ],
            ],
        ],

        'legal_disclaimer' => [
            'label' => 'Disclaimer',
            'icon' => 'bi-exclamation-triangle',
            'description' => 'The Research Use Only disclaimer page.',
            'live_url' => '/disclaimer',
            'sections' => [
                [
                    'title' => 'Page content',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Page title', 'required' => true, 'max' => 120],
                        ['name' => 'blocks', 'type' => 'repeater', 'label' => 'Content blocks', 'add_label' => 'Add block', 'item_label' => 'text', 'max_items' => 100, 'fields' => [
                            ['name' => 'kind', 'type' => 'select', 'label' => 'Block type', 'options' => [
                                ['value' => 'heading', 'label' => 'Heading'],
                                ['value' => 'paragraph', 'label' => 'Paragraph'],
                            ]],
                            ['name' => 'text', 'type' => 'textarea', 'label' => 'Text', 'required' => true, 'max' => 3000, 'rows' => 5],
                        ]],
                    ],
                ],
            ],
        ],

    ],
];
