<?php

/*
|--------------------------------------------------------------------------
| Editable email templates
|--------------------------------------------------------------------------
|
| Every transactional email the store sends is listed here. The registry
| drives the admin "Email Templates" editor: each template's fields can be
| overridden in the email_templates table, and these definitions supply the
| human labels plus the sample variables used to preview and test-send.
|
| A template left untouched (no active row, or a null field) renders from
| the notification's built-in default, so editing is always opt-in.
|
| Variables are written in templates as {name} and substituted at send time.
| The "sample" value is what the preview and the test email substitute so an
| editor can see the finished wording before anything is really sent.
|
*/

return [

    'templates' => [

        'welcome' => [
            'label' => 'Welcome email',
            'description' => 'Sent once, right after a customer registers.',
            'variables' => [
                ['name' => 'name', 'sample' => 'Aisha', 'label' => 'Customer first name'],
                ['name' => 'support_email', 'sample' => 'info@nexuspeptides.com', 'label' => 'Support email'],
                ['name' => 'shop_url', 'sample' => 'http://localhost/peptide-shop', 'label' => 'Shop link'],
            ],
        ],

        'password_reset' => [
            'label' => 'Password reset link',
            'description' => 'Sent when a customer asks to reset their password.',
            'variables' => [
                ['name' => 'name', 'sample' => 'Aisha', 'label' => 'Customer first name'],
                ['name' => 'email', 'sample' => 'aisha@example.com', 'label' => 'Account email'],
                ['name' => 'expiry', 'sample' => '60', 'label' => 'Link lifetime (minutes)'],
            ],
        ],

        'order_status' => [
            'label' => 'Order status update',
            'description' => 'Sent to the customer each time an order status changes.',
            'variables' => [
                ['name' => 'name', 'sample' => 'Aisha', 'label' => 'Customer first name'],
                ['name' => 'order_number', 'sample' => 'NP-000123', 'label' => 'Order number'],
                ['name' => 'old_status', 'sample' => 'Processing', 'label' => 'Previous status'],
                ['name' => 'new_status', 'sample' => 'Shipped', 'label' => 'New status'],
                ['name' => 'note', 'sample' => 'Packed and handed to the courier.', 'label' => 'Status note'],
                ['name' => 'tracking', 'sample' => 'AED-TRACK-00099', 'label' => 'Tracking number'],
                ['name' => 'total', 'sample' => '249.00', 'label' => 'Order total (AED)'],
            ],
        ],

        'help_received' => [
            'label' => 'Help Center message (to your team)',
            'description' => 'Delivered to the delivery inbox when the contact form is submitted.',
            'variables' => [
                ['name' => 'customer_name', 'sample' => 'Aisha Al Marri', 'label' => 'Sender full name'],
                ['name' => 'customer_email', 'sample' => 'aisha@example.com', 'label' => 'Sender email'],
                ['name' => 'customer_mobile', 'sample' => '+971501234567', 'label' => 'Sender mobile'],
                ['name' => 'subject', 'sample' => 'Question about BPC-157', 'label' => 'Message subject'],
                ['name' => 'message', 'sample' => 'Do you have this peptide in stock?', 'label' => 'Message body'],
            ],
        ],

        'help_confirmation' => [
            'label' => 'Help Center receipt (to the customer)',
            'description' => 'The copy of their enquiry a customer receives after submitting the form.',
            'variables' => [
                ['name' => 'first_name', 'sample' => 'Aisha', 'label' => 'Customer first name'],
                ['name' => 'subject', 'sample' => 'Question about BPC-157', 'label' => 'Message subject'],
                ['name' => 'message', 'sample' => 'Do you have this peptide in stock?', 'label' => 'Message body'],
                ['name' => 'support_email', 'sample' => 'info@nexuspeptides.com', 'label' => 'Support email'],
            ],
        ],

    ],

    'cache_key' => 'email:templates',

];
