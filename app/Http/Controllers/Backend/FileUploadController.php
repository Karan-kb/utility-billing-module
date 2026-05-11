<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // 2MB max
            ]);

            // Get the file from the request
            $file = $request->file('file');

            // Check if file is null
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file provided in the request.',
                ], 400);
            }

            // Generate a unique filename to avoid overwrites
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('', $filename, 'private');

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully.',
                'path' => $path,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during file upload.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function download($filename)
    {
        try {
            // Sanitize filename to prevent path traversal
            $filename = basename($filename);
            if (Storage::disk('private')->exists($filename)) {
                return Storage::disk('private')->download($filename);
            }

            return response()->json(['error' => 'File not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while downloading the file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}