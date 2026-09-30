<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Streams uploaded files (challan photos, gate voice notes, attachments) from
 * storage/app/public without relying on the public/storage symlink.
 * BinaryFileResponse supports HTTP Range requests, which browsers use for
 * audio playback and seeking.
 */
class MediaController extends Controller
{
    private const MIME = [
        'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'aac' => 'audio/aac', 'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'opus' => 'audio/ogg', 'webm' => 'audio/webm', '3gp' => 'audio/3gpp',
    ];

    public function show(string $path)
    {
        $root = realpath(Storage::disk('public')->path(''));
        $full = realpath(Storage::disk('public')->path($path));

        // Block ../ traversal and anything outside the public disk
        if (!$root || !$full || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $headers = ['Cache-Control' => 'private, max-age=86400'];
        if (isset(self::MIME[$ext])) {
            $headers['Content-Type'] = self::MIME[$ext];
        } elseif (str_starts_with(str_replace('\\', '/', $path), 'challan_voice/')) {
            // Voice notes uploaded before the extension fix may be saved as .bin etc.
            // They are AAC from the app recorder, so tell the browser so.
            $headers['Content-Type'] = 'audio/mp4';
        }

        return response()->file($full, $headers);
    }
}
