<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    /**
     * Run the artisan optimize command securely.
     */
    public function optimize(Request $request)
    {
        // For security, require a token in the URL.
        // E.g., /system/optimize?token=your-secret-token-123
        // You should define OPTIMIZE_TOKEN in your .env file.
        $validToken = env('OPTIMIZE_TOKEN', 'your-secret-token-123');

        if ($request->query('token') !== $validToken) {
            abort(403, 'Unauthorized access. Invalid or missing token.');
        }

        try {
            Artisan::call('optimize');
            
            return response()->json([
                'status' => 'success',
                'message' => 'Optimization complete!',
                'output' => Artisan::output()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
