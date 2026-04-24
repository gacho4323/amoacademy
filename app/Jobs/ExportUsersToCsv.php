<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportUsersToCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
        Log::info('📦 ExportUsersToCsv job initialized for user ID: ' . $this->userId);
    }

    public function handle(): void
    {
        try {
            Log::info('🚀 Starting CSV export for user ID: ' . $this->userId);

            $disk = Storage::disk('csv');

            $headers = ['Name', 'Email'];

            $users = User::select(['name', 'email'])->get();

            $csvContent = implode(',', array_map([$this, 'escapeCsvField'], $headers)) . "\n";

            foreach ($users as $user) {
                $row = [
                    $user->name ?? '',
                    $user->email ?? '',
                ];

                $csvContent .= implode(',', array_map([$this, 'escapeCsvField'], $row)) . "\n";
            }

            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'UTF-8');

            $fileName = 'users_export_' . time() . '.csv';
            $disk->put($fileName, $csvContent);

            Log::info('✅ CSV file successfully written: ' . $fileName);

        } catch (\Throwable $e) {
            Log::error('❌ ExportUsersToCsv failed for user ID ' . $this->userId . ': ' . $e->getMessage());
            throw $e;
        }
    }

    private function escapeCsvField($field): string
    {
        if (is_null($field)) return '';
        $field = (string)$field;

        if (str_contains($field, ',') || str_contains($field, '"')) {
            $field = '"' . str_replace('"', '""', $field) . '"';
        }

        return $field;
    }
}
