<?php

namespace App\Services\Ai;

use App\Models\AiSuggestion;
use App\Models\KnowledgeBaseArticle;
use App\Models\Tenant;

class ChatbotService
{
    public function __construct(private ClaudeClient $client) {}

    public function chat(Tenant $tenant, string $message, array $history = []): array
    {
        // Search KB for relevant articles
        $kbArticles = KnowledgeBaseArticle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$message])
            ->limit(3)
            ->get(['id', 'title', 'content', 'excerpt', 'slug']);

        $kbContext = $kbArticles->isEmpty()
            ? 'No se encontraron articulos relevantes.'
            : $kbArticles->map(fn($a) => "Articulo: {$a->title}\n{$a->excerpt}")->join("\n\n");

        $historyContext = collect($history)->map(function ($msg) {
            $role = $msg['role'] === 'user' ? 'Usuario' : 'Asistente';
            return "{$role}: {$msg['content']}";
        })->join("\n");

        $systemPrompt = <<<PROMPT
Eres el asistente virtual de soporte de {$tenant->name}. Tu objetivo es ayudar a los usuarios con sus problemas tecnicos.

Reglas:
1. Responde siempre en espanol
2. Se amable, profesional y conciso
3. Si encuentras articulos relevantes en la base de conocimiento, usa esa informacion para responder
4. Si no puedes resolver el problema, sugiere crear un ticket de soporte
5. No inventes informacion tecnica

Responde con un JSON:
{
  "response": "<tu respuesta al usuario>",
  "should_create_ticket": <true si el problema necesita escalacion>,
  "suggested_ticket": {
    "title": "<titulo sugerido para el ticket>",
    "description": "<descripcion>",
    "priority": "<low|medium|high|urgent>"
  },
  "kb_articles_used": [<ids de articulos usados>]
}
PROMPT;

        $userMessage = "Historial:\n{$historyContext}\n\nMensaje actual: {$message}\n\nArticulos KB disponibles:\n{$kbContext}";

        $response = $this->client->sendMessage($systemPrompt, $userMessage);

        $content = $response['content'];
        $jsonMatch = [];
        preg_match('/\{[\s\S]*\}/', $content, $jsonMatch);
        $result = json_decode($jsonMatch[0] ?? '{}', true);

        AiSuggestion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'type' => 'chatbot',
            'input' => $message,
            'output' => $content,
            'model' => $response['model'],
            'tokens_used' => $response['tokens_used'],
            'processing_time_ms' => $response['processing_time_ms'],
        ]);

        return [
            'response' => $result['response'] ?? 'Lo siento, hubo un error. Por favor intenta nuevamente.',
            'should_create_ticket' => $result['should_create_ticket'] ?? false,
            'suggested_ticket' => $result['suggested_ticket'] ?? null,
            'kb_articles' => $kbArticles->map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
            ])->toArray(),
        ];
    }
}
