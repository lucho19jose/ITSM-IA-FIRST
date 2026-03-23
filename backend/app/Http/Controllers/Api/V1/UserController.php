<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(
        protected PlanService $planService,
    ) {}

    public function index(): JsonResponse
    {
        $users = User::orderBy('name')->get();
        return response()->json(['data' => UserResource::collection($users)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)],
            'role' => 'required|in:admin,agent,end_user',
        ]);

        // Check agent limit when creating admin or agent users
        if (in_array($validated['role'], ['admin', 'agent'])) {
            $tenant = $request->user()->tenant;
            if ($tenant && !$this->planService->canAddAgent($tenant)) {
                return response()->json([
                    'message' => 'Has alcanzado el límite de agentes de tu plan. Actualiza tu plan para agregar más agentes.',
                    'error' => 'agent_limit_reached',
                    'upgrade_url' => '/settings/billing',
                ], 403);
            }
        }

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('department');
        return response()->json(['data' => new UserResource($user)]);
    }

    public function recentTickets(User $user): JsonResponse
    {
        $tickets = \App\Models\Ticket::where('requester_id', $user->id)
            ->with(['assignee', 'category'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'assignee_name' => $t->assignee?->name,
                'created_at' => $t->created_at->toISOString(),
            ]);

        return response()->json(['data' => $tickets]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => ['sometimes', Password::min(8)],
            'role' => 'sometimes|in:admin,agent,end_user',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json(['data' => new UserResource($user)]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta'], 422);
        }

        $user->delete();
        return response()->json(['message' => 'Usuario eliminado']);
    }

    public function agents(): JsonResponse
    {
        $agents = User::whereIn('role', ['admin', 'agent'])->orderBy('name')->get();
        return response()->json(['data' => UserResource::collection($agents)]);
    }
}
