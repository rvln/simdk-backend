<?php

namespace App\Http\Controllers;

use App\Models\Inventory;

class InventoryController extends Controller
{
    /**
     * Public endpoint: returns all inventory items for the Smart Cart dropdown.
     */
    public function index()
    {
        $inventories = Inventory::select('id', 'itemName', 'category', 'stock', 'target_qty', 'unit', 'description')
            ->orderBy('category')
            ->orderBy('itemName')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $inventories,
        ]);
    }
}
