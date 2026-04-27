<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * GET /api/inventories
     * Public endpoint: returns all inventory items for the Smart Cart dropdown.
     * Delegates data retrieval to InventoryService — zero Eloquent here.
     */
    public function index()
    {
        try {
            $inventories = $this->inventoryService->getPublicInventoryList();

            return response()->json([
                'status' => 'success',
                'data'   => $inventories,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
