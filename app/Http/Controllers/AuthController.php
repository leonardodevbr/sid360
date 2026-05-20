<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Mail\FirstAccessMail;
use App\Support\Settings;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $this->getCredentials($request);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'login' => ['E-mail, usuário ou matrícula e/ou senha incorretos.'],
            ]);
        }

        $user = Auth::user();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['E-mail, usuário ou matrícula e/ou senha incorretos.'],
            ]);
        }

        $user->load(['roles']);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getCredentials(LoginRequest $request): array
    {
        $login = $request->input('login');
        $password = $request->input('password');

        $allowedMethods = Settings::get('allowed_login_methods', ['email', 'username', 'matricula']);
        if (! is_array($allowedMethods)) {
            $allowedMethods = ['email', 'username', 'matricula'];
        }
        $allowedMethods = array_map('strtolower', $allowedMethods);

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : null;

        if (! $field) {
            $userByUsername = User::where('username', $login)->first();
            $field = $userByUsername ? 'username' : 'matricula';
        }

        if (! in_array($field, $allowedMethods, true)) {
            $labels = [
                'email' => 'e-mail',
                'username' => 'usuário',
                'matricula' => 'matrícula',
            ];
            $permitidos = array_map(fn ($m) => $labels[$m] ?? $m, $allowedMethods);
            throw ValidationException::withMessages([
                'login' => ['Este tipo de login não está habilitado. Use: '.implode(', ', $permitidos).'.'],
            ]);
        }

        return [
            $field => $login,
            'password' => $password,
        ];
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->load(['roles']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.exists' => 'Não encontramos um usuário com este e-mail.',
        ]);

        $user = User::where('email', $request->input('email'))->first();
        if (! $user) {
            return response()->json(['message' => 'Não encontramos um usuário com este e-mail.'], 422);
        }

        $token = Password::broker()->createToken($user);
        $resetUrl = rtrim(config('app.url'), '/').'/reset-password?token='.urlencode($token).'&email='.urlencode($user->email);

        Mail::to($user->email)->send(new FirstAccessMail($user, $resetUrl, false));

        return response()->json([
            'message' => 'Enviamos um link para redefinir sua senha no e-mail informado. Verifique sua caixa de entrada.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ], [
            'email.exists' => 'E-mail não encontrado.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Link expirado ou inválido. Solicite uma nova redefinição de senha.',
            ], 422);
        }

        return response()->json([
            'message' => 'Senha redefinida com sucesso. Você já pode entrar com sua nova senha.',
        ]);
    }
}
