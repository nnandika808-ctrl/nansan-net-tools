<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\DocumentationArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DocumentationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = $data['q'] ?? '';

        return Inertia::render('Documentation', ['articles' => DocumentationArticle::published()->when($q, fn ($query) => $query->where(fn ($s) => $s->where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%")->orWhere('title_en', 'like', "%{$q}%")))->latest('published_at')->paginate(12)->withQueryString(), 'q' => $q]);
    }

    public function show(string $slug)
    {
        $article = DocumentationArticle::published()->where('slug', $slug)->firstOrFail();

        return Inertia::render('Article', ['article' => $article, 'html' => $this->markdown($article->body), 'htmlEn' => $article->body_en ? $this->markdown($article->body_en) : null, 'related' => DocumentationArticle::published()->where('id', '!=', $article->id)->where('documentation_category_id', $article->documentation_category_id)->limit(3)->get(['title', 'title_en', 'slug', 'excerpt', 'excerpt_en'])]);
    }

    public function page(string $slug)
    {
        $page = CmsPage::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return Inertia::render('Article', ['article' => $page, 'html' => $this->markdown($page->body), 'htmlEn' => $page->body_en ? $this->markdown($page->body_en) : null, 'related' => []]);
    }

    public function markdown(string $body): string
    {
        return (string) Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
