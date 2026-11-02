<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Services\Audit;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(MediaFile $media, Request $request)
    {
        abort_unless($media->is_public || $request->user()?->isAdmin() || ($request->user() && $media->user_id === $request->user()->id), 404);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => $media->is_public ? 'public, max-age=86400' : 'private, no-store']);
    }

    public function store(Request $request, MediaStorage $storage)
    {
        $data = $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'folder' => ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9 _-]+$/'], 'is_public' => ['sometimes', 'boolean']]);
        $media = $storage->store($request->file('file'), $request->user(), $request->boolean('is_public'), $data['folder'] ?? 'general');
        Audit::record('media.uploaded', 'media', $media->id);

        return back()->with('success', 'Gambar diunggah. URL: /media/'.$media->id);
    }

    public function avatar(Request $request, MediaStorage $storage)
    {
        $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $previous = $request->user()->avatar_path;
        $media = $storage->store($request->file('file'), $request->user(), false, 'avatars');
        $request->user()->update(['avatar_path' => '/media/'.$media->id]);
        if ($previous && preg_match('~^/media/(\d+)$~', $previous, $match)) {
            $old = MediaFile::whereKey($match[1])->where('user_id', $request->user()->id)->first();
            if ($old) {
                Storage::disk($old->disk)->delete($old->path);
                $old->delete();
            }
        }

        return back()->with('success', 'Foto profil diperbarui.');
    }

    public function destroy(MediaFile $media)
    {
        abort_if($media->folder === 'avatars', 422, 'Ganti foto melalui pengaturan profil.');
        Storage::disk($media->disk)->delete($media->path);
        Audit::record('media.deleted', 'media', $media->id);
        $media->delete();

        return back()->with('success', 'Media dihapus.');
    }
}
