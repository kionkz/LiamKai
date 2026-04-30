<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => 'required|string',
        ]);

        $identifier = trim((string) $request->username);

        $query = User::query();
        $hasUsernameColumn = Schema::hasColumn('users', 'username');
        $hasEmailColumn = Schema::hasColumn('users', 'email');

        if ($hasUsernameColumn && $hasEmailColumn) {
            $query->where(function ($q) use ($identifier) {
                $q->where('username', $identifier)
                    ->orWhere('email', $identifier);
            });
        } elseif ($hasUsernameColumn) {
            $query->where('username', $identifier);
        } elseif ($hasEmailColumn) {
            $query->where('email', $identifier);
        }

        $user = $query->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->recordLoginAudit($request, $identifier, $user, 'failed', 'Invalid credentials');

            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if ($user->account_status === 'inactive') {
            $this->recordLoginAudit($request, $identifier, $user, 'blocked', 'Inactive account');

            return response()->json([
                'message' => 'Your account has been deactivated. Please contact an administrator.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $this->recordLoginAudit($request, $identifier, $user, 'success');

        return response()->json([
            'token' => $token,
            'user' => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'username'            => $user->username,
                'email'               => $user->email,
                'role'                => $user->role,
                'account_status'      => $user->account_status,
                'employee_id'         => $user->employee_id,
                'must_change_password' => (bool) $user->must_change_password,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $this->recordLoginAudit($request, $user->username ?? $user->email, $user, 'logout');
        $user->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'username'       => $user->username,
                'email'          => $user->email,
                'role'           => $user->role,
                'account_status' => $user->account_status,
                'employee_id'    => $user->employee_id,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required_with:password', 'nullable', 'string'],
        ]);

        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            if (!Hash::check($validated['current_password'] ?? '', $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                ], 422);
            }

            $user->password = Hash::make($validated['password']);
            $user->must_change_password = false;
            // Invalidate old tokens after password change.
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;
        } else {
            $token = null;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'username'            => $user->username,
                'email'               => $user->email,
                'role'                => $user->role,
                'account_status'      => $user->account_status,
                'employee_id'         => $user->employee_id,
                'must_change_password' => (bool) $user->must_change_password,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Force-change password (used on first login when must_change_password is true).
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password             = Hash::make($validated['password']);
        $user->must_change_password = false;
        $user->save();

        // Issue a fresh token
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
            'token'   => $token,
            'user'    => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'username'            => $user->username,
                'email'               => $user->email,
                'role'                => $user->role,
                'account_status'      => $user->account_status,
                'employee_id'         => $user->employee_id,
                'must_change_password' => false,
            ],
        ]);
    }

    private function recordLoginAudit(Request $request, ?string $identifier, ?User $user, string $event, ?string $reason = null): void
    {
        LoginAuditLog::create([
            'user_id' => $user?->id,
            'identifier' => $identifier,
            'event' => $event,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
