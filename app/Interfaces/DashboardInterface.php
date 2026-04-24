<?php

namespace App\Interfaces;

interface DashboardInterface
{
    /**
     * Retrieve dashboard statistics.
     *
     * @return array<string, int|string>
     */
    public function getDashboardStats(): array;

    /**
     * Retrieve recent activities.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivities(): array;

    /**
     * Retrieve complete dashboard overview.
     *
     * @return array<string, array>
     */
    public function getDashboardOverview(): array;

    /**
     * Clear dashboard-related cache.
     */
    public function clearCache(): void;
}