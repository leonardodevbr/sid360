<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('users.view');

        $authUser = auth()->user();
        $query = User::query()->with('roles');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('matricula', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->string('role')->toString();
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        if ($authUser) {
            $query->where('id', '!=', $authUser->id);
        }

        $users = $query->orderBy('name')->paginate($perPage);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('users.create');

        $data = $request->safe()->only(['name', 'email', 'username', 'matricula']);
        $roles = $request->validated('roles');

        $user = DB::transaction(function () use ($data, $request, $roles): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => ! empty($data['username']) ? $data['username'] : null,
                'matricula' => ! empty($data['matricula']) ? $data['matricula'] : null,
                'password' => $request->validated('password'),
            ]);

            $user->syncRoles($roles);

            return $user->load('roles');
        });

        return response()->json(new UserResource($user), 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('users.view');

        $user = User::query()->with('roles')->findOrFail((int) $id);

        return response()->json(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $this->authorize('users.edit');

        $user = User::query()->with('roles')->findOrFail((int) $id);

        $data = $request->safe()->only(['name', 'email', 'username', 'matricula']);

        DB::transaction(function () use ($user, $data, $request): void {
            $payload = [
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
                'username' => array_key_exists('username', $data)
                    ? ($data['username'] !== null && $data['username'] !== '' ? $data['username'] : null)
                    : $user->username,
                'matricula' => array_key_exists('matricula', $data)
                    ? ($data['matricula'] !== null && $data['matricula'] !== '' ? $data['matricula'] : null)
                    : $user->matricula,
            ];

            if ($request->filled('password')) {
                $payload['password'] = $request->validated('password');
            }

            if ($request->has('roles')) {
                $user->syncRoles($request->validated('roles'));
            }

            $user->update($payload);
        });

        return response()->json(new UserResource($user->fresh()->load('roles')));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('users.delete');

        $authUser = auth()->user();
        $user = User::query()->findOrFail((int) $id);

        if ($authUser && $user->id === $authUser->id) {
            throw ValidationException::withMessages([
                'user' => ['Não é permitido excluir o próprio usuário.'],
            ]);
        }

        $user->delete();

        return response()->json(['message' => 'Usuário excluído com sucesso.']);
    }
}
