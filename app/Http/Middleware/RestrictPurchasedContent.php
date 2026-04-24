<?php

namespace App\Http\Middleware;

use App\Models\Course;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestrictPurchasedContent
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $course = $request->route('course'); // Get the course from the route parameter

        // Check if the user has purchased the course
        $hasPurchased = $user->courses()
            ->where('course_id', $course->id)
            ->exists();

        if (!$hasPurchased) {
            return response()->json(['error' => 'You must purchase this course to access its content.'], 403);
        }

        return $next($request);
    }
}