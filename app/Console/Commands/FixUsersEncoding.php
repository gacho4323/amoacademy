<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class FixUsersEncoding extends Command
{
    protected $signature = 'fix:users-encoding';
    protected $description = 'Fix malformed UTF-8 characters in user fields';

    public function handle()
    {
        $this->info('Starting to fix user encodings...');

        $fieldsToFix = [
            'name',
            'first_name',
            'last_name',
        ];

        $count = 0;

        User::query()->chunk(100, function ($users) use (&$count, $fieldsToFix) {
            foreach ($users as $user) {
                $changed = false;

                foreach ($fieldsToFix as $field) {
                    if (!is_null($user->$field)) {
                        $fixed = mb_convert_encoding($user->$field, 'UTF-8', 'UTF-8');
                        if ($fixed !== $user->$field) {
                            $user->$field = $fixed;
                            $changed = true;
                        }
                    }
                }

                if ($changed) {
                    $user->save();
                    $count++;
                }
            }
        });

        $this->info("Encoding fix complete. {$count} users updated.");
        return 0;
    }
}
