<?php
namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ExportUsersToCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExportCsvController extends Controller
{
    public function exportCsv(Request $request)
    {
        $userId = auth()->id();
        try {
            Log::info('Starting CSV export for user ID: ' . $userId);
            Log::info('Filesystem disks config: ' . json_encode(config('filesystems.disks')));

            // Verify csv disk
            if (!config('filesystems.disks.csv')) {
                Log::error('CSV disk not configured in filesystems.php');
                throw new \Exception('CSV disk not configured');
            }

            // Define headers in requested order
            $headers = [
                'Email', 'First Name', 'Last Name', 'City', 'Country', 'State',
                'Date of Birth', 'Phone Number', 'Email Opt-In Date', 'Email Opt-Out Date',
            ];

            // Fetch users with matching columns
            $users = \App\Models\User::select([
                'email', 'first_name', 'last_name', 'city', 'country', 'state',
                'date_of_birth', 'phone_number', 'email_opt_in_date', 'email_opt_out_date',
            ])->get();
            Log::info('Fetched ' . $users->count() . ' users for export');

            // Create CSV content with UTF-8 BOM for Excel compatibility
            $csvContent = "\xEF\xBB\xBF"; // UTF-8 BOM
            $csvContent .= implode(',', array_map([$this, 'escapeCsvField'], $headers)) . "\n";
            foreach ($users as $user) {
                $row = [
                    $user->email ?? '',
                    $user->first_name ?? '',
                    $user->last_name ?? '',
                    $user->city ?? '',
                    $user->country ?? '',
                    $user->state ?? '',
                    $user->date_of_birth ? $user->date_of_birth->format('Y-m-d') : '',
                    $user->phone_number ?? '',
                    $user->email_opt_in_date ? $user->email_opt_in_date->format('Y-m-d') : '',
                    $user->email_opt_out_date ? $user->email_opt_out_date->format('Y-m-d') : '',
                ];
                $csvContent .= implode(',', array_map([$this, 'escapeCsvField'], $row)) . "\n";
            }
            Log::info('CSV content generated: ' . substr($csvContent, 0, 200) . '...');

            // Write CSV to storage/app/csv
            $csvFileName = 'users_export_' . time() . '.csv';
            $filePath = $csvFileName;
            $filePath = str_replace('\\', '/', $filePath);
            $fullPath = Storage::disk('csv')->path($filePath);
            Log::info('Writing CSV to: ' . $fullPath);
            Storage::disk('csv')->put($filePath, $csvContent);
            Log::info('CSV file written successfully: ' . $filePath);

            // Verify CSV
            if (!Storage::disk('csv')->exists($filePath)) {
                Log::error('CSV file not found: ' . $filePath);
                throw new \Exception('CSV file not found: ' . $filePath);
            }

            // Write path file to storage/app/public/csv_paths
            $pathFile = 'csv_paths/' . $userId . '.txt';
            $pathFile = str_replace('\\', '/', $pathFile);
            Log::info('Writing path file to: ' . Storage::disk('public')->path($pathFile));
            Storage::disk('public')->put($pathFile, $filePath);
            Log::info('Path file written: public/' . $pathFile);

            return response()->json([
                'message' => 'CSV export completed. Download and import into Excel to format as a table.',
                'check_url' => route('admin.check-csv', ['userId' => $userId]),
            ]);
        } catch (\Exception $e) {
            Log::error('Export failed for user ID ' . $userId . ': ' . $e->getMessage());
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    public function checkCsvStatus($userId)
    {
        if ($userId != auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $pathFile = 'csv_paths/' . $userId . '.txt';
            $pathFile = str_replace('\\', '/', $pathFile);
            Log::info('Checking path file: ' . Storage::disk('public')->path($pathFile));
            if (!Storage::disk('public')->exists($pathFile)) {
                Log::error('Path file does not exist: public/' . $pathFile);
                return response()->json(['ready' => false, 'error' => 'Path file does not exist'], 404);
            }

            $filePath = Storage::disk('public')->get($pathFile);
            Log::info('Checking CSV status for user ID ' . $userId . ', filePath: ' . ($filePath ?: 'empty'));

            if ($filePath === '') {
                Log::error('Path file is empty: public/' . $pathFile);
                return response()->json(['ready' => false, 'error' => 'Path file is empty'], 500);
            }

            if (!config('filesystems.disks.csv')) {
                Log::error('CSV disk not configured in filesystems.php');
                return response()->json(['ready' => false, 'error' => 'CSV disk not configured'], 500);
            }

            if (Storage::disk('csv')->exists($filePath)) {
                Log::info('CSV file found: ' . $filePath);
                return response()->json([
                    'ready' => true,
                    'download_url' => route('admin.download-csv', ['userId' => $userId]),
                ]);
            }

            Log::warning('CSV file does not exist: ' . $filePath);
            return response()->json(['ready' => false, 'error' => 'CSV file not found']);
        } catch (\Exception $e) {
            Log::error('Failed to check CSV status for user ID ' . $userId . ': ' . $e->getMessage());
            return response()->json(['ready' => false, 'error' => 'Failed to check status: ' . $e->getMessage()], 500);
        }
    }

    public function downloadCsv($userId)
    {
        if ($userId != auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        try {
            $pathFile = 'csv_paths/' . $userId . '.txt';
            $pathFile = str_replace('\\', '/', $pathFile);
            Log::info('Downloading path file: ' . Storage::disk('public')->path($pathFile));
            if (!Storage::disk('public')->exists($pathFile)) {
                Log::error('Path file does not exist: public/' . $pathFile);
                return response()->json(['error' => 'Path file does not exist'], 404);
            }

            $filePath = Storage::disk('public')->get($pathFile);
            if ($filePath && Storage::disk('csv')->exists($filePath)) {
                Log::info('Downloading CSV file: ' . $filePath);
                Storage::disk('public')->delete($pathFile);

                // Return file with explicit headers to ensure Save As dialog
                return Storage::disk('csv')->download($filePath, 'users_export.csv', [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="users_export.csv"',
                ]);
            }
            Log::error('CSV file not found for download: ' . $filePath);
            return response()->json(['error' => 'File not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to download CSV for user ID ' . $userId . ': ' . $e->getMessage());
            return response()->json(['error' => 'Download failed: ' . $e->getMessage()], 500);
        }
    }

    private function escapeCsvField($field)
    {
        if (is_null($field) || $field === '') {
            return '""'; // Empty fields quoted for Excel
        }
        // Always quote to ensure Excel compatibility
        return '"' . str_replace('"', '""', $field) . '"';
    }
}