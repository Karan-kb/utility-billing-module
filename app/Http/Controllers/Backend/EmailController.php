<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Mail\SendFileEmail;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function sendEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'attachments.*' => 'nullable|file|max:2048'
        ]);

        $subject = $request->subject;
        $body    = $request->body;

        $files = $request->file('attachments', []);

        // Ensure array
        if (!is_array($files)) {
            $files = [$files];
        }

        Mail::to($request->email)
            ->send(new SendFileEmail($subject, $body, $files));

        return response()->json([
            'message' => 'Email sent successfully'
        ]);
    }
}