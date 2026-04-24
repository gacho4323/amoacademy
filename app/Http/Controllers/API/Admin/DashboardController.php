<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\DashboardException;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    private DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->dashboardService->getDashboardStats();
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (DashboardException $e) {
            Log::error('Dashboard stats error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode());
        } catch (\Exception $e) {
            Log::error('Unexpected error fetching dashboard stats: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error occurred while fetching dashboard statistics'
            ], 500);
        }
    }

    public function getRecentActivities(): JsonResponse
    {
        try {
            $activities = $this->dashboardService->getRecentActivities();
            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (DashboardException $e) {
            Log::error('Recent activities error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode());
        } catch (\Exception $e) {
            Log::error('Unexpected error fetching recent activities: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error occurred while fetching recent activities'
            ], 500);
        }
    }

    public function getOverview(): JsonResponse
    {
        try {
            $overview = $this->dashboardService->getDashboardOverview();
            return response()->json([
                'success' => true,
                'data' => $overview
            ]);
        } catch (DashboardException $e) {
            Log::error('Dashboard overview error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode());
        } catch (\Exception $e) {
            Log::error('Unexpected error fetching dashboard overview: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error occurred while fetching dashboard overview'
            ], 500);
        }
    }
}