<?php

namespace App\Repositories;

use App\Exceptions\DashboardException;
use App\Interfaces\DashboardInterface;
use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use App\Support\ActivityBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardInterface
{
    private ActivityBuilder $activityBuilder;

    public function __construct(ActivityBuilder $activityBuilder)
    {
        $this->activityBuilder = $activityBuilder;
    }

    public function getDashboardStats(): array
    {
        $cacheKey = config('dashboard.cache_keys.stats');

        return Cache::remember($cacheKey, now()->addMinutes(config('dashboard.cache_ttl.stats')), function (): array {
            return [
                'total_courses' => $this->getTotalCourses(),
                'active_users' => $this->getActiveUsers(),
                'total_orders' => $this->getTotalOrders(),
                'monthly_revenue' => $this->getMonthlyRevenue(),
            ];
        });
    }

    public function getRecentActivities(): array
    {
        $cacheKey = config('dashboard.cache_keys.activities');

        return Cache::remember($cacheKey, now()->addMinutes(config('dashboard.cache_ttl.activities')), function (): array {
            $activities = [];

            $activities = array_merge(
                $activities,
                $this->activityBuilder->buildCourseActivities(
                    Course::with('author')
                        ->orderBy('created_at', 'desc')
                        ->take(config('dashboard.limits.recent_courses'))
                        ->get()
                ),
                $this->activityBuilder->buildUserActivities(
                    User::where('created_at', '>=', now()->subDays(7))
                        ->orderBy('created_at', 'desc')
                        ->take(config('dashboard.limits.recent_users'))
                        ->get()
                )
            );

            if (DB::getSchemaBuilder()->hasTable('payments')) {
                $activities = array_merge(
                    $activities,
                    $this->activityBuilder->buildPurchaseActivities(
                        Payment::with(['user', 'course'])
                            ->orderBy('created_at', 'desc')
                            ->take(config('dashboard.limits.recent_purchases'))
                            ->get()
                    )
                );
            }

            return $this->activityBuilder->sortAndLimitActivities($activities, config('dashboard.limits.total_activities'));
        });
    }

    public function getDashboardOverview(): array
    {
        return [
            'stats' => $this->getDashboardStats(),
            'recent_activities' => $this->getRecentActivities(),
        ];
    }

    public function clearCache(): void
    {
        Cache::forget(config('dashboard.cache_keys.stats'));
        Cache::forget(config('dashboard.cache_keys.activities'));
    }

    private function getTotalCourses(): int
    {
        try {
            return Course::count();
        } catch (\Exception $e) {
            throw new DashboardException('Failed to count total courses: ' . $e->getMessage(), 500);
        }
    }

    private function getActiveUsers(): int
    {
        try {
            if (DB::getSchemaBuilder()->hasColumn('users', 'last_login_at')) {
                return User::where('last_login_at', '>=', now()->subDays(30))->count();
            }
            return User::whereHas('courses')->count();
        } catch (\Exception $e) {
            throw new DashboardException('Failed to count active users: ' . $e->getMessage(), 500);
        }
    }

    private function getTotalOrders(): int
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('payments')) {
                return Payment::count();
            }
            return DB::table('course_user')->count();
        } catch (\Exception $e) {
            throw new DashboardException('Failed to count total orders: ' . $e->getMessage(), 500);
        }
    }

    private function getMonthlyRevenue(): string
    {
        try {
            $currentMonth = now()->startOfMonth();
            if (DB::getSchemaBuilder()->hasTable('payments') &&
                DB::getSchemaBuilder()->hasColumn('payments', 'amount')) {
                $revenue = Payment::where('created_at', '>=', $currentMonth)
                    ->where('status', 'completed')
                    ->sum('amount');
            } else {
                $revenue = DB::table('course_user')
                    ->join('courses', 'course_user.course_id', '=', 'courses.id')
                    ->where('course_user.created_at', '>=', $currentMonth)
                    ->sum('courses.price');
            }
            return number_format($revenue, 2, ',', '.') . ' RSD';
        } catch (\Exception $e) {
            throw new DashboardException('Failed to calculate monthly revenue: ' . $e->getMessage(), 500);
        }
    }
}