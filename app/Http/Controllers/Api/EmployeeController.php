<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmployeeCredentialsMail;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Display all employees.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'role' => ['nullable', Rule::in(['admin', 'sales', 'delivery', 'inventory', 'purchasing'])],
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => ['nullable', Rule::in(['name', 'email', 'role', 'phone', 'status'])],
                'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            ]);

            $perPage = $validated['per_page'] ?? 15;
            $search = trim((string) ($validated['search'] ?? ''));
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortDirection = $validated['sort_direction'] ?? 'asc';

            $query = Employee::with('user');

            if ($search !== '') {
                $query->where(function ($employeeQuery) use ($search) {
                    $employeeQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if (!empty($validated['role'])) {
                $query->where('role', $validated['role']);
            }

            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            $employees = $query->orderBy($sortBy, $sortDirection)->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Employees retrieved successfully',
                'data' => $employees->items(),
                'pagination' => [
                    'total' => $employees->total(),
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving employees',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:employees,email',
                'phone' => 'nullable|string|max:20',
                'role' => 'required|in:admin,sales,delivery,inventory,purchasing',
                'address' => 'nullable|string|max:500',
                'status' => 'nullable|in:active,inactive',
            ]);

            $validated['status'] = $validated['status'] ?? 'active';

            $employee = Employee::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific employee.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $employee,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:employees,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'role' => 'sometimes|required|in:admin,sales,delivery,inventory,purchasing',
                'address' => 'nullable|string|max:500',
                'status' => 'sometimes|required|in:active,inactive',
            ]);

            $employee->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => $employee,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a system account (User) for an employee.
     */
    public function createAccount(Request $request, string $id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);

            if ($employee->user) {
                return response()->json([
                    'success' => false,
                    'message' => 'This employee already has a system account.',
                ], 422);
            }

            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'employee_id'         => $employee->id,
                'name'                => $employee->name,
                'username'            => $validated['username'],
                'email'               => $employee->email,
                'password'            => Hash::make($validated['password']),
                'role'                => $employee->role,
                'account_status'      => 'active',
                'must_change_password' => true,
            ]);

            // Send credentials email — catch failures so account creation still succeeds
            $emailSent = false;
            try {
                Mail::to($employee->email)->send(new EmployeeCredentialsMail(
                    employeeName:      $employee->name,
                    username:          $validated['username'],
                    temporaryPassword: $validated['password'],
                    loginUrl:          config('app.url') . '/login',
                ));
                $emailSent = true;
            } catch (\Exception $mailException) {
                Log::error('Failed to send credentials email to ' . $employee->email . ': ' . $mailException->getMessage());
            }

            $message = $emailSent
                ? "Account created for {$employee->name}. Login credentials have been sent to {$employee->email}."
                : "Account created for {$employee->name}. Warning: credentials email could not be sent to {$employee->email} — please share credentials manually.";

            return response()->json([
                'success'    => true,
                'message'    => $message,
                'email_sent' => $emailSent,
                'data'       => [
                    'user_id'        => $user->id,
                    'username'       => $user->username,
                    'role'           => $user->role,
                    'account_status' => $user->account_status,
                ],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error creating account: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Revoke (delete) the system account for an employee.
     */
    public function revokeAccount(string $id): JsonResponse
    {
        try {
            $employee = Employee::with('user')->findOrFail($id);

            if (!$employee->user) {
                return response()->json(['success' => false, 'message' => 'This employee has no account to revoke.'], 422);
            }

            $employee->user->tokens()->delete();
            $employee->user->delete();

            return response()->json(['success' => true, 'message' => "Account for {$employee->name} has been revoked."]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error revoking account: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle account_status active/inactive.
     */
    public function toggleAccountStatus(string $id): JsonResponse
    {
        try {
            $employee = Employee::with('user')->findOrFail($id);

            if (!$employee->user) {
                return response()->json(['success' => false, 'message' => 'This employee has no system account.'], 422);
            }

            $newStatus = $employee->user->account_status === 'active' ? 'inactive' : 'active';
            $employee->user->update(['account_status' => $newStatus]);

            if ($newStatus === 'inactive') {
                $employee->user->tokens()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => "Account {$newStatus} for {$employee->name}.",
                'data'    => ['account_status' => $newStatus],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating account status: ' . $e->getMessage()], 500);
        }
    }
}
