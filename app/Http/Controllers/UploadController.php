<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Upload de logo/brasão para department ou municipality.
     * Grava em storage/app/public (disco padrão public).
     * POST /upload/logo com: file (imagem), type (department|municipality), id (opcional para criar)
     */
    public function logo(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['super-admin', 'admin'])) {
            abort(403, 'Sem permissão para enviar logo.');
        }

        $request->validate([
            'file' => ['required', 'file', 'image', 'max:2048'],
            'type' => ['required', 'string', 'in:department,municipality'],
            'id' => ['required', 'string'],
        ]);

        $file = $request->file('file');
        $type = $request->input('type');
        $id = $request->input('id');

        $folder = "logos/{$type}/{$id}";
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs($folder, $name, 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Upload de assinatura do usuário.
     * Grava em storage/app/public/signatures e atualiza user.signature_path.
     * POST /upload/signature com: file (imagem), id (user id).
     */
    public function signature(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if (! $authUser) {
            abort(403, 'Não autenticado.');
        }

        $request->validate([
            'file' => ['required', 'file', 'image', 'max:2048'],
            'id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $userId = (int) $request->input('id');
        $user = User::query()->findOrFail($userId);

        // Pode editar a própria assinatura (perfil) ou ter permissão users.edit
        if ($userId !== $authUser->id && ! $authUser->can('users.edit')) {
            abort(403, 'Sem permissão para enviar assinatura.');
        }

        $file = $request->file('file');
        $disk = Storage::disk('public');

        if ($user->signature_path && $disk->exists($user->signature_path)) {
            $disk->delete($user->signature_path);
        }

        $path = $file->store('signatures', 'public');
        if (! $path) {
            return response()->json(['message' => 'Falha ao salvar o arquivo.'], 422);
        }

        $user->update(['signature_path' => $path]);

        return response()->json([
            'path' => $path,
            'url' => $disk->url($path),
        ]);
    }
}
