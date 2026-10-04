<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'role' => ['required', Rule::in(['donor', 'recipient'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'locale' => ['sometimes', Rule::in(['ar', 'fr', 'en'])],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:32'],
            'wilaya_id' => [Rule::requiredIf($request->input('role') === 'donor'), 'nullable', 'integer', 'exists:wilayas,id'],
            'commune_id' => [
                'nullable',
                'integer',
                Rule::exists('communes', 'id')->where('wilaya_id', $request->input('wilaya_id')),
            ],
            'blood_type_id' => ['nullable', 'integer', 'exists:blood_types,id'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'consent_to_contact' => ['sometimes', 'boolean'],
        ]);

        $user = DB::transaction(function () use ($request, $data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'role' => $data['role'],
                'locale' => $data['locale'] ?? 'fr',
            ]);

            if ($data['role'] === 'donor') {
                $user->donor()->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'],
                    'wilaya_id' => $data['wilaya_id'],
                    'commune_id' => $data['commune_id'] ?? null,
                    'blood_type_id' => $data['blood_type_id'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'consent_to_contact' => $data['consent_to_contact'] ?? false,
                ]);
            } else {
                $user->recipient()->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'],
                    'wilaya_id' => $data['wilaya_id'] ?? null,
                    'commune_id' => $data['commune_id'] ?? null,
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'account.registered',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => ['role' => $user->role],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            return $user;
        });

        return response()->json([
            'user' => $user->load(['donor', 'recipient']),
            'token' => $user->createToken('web')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:72'],
        ]);

        $user = User::where('email', strtolower($credentials['email']))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            AuditLog::create([
                'action' => 'account.login_failed',
                'metadata' => ['reason' => 'invalid_credentials'],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            return response()->json(['message' => 'The supplied credentials are invalid.'], 401);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'account.login_succeeded',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return response()->json([
            'user' => $user->load(['donor', 'recipient']),
            'token' => $user->createToken('web')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load(['donor', 'recipient'])]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'account.logout',
            'subject_type' => User::class,
            'subject_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return response()->json(['message' => 'Signed out.']);
    }
}
