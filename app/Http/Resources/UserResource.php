<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        // Sanitize string fields to ensure valid UTF-8 encoding
        $name = $this->sanitizeString($this->name ?? 'Unknown');
        $firstName = $this->sanitizeString($this->first_name ?? '');
        $lastName = $this->sanitizeString($this->last_name ?? '');
        $email = $this->sanitizeString($this->email ?? 'N/A');
        $role = $this->sanitizeString($this->role ?? 'user');

        return [
            'id' => $this->id,
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role' => $role,
            'avatar' => $name ? strtoupper(mb_substr($name, 0, 1, 'UTF-8')) : 'U',
        ];
    }

    /**
     * Sanitize a string to ensure valid UTF-8 encoding.
     *
     * @param string $input
     * @return string
     */
    private function sanitizeString($input)
    {
        // Check if the string is valid UTF-8
        if (!mb_check_encoding($input, 'UTF-8')) {
            // Convert to UTF-8, replacing invalid characters
            return mb_convert_encoding($input, 'UTF-8', 'auto');
        }
        return $input;
    }
}