<?php

namespace App\Support;

class Media
{
    // Public URL for a file on the 'public' disk. Served by MediaController,
    // so it works even when `php artisan storage:link` was never run.
    public static function url(?string $path): ?string
    {
        return $path ? route('media.show', ['path' => ltrim($path, '/')]) : null;
    }
}
