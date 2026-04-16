<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Display all employees.
     */
    public function index(): JsonResponse
    {
        try {
            $employees = Employee::orderByDesc('created_at')->paginate(15);

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
                'position' => 'required|string|max:255',
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
                'position' => 'sometimes|required|string|max:255',
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
}
