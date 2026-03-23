<?php

return [
    'tiers' => [
        'free' => [
            'max_agents' => 3,
            'max_tickets_per_month' => 100,
            'max_storage_mb' => 500,
            'features' => [
                'ai_classification' => true,
                'ai_suggestions' => false,
                'chatbot' => false,
                'custom_domain' => false,
                'sla_policies' => false,
                'service_catalog' => false,
                'api_access' => false,
            ],
        ],
        'basico' => [
            'max_agents' => 10,
            'max_tickets_per_month' => 1000,
            'max_storage_mb' => 5000,
            'features' => [
                'ai_classification' => true,
                'ai_suggestions' => true,
                'chatbot' => true,
                'custom_domain' => false,
                'sla_policies' => true,
                'service_catalog' => true,
                'api_access' => false,
            ],
        ],
        'profesional' => [
            'max_agents' => 50,
            'max_tickets_per_month' => 10000,
            'max_storage_mb' => 50000,
            'features' => [
                'ai_classification' => true,
                'ai_suggestions' => true,
                'chatbot' => true,
                'custom_domain' => true,
                'sla_policies' => true,
                'service_catalog' => true,
                'api_access' => true,
            ],
        ],
        'enterprise' => [
            'max_agents' => -1,
            'max_tickets_per_month' => -1,
            'max_storage_mb' => -1,
            'features' => [
                'ai_classification' => true,
                'ai_suggestions' => true,
                'chatbot' => true,
                'custom_domain' => true,
                'sla_policies' => true,
                'service_catalog' => true,
                'api_access' => true,
            ],
        ],
    ],
];
