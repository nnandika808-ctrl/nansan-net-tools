<?php
namespace App\Services;
use App\Models\{MediaFile,User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class MediaStorage
{
    public function store(UploadedFile $file, User $user, bool $public=false, string $folder='general'): MediaFile
    {
        $size=@getimagesize($file->getRealPath());
        if (!$size || $size[0]>6000 || $size[1]>6000 || $size[0]*$size[1]>20000000) throw ValidationException::withMessages(['file'=>'Dimensi gambar terlalu besar atau file tidak valid.']);
        $image=@imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (!$image) throw ValidationException::withMessages(['file'=>'Gambar tidak dapat diproses.']);
        $image=imagescale($image,min(1920,imagesx($image)));
        ob_start(); imagewebp($image,null,82); $bytes=ob_get_clean(); imagedestroy($image);
        $path='media/'.Str::uuid().'.webp'; Storage::disk('local')->put($path,$bytes);
        return MediaFile::create(['user_id'=>$user->id,'path'=>$path,'original_name'=>Str::limit(basename($file->getClientOriginalName()),170,''),'mime_type'=>'image/webp','size'=>strlen($bytes),'folder'=>$folder,'is_public'=>$public]);
    }
}
