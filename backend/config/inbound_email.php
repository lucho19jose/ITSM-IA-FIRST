<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inbound Email - Ticket creation from email
    |--------------------------------------------------------------------------
    |
    | Configure how emails are converted into tickets.
    | Supports: webhook (Mailgun/SendGrid/Postmark) and IMAP polling.
    |
    */

    'enabled' => env('INBOUND_EMAIL_ENABLED', false),

    // Webhook secret for verifying inbound email provider requests
    'webhook_secret' => env('INBOUND_EMAIL_WEBHOOK_SECRET'),

    // IMAP polling configuration
    'imap' => [
        'host' => env('INBOUND_EMAIL_IMAP_HOST'),
        'port' => env('INBOUND_EMAIL_IMAP_PORT', 993),
        'encryption' => env('INBOUND_EMAIL_IMAP_ENCRYPTION', 'ssl'),
        'username' => env('INBOUND_EMAIL_IMAP_USERNAME'),
        'password' => env('INBOUND_EMAIL_IMAP_PASSWORD'),
        'folder' => env('INBOUND_EMAIL_IMAP_FOLDER', 'INBOX'),
        'delete_after_processing' => env('INBOUND_EMAIL_DELETE_PROCESSED', false),
    ],

    // Default tenant slug for emails that don't match a specific tenant
    // Format support+{tenant_slug}@autoservice.pe
    'default_tenant_slug' => env('INBOUND_EMAIL_DEFAULT_TENANT'),

    // Email address pattern: support+{slug}@domain.com
    'address_pattern' => env('INBOUND_EMAIL_ADDRESS', 'soporte@autoservice.pe'),

];
