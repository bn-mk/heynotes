<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function uploadAudio(Request $request)
    {
        $request->validate([
            // Validate by extension to tolerate mime parameters like 'audio/webm;codecs=opus'
            'audio' => 'required|file|mimes:webm,ogg,mp3,wav,m4a|max:51200', // up to 50MB
        ]);

        $file = $request->file('audio');
        $ext = $file->getClientOriginalExtension();
        $name = Str::uuid()->toString() . ($ext ? ('.' . $ext) : '');
        $path = $file->storeAs('audio', $name, 'public');

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'url' => $url,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ], 201);
    }
}

