<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ClassifyTicketJob;
use App\Models\Ticket;
use App\Services\Ai\ClaudeClient;
use App\Services\Ai\KbGenerator;
use App\Services\Ai\ResponseSuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function classify(Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        ClassifyTicketJob::dispatch($ticket)->onQueue('ai');

        return response()->json([
            'message' => 'Clasificacion en proceso',
        ]);
    }

    public function suggestResponse(Ticket $ticket, ResponseSuggester $suggester): JsonResponse
    {
        $this->authorize('view', $ticket);

        try {
            $result = $suggester->suggest($ticket);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['data' => $result]);
    }

    public function generateKbArticle(Ticket $ticket, KbGenerator $generator): JsonResponse
    {
        $this->authorize('update', $ticket);

        try {
            $result = $generator->generateFromTicket($ticket);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['data' => $result]);
    }

    public function improveText(Request $request, ClaudeClient $client): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:5000',
        ]);

        $systemPrompt = <<<'PROMPT'
Eres un corrector profesional de textos de soporte tecnico en español. Tu tarea es mejorar el texto del agente:

1. Corregir errores ortograficos y gramaticales
2. Mejorar la redaccion para que sea profesional y clara
3. Mantener el mismo significado y tono amable
4. No agregar informacion nueva, solo mejorar lo existente
5. Mantener el formato de saludo/despedida si existe

Responde SOLO con el texto mejorado, sin explicaciones ni comentarios adicionales. No uses markdown.
PROMPT;

        try {
            $result = $client->sendFast($systemPrompt, $request->input('text'), 1024);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'data' => [
                'improved_text' => $result['content'],
                'model' => $result['model'],
                'processing_time_ms' => $result['processing_time_ms'],
            ],
        ]);
    }
}
