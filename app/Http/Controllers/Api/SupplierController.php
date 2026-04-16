<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Display all suppliers
     */
    public function index(): JsonResponse
    {
        try {
            $suppliers = Supplier::with('products', 'purchaseOrders')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'message' => 'Suppliers retrieved successfully',
                'data' => $suppliers->items(),
                'pagination' => [
                    'total' => $suppliers->total(),
                    'current_page' => $suppliers->currentPage(),
                    'last_page' => $suppliers->lastPage(),
                    'per_page' => $suppliers->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new supplier
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:suppliers,name',
                'contact_person' => 'required|string|max:255',
                'email' => 'required|email|unique:suppliers,email',
                'phone' => 'required|string|max:20',
                'address' => 'required|string',
                'notes' => 'nullable|string',
            ]);
            
            $supplier = Supplier::create($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display specific supplier
     */
    public function show(string $id): JsonResponse
    {
        try {
            $supplier = Supplier::with('products', 'purchaseOrders.purchaseOrderItems')
                ->findOrFail($id);
            
            // Add supplier metrics
            $supplier->metrics = [
                'total_products' => $supplier->products()->count(),
                'pending_orders' => $supplier->purchaseOrders()->where('status', 'pending')->count(),
                'total_value' => $supplier->purchaseOrders()->sum('total_amount'),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $supplier
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a supplier
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:suppliers,name,' . $id,
                'contact_person' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:suppliers,email,' . $id,
                'phone' => 'sometimes|required|string|max:20',
                'address' => 'sometimes|required|string',
                'notes' => 'nullable|string',
            ]);
            
            $supplier = Supplier::findOrFail($id);
            $supplier->update($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Supplier updated successfully',
                'data' => $supplier
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a supplier
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $supplier = Supplier::findOrFail($id);
            
            // Prevent deletion if supplier has active orders
            if ($supplier->purchaseOrders()->where('status', '!=', 'delivered')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete supplier with active orders'
                ], 422);
            }
            
            $supplier->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Supplier deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
