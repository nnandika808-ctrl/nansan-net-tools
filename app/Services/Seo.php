<?php

namespace App\Services;

use App\Models\CmsPage;
use App\Models\DocumentationArticle;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class Seo
{
    public function paths(): array
    {
        return array_merge(['/', '/tools', '/docs'], Tool::all()->map(fn ($t) => '/tools/'.$t->slug)->all(), DocumentationArticle::published()->get()->map(fn ($a) => '/docs/'.$a->slug)->all(), CmsPage::where('status', 'published')->get()->map(fn ($p) => '/pages/'.$p->slug)->all());
    }

    public function forRequest(Request $request): array
    {
        $path = '/'.trim($request->path(), '/');
        $metadata = DB::table('seo_metadata')->where('path', $path)->first();
        $public = $path === '/' || $path === '/tools' || $path === '/docs' || preg_match('~^/(tools|docs|pages)/[a-z0-9-]+$~D', $path);

        return ['title' => $metadata?->title ?? 'NANSAN NET Tools', 'customized' => (bool) $metadata,
            'description' => $metadata?->description ?? 'Platform generator konfigurasi MikroTik. Gratis untuk komunitas jaringan.',
            'canonical' => rtrim(config('app.url'), '/').($path === '/' ? '' : $path),
            'image' => $metadata?->og_image ? rtrim(config('app.url'), '/').$metadata->og_image : null,
            'indexable' => (bool) config('nansan.indexable') && $public && ! $request->user() && ! $metadata?->noindex];
    }
}
