<?php

namespace App\Services;

use App\Jobs\SendWebhookNotificationJob;
use App\Models\Integration;
use App\Models\Tenant;

class WebhookNotificationService
{
    public const EVENT_TICKET_CREATED = 'ticket_created';
    public const EVENT_TICKET_ASSIGNED = 'ticket_assigned';
    public const EVENT_TICKET_CLOSED = 'ticket_closed';
    public const EVENT_SLA_BREACH = 'sla_breach';
    public const EVENT_TICKET_COMMENTED = 'ticket_commented';

    public const ALL_EVENTS = [
        self::EVENT_TICKET_CREATED,
        self::EVENT_TICKET_ASSIGNED,
        self::EVENT_TICKET_CLOSED,
        self::EVENT_SLA_BREACH,
        self::EVENT_TICKET_COMMENTED,
    ];

    public function notify(Tenant $tenant, string $event, array $data): void
    {
        $integrations = Integration::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn(Integration $i) => $i->hasEvent($event));

        foreach ($integrations as $integration) {
            SendWebhookNotificationJob::dispatch($integration, $event, $data);
        }
    }

    public static function formatSlackMessage(string $event, array $data): array
    {
        $blocks = [];

        switch ($event) {
            case self::EVENT_TICKET_CREATED:
                $blocks = [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => "\xF0\x9F\x8E\xAB Nuevo ticket #{$data['ticket_number']}: {$data['title']}",
                            'emoji' => true,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Prioridad:*\n{$data['priority']}"],
                            ['type' => 'mrkdwn', 'text' => "*Solicitante:*\n{$data['requester']}"],
                            ['type' => 'mrkdwn', 'text' => "*Tipo:*\n{$data['type']}"],
                            ['type' => 'mrkdwn', 'text' => "*Estado:*\n{$data['status']}"],
                        ],
                    ],
                ];
                if (!empty($data['link'])) {
                    $blocks[] = [
                        'type' => 'actions',
                        'elements' => [
                            [
                                'type' => 'button',
                                'text' => ['type' => 'plain_text', 'text' => 'Ver ticket', 'emoji' => true],
                                'url' => $data['link'],
                                'style' => 'primary',
                            ],
                        ],
                    ];
                }
                break;

            case self::EVENT_TICKET_ASSIGNED:
                $blocks = [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => "\xF0\x9F\x91\xA4 Ticket #{$data['ticket_number']} asignado a {$data['assignee']}",
                            'emoji' => true,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Asunto:*\n{$data['title']}"],
                            ['type' => 'mrkdwn', 'text' => "*Prioridad:*\n{$data['priority']}"],
                        ],
                    ],
                ];
                if (!empty($data['link'])) {
                    $blocks[] = [
                        'type' => 'actions',
                        'elements' => [
                            [
                                'type' => 'button',
                                'text' => ['type' => 'plain_text', 'text' => 'Ver ticket', 'emoji' => true],
                                'url' => $data['link'],
                            ],
                        ],
                    ];
                }
                break;

            case self::EVENT_TICKET_CLOSED:
                $resolutionTime = $data['resolution_time'] ?? 'N/A';
                $blocks = [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => "\xE2\x9C\x85 Ticket #{$data['ticket_number']} resuelto: {$data['title']}",
                            'emoji' => true,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Tiempo de resolucion:*\n{$resolutionTime}"],
                        ],
                    ],
                ];
                break;

            case self::EVENT_SLA_BREACH:
                $timeRemaining = $data['time_remaining'] ?? 'N/A';
                $blocks = [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => "\xE2\x9A\xA0\xEF\xB8\x8F SLA en riesgo: Ticket #{$data['ticket_number']}",
                            'emoji' => true,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Asunto:*\n{$data['title']}"],
                            ['type' => 'mrkdwn', 'text' => "*Tiempo restante:*\n{$timeRemaining}"],
                            ['type' => 'mrkdwn', 'text' => "*Tipo de incumplimiento:*\n{$data['breach_type']}"],
                        ],
                    ],
                ];
                if (!empty($data['link'])) {
                    $blocks[] = [
                        'type' => 'actions',
                        'elements' => [
                            [
                                'type' => 'button',
                                'text' => ['type' => 'plain_text', 'text' => 'Ver ticket', 'emoji' => true],
                                'url' => $data['link'],
                                'style' => 'danger',
                            ],
                        ],
                    ];
                }
                break;

            case self::EVENT_TICKET_COMMENTED:
                $preview = mb_substr(strip_tags($data['comment_body'] ?? ''), 0, 150);
                $blocks = [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => "\xF0\x9F\x92\xAC Nuevo comentario en #{$data['ticket_number']}",
                            'emoji' => true,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Autor:*\n{$data['commenter']}"],
                            ['type' => 'mrkdwn', 'text' => "*Asunto:*\n{$data['title']}"],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "> {$preview}" . (mb_strlen(strip_tags($data['comment_body'] ?? '')) > 150 ? '...' : ''),
                        ],
                    ],
                ];
                if (!empty($data['link'])) {
                    $blocks[] = [
                        'type' => 'actions',
                        'elements' => [
                            [
                                'type' => 'button',
                                'text' => ['type' => 'plain_text', 'text' => 'Ver ticket', 'emoji' => true],
                                'url' => $data['link'],
                            ],
                        ],
                    ];
                }
                break;
        }

        return ['blocks' => $blocks];
    }

    public static function formatTeamsMessage(string $event, array $data): array
    {
        $facts = [];
        $title = '';
        $color = 'default';

        switch ($event) {
            case self::EVENT_TICKET_CREATED:
                $title = "\xF0\x9F\x8E\xAB Nuevo ticket #{$data['ticket_number']}: {$data['title']}";
                $color = 'accent';
                $facts = [
                    ['title' => 'Prioridad', 'value' => $data['priority']],
                    ['title' => 'Solicitante', 'value' => $data['requester']],
                    ['title' => 'Tipo', 'value' => $data['type']],
                    ['title' => 'Estado', 'value' => $data['status']],
                ];
                break;

            case self::EVENT_TICKET_ASSIGNED:
                $title = "\xF0\x9F\x91\xA4 Ticket #{$data['ticket_number']} asignado a {$data['assignee']}";
                $color = 'good';
                $facts = [
                    ['title' => 'Asunto', 'value' => $data['title']],
                    ['title' => 'Prioridad', 'value' => $data['priority']],
                ];
                break;

            case self::EVENT_TICKET_CLOSED:
                $title = "\xE2\x9C\x85 Ticket #{$data['ticket_number']} resuelto: {$data['title']}";
                $color = 'good';
                $facts = [
                    ['title' => 'Tiempo de resolucion', 'value' => $data['resolution_time'] ?? 'N/A'],
                ];
                break;

            case self::EVENT_SLA_BREACH:
                $title = "\xE2\x9A\xA0\xEF\xB8\x8F SLA en riesgo: Ticket #{$data['ticket_number']}";
                $color = 'attention';
                $facts = [
                    ['title' => 'Asunto', 'value' => $data['title']],
                    ['title' => 'Tiempo restante', 'value' => $data['time_remaining'] ?? 'N/A'],
                    ['title' => 'Tipo', 'value' => $data['breach_type'] ?? 'N/A'],
                ];
                break;

            case self::EVENT_TICKET_COMMENTED:
                $preview = mb_substr(strip_tags($data['comment_body'] ?? ''), 0, 150);
                $title = "\xF0\x9F\x92\xAC Nuevo comentario en #{$data['ticket_number']}";
                $facts = [
                    ['title' => 'Autor', 'value' => $data['commenter']],
                    ['title' => 'Asunto', 'value' => $data['title']],
                    ['title' => 'Comentario', 'value' => $preview],
                ];
                break;
        }

        $body = [
            [
                'type' => 'TextBlock',
                'size' => 'Medium',
                'weight' => 'Bolder',
                'text' => $title,
                'wrap' => true,
            ],
            [
                'type' => 'FactSet',
                'facts' => $facts,
            ],
        ];

        $actions = [];
        if (!empty($data['link'])) {
            $actions[] = [
                'type' => 'Action.OpenUrl',
                'title' => 'Ver ticket',
                'url' => $data['link'],
            ];
        }

        $card = [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => $body,
                        'actions' => $actions,
                    ],
                ],
            ],
        ];

        return $card;
    }

    public static function formatGenericWebhookMessage(string $event, array $data): array
    {
        return [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];
    }

    public static function buildTestPayload(string $provider): array
    {
        $data = [
            'ticket_number' => 'TEST-001',
            'title' => 'Mensaje de prueba - AutoService ITSM',
            'priority' => 'medium',
            'requester' => 'Sistema de Prueba',
            'type' => 'incident',
            'status' => 'open',
            'link' => '',
        ];

        return match ($provider) {
            'slack' => self::formatSlackMessage(self::EVENT_TICKET_CREATED, $data),
            'teams' => self::formatTeamsMessage(self::EVENT_TICKET_CREATED, $data),
            default => self::formatGenericWebhookMessage(self::EVENT_TICKET_CREATED, $data),
        };
    }
}
