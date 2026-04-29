<?php

namespace App\Http\Controllers;

use App\Models\Capacity;
use App\Services\CapacityGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapacityController extends Controller
{
    private CapacityGeneratorService $capacityGeneratorService;

    public function __construct(CapacityGeneratorService $capacityGeneratorService)
    {
        $this->capacityGeneratorService = $capacityGeneratorService;
    }

    /**
     * Fetch available capacities.
     * Hooks JIT Generation to ensure a 30-day window is always populated.
     */
    public function index(Request $request): JsonResponse
    {
        // JIT Generation Hook - Guarantees the 30-day window is populated
        $this->capacityGeneratorService->generateForWindow(30);

        // Fetch capacities from today onwards
        $capacities = Capacity::where('date', '>=', today())
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $capacities
        ]);
    }
}
