<?php

namespace App\Services;

use App\Interfaces\DashboardInterface;

class DashboardService
{
    private DashboardInterface $dashboardRepository;

    public function __construct(DashboardInterface $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    /**
     * Get dashboard statistics.
     *
     * @return array<string, int|string>
     */
    public function getDashboardStats(): array
    {
        return $this->dashboardRepository->getDashboardStats();
    }

    /**
     * Get recent activities.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivities(): array
    {
        return $this->dashboardRepository->getRecentActivities();
    }

    /**
     * Get complete dashboard overview.
     *
     * @return array<string, array>
     */
    public function getDashboardOverview(): array
    {
        return $this->dashboardRepository->getDashboardOverview();
    }

    /**
     * Clear dashboard cache.
     */
    public function clearCache(): void
    {
        $this->dashboardRepository->clearCache();
    }
}