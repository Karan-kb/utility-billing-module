<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function download(string $filename)
    {
        try {
            // Sanitize filename to prevent path traversal
            $filename = basename($filename);
            $disk = Storage::disk('company');

            if (!$disk->exists($filename)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            $url = $disk->temporaryUrl($filename, now()->addMinutes(2));
            return response()->json(['success' => true, 'url' => $url], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the file URL.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}