<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportParamsRequest;
use Illuminate\Support\Facades\Auth;
use App\Enums\RoleEnum;

class ReportController extends Controller
{
    public function requestReport(ReportParamsRequest $request)
    {
        if (Auth::user()->role->value !== RoleEnum::KEPALA_PANTI->value) {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'Forbidden Access.'
            ], 403);
        }

        // Call ReportService. Returns generic struct for now.
        return response()->json([
            'status' => 'success',
            'data' => [
                'report' => 'Simulated Generated Data for Kepala Panti'
            ]
        ]);
    }
}
