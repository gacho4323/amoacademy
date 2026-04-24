<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CleanUserNamesUtf8 extends Migration
{
    public function up()
    {
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $cleanName = $this->sanitizeString($user->name ?? 'Unknown');
            $cleanFirstName = $this->sanitizeString($user->first_name ?? '');
            $cleanLastName = $this->sanitizeString($user->last_name ?? '');
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'name' => $cleanName,
                    'first_name' => $cleanFirstName,
                    'last_name' => $cleanLastName
                ]);
        }
    }

    public function down()
    {
        // No rollback needed
    }

    private function sanitizeString($input)
    {
        if (!mb_check_encoding($input, 'UTF-8')) {
            return mb_convert_encoding($input, 'UTF-8', 'auto');
        }
        return $input;
    }
}