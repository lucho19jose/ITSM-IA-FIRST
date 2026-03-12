<?php

namespace App\Services\Ai;

use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Ticket;

class TicketClassifier
{
    public function __construct(private ClaudeClient $client) {}

    public function classify(Ticket $ticket): array
    {
        $categories = Category::where('tenant_id', $ticket->tenant_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'description'])
            ->toArray();

        if (empty($categories)) {
            return ['success' => false, 'reason' => 'No hay categorias disponibles'];
        }

        $categoriesJson = json_encode($categories, JSON_UNESCAPED_UNICODE);

        $systemPrompt = <<<PROMPT
Eres un clasificador de tickets ITSM para una empresa. Tu trabajo es analizar un ticket y clasificarlo.

Categorias disponibles:
{$categoriesJson}

Debes responder SOLO con un JSON valido con este formato exacto:
{
  "category_id": <id de la categoria>,
  "category_name": "<nombre de la categoria>",
  "priority": "<low|medium|high|urgent>",
  "confidence": <numero entre 0 y 1>,
  "reasoning": "<breve explicacion>"
}

Criterios de prioridad:
- urgent: Sistema caido, afecta a toda la empresa, perdida de datos
- high: Afecta trabajo de un equipo, sin workaround disponible
- medium: Afecta a un usuario, tiene workaround
- low: Consultas, mejoras, no urgente
PROMPT;

        $userMessage = "Titulo: {$ticket->title}\nDescripcion: {$ticket->description}\nTipo: {$ticket->type}";

        $response = $this->client->sendMessage($systemPrompt, $userMessage);

        // Parse JSON from response
        $content = $response['content'];
        $jsonMatch = [];
        preg_match('/\{[\s\S]*\}/', $content, $jsonMatch);
        $result = json_decode($jsonMatch[0] ?? '{}', true);

        // Log AI suggestion
        $suggestion = AiSuggestion::withoutGlobalScopes()->create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'type' => 'classification',
            'input' => $userMessage,
            'output' => $content,
            'model' => $response['model'],
            'confidence' => $result['confidence'] ?? null,
            'tokens_used' => $response['tokens_used'],
            'processing_time_ms' => $response['processing_time_ms'],
        ]);

        return [
            'success' => true,
            'suggestion_id' => $suggestion->id,
            'category_id' => $result['category_id'] ?? null,
            'category_name' => $result['category_name'] ?? null,
            'priority' => $result['priority'] ?? null,
            'confidence' => $result['confidence'] ?? 0,
            'reasoning' => $result['reasoning'] ?? '',
        ];
    }
}
