<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ActivityBuilder
{
    /**
     * Build activities for recent courses.
     *
     * @param Collection<Course> $courses
     * @return array<int, array<string, mixed>>
     */
    public function buildCourseActivities(Collection $courses): array
    {
        $activities = [];

        foreach ($courses as $course) {
            $activities[] = [
                'type' => 'course_published',
                'message' => "Novi kurs \"{$course->title}\" je objavljen",
                'timestamp' => $course->created_at instanceof Carbon
                    ? $course->created_at->toIso8601String()
                    : now()->toIso8601String(),
                'data' => [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'author' => $course->author->name ?? 'N/A',
                ],
            ];
        }

        return $activities;
    }

    /**
     * Build activities for recent user registrations.
     *
     * @param Collection<User> $users
     * @return array<int, array<string, mixed>>
     */
    public function buildUserActivities(Collection $users): array
    {
        $activities = [];

        foreach ($users as $user) {
            $activities[] = [
                'type' => 'user_registered',
                'message' => "Korisnik {$user->name} se registrovao",
                'timestamp' => $user->created_at instanceof Carbon
                    ? $user->created_at->toIso8601String()
                    : now()->toIso8601String(),
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                ],
            ];
        }

        return $activities;
    }

    /**
     * Build activities for recent course purchases.
     *
     * @param Collection<Payment> $payments
     * @return array<int, array<string, mixed>>
     */
    public function buildPurchaseActivities(Collection $payments): array
    {
        $activities = [];

        foreach ($payments as $payment) {
            $activities[] = [
                'type' => 'course_purchased',
                'message' => "Korisnik {$payment->user->name} je kupio kurs",
                'timestamp' => $payment->created_at instanceof Carbon
                    ? $payment->created_at->toIso8601String()
                    : now()->toIso8601String(),
                'data' => [
                    'order_id' => $payment->id,
                    'user_name' => $payment->user->name,
                    'course_title' => $payment->course->title ?? 'N/A',
                ],
            ];
        }

        return $activities;
    }

    /**
     * Sort activities by timestamp and limit the number of activities.
     *
     * @param array<int, array<string, mixed>> $activities
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function sortAndLimitActivities(array $activities, int $limit): array
    {
        usort($activities, function ($a, $b) {
            $aTime = Carbon::parse($a['timestamp']);
            $bTime = Carbon::parse($b['timestamp']);
            return $bTime->gt($aTime) ? 1 : -1;
        });

        return array_slice($activities, 0, $limit);
    }
}