<?php

namespace App\Services\Ai;

use App\Models\AiSuggestion;
use App\Models\KnowledgeBaseArticle;
use App\Models\Ticket;

class ResponseSuggester
{
    public function __construct(private ClaudeClient $client) {}

    public function suggest(Ticket $ticket): array
    {
        $ticket->load(['comments.user', 'category']);

        // Search for relevant KB articles
        $searchTerms = $ticket->title . ' ' . ($ticket->category?->name ?? '');
        $kbArticles = KnowledgeBaseArticle::withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$searchTerms])
            ->limit(3)
            ->get(['id', 'title', 'excerpt', 'slug']);

        $kbContext = $kbArticles->isEmpty()
            ? 'No se encontraron articulos relevantes en la base de conocimiento.'
            : $kbArticles->map(fn($a) => "- {$a->title}: {$a->excerpt}")->join("\n");

        $commentsContext = $ticket->comments->map(function ($c) {
            $type = $c->is_internal ? '[NOTA INTERNA]' : '[PUBLICO]';
            return "{$type} {$c->user->name}: {$c->body}";
        })->join("\n");

        $systemPrompt = <<<PROMPT
Eres un asistente de soporte tecnico para una empresa peruana. Genera una respuesta profesional y empatica para un ticket de soporte.

La respuesta debe:
1. Ser en espanol
2. Ser profesional pero cercana
3. Incluir pasos concretos para resolver el problema
4. Referenciar articulos de KB si son relevantes

Responde SOLO con un JSON:
{
  "suggested_response": "<respuesta sugerida para el usuario>",
  "internal_note": "<nota interna para el agente, si aplica>",
  "relevant_kb_articles": [<ids de articulos KB relevantes>],
  "confidence": <numero entre 0 y 1>
}
PROMPT;

        $userMessage = <<<MSG
Ticket: {$ticket->ticket_number}
Titulo: {$ticket->title}
Descripcion: {$ticket->description}
Categoria: {$ticket->category?->name}
Prioridad: {$ticket->priority}

Historial de comentarios:
{$commentsContext}

Articulos KB relacionados:
{$kbContext}
MSG;

        $response = $this->client->sendMessage($systemPrompt, $userMessage);

        $content = $response['content'];
        $jsonMatch = [];
        preg_match('/\{[\s\S]*\}/', $content, $jsonMatch);
        $result = json_decode($jsonMatch[0] ?? '{}', true);

        AiSuggestion::withoutGlobalScopes()->create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'type' => 'response',
            'input' => $userMessage,
            'output' => $content,
            'model' => $response['model'],
            'confidence' => $result['confidence'] ?? null,
            'tokens_used' => $response['tokens_used'],
            'processing_time_ms' => $response['processing_time_ms'],
        ]);

        return [
            'suggested_response' => $result['suggested_response'] ?? '',
            'internal_note' => $result['internal_note'] ?? '',
            'relevant_kb_articles' => $kbArticles->toArray(),
            'confidence' => $result['confidence'] ?? 0,
        ];
    }
}
