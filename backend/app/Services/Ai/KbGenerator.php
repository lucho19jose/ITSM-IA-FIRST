<?php

namespace App\Services\Ai;

use App\Models\AiSuggestion;
use App\Models\Ticket;

class KbGenerator
{
    public function __construct(private ClaudeClient $client) {}

    public function generateFromTicket(Ticket $ticket): array
    {
        $ticket->load(['comments.user', 'category']);

        $commentsText = $ticket->comments
            ->where('is_internal', false)
            ->map(fn($c) => "{$c->user->name}: {$c->body}")
            ->join("\n");

        $systemPrompt = <<<PROMPT
Eres un redactor de documentacion tecnica. A partir de un ticket de soporte resuelto, genera un articulo para la base de conocimiento.

El articulo debe:
1. Estar en espanol
2. Ser claro y paso a paso
3. Usar HTML para formato (h2, h3, p, ol, ul, li, strong, code)
4. Incluir: problema, causa, solucion paso a paso
5. Ser util para usuarios finales (no tecnicos)

Responde con un JSON:
{
  "title": "<titulo del articulo>",
  "content": "<contenido HTML del articulo>",
  "excerpt": "<resumen de 1-2 oraciones>"
}
PROMPT;

        $userMessage = <<<MSG
Ticket: {$ticket->ticket_number}
Titulo: {$ticket->title}
Descripcion: {$ticket->description}
Categoria: {$ticket->category?->name}
Resolucion:
{$commentsText}
MSG;

        $response = $this->client->sendMessage($systemPrompt, $userMessage, 2048);

        $content = $response['content'];
        $jsonMatch = [];
        preg_match('/\{[\s\S]*\}/', $content, $jsonMatch);
        $result = json_decode($jsonMatch[0] ?? '{}', true);

        AiSuggestion::withoutGlobalScopes()->create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'type' => 'kb_generation',
            'input' => $userMessage,
            'output' => $content,
            'model' => $response['model'],
            'tokens_used' => $response['tokens_used'],
            'processing_time_ms' => $response['processing_time_ms'],
        ]);

        return [
            'title' => $result['title'] ?? '',
            'content' => $result['content'] ?? '',
            'excerpt' => $result['excerpt'] ?? '',
        ];
    }
}
