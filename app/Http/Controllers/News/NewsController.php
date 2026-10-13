<?php

namespace App\Http\Controllers\News;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;
use Stevebauman\Purify\Facades\Purify;

class NewsController extends Controller
{
    public function index()
    {
        // The management list shows every announcement, including ones scheduled
        // ahead of now — unlike the homepage, which only surfaces published ones.
        $news = News::orderBy('published_at', 'desc')->paginate(25);

        return view('news.admin.index', ['news' => $news]);
    }

    public function create()
    {
        return view('news.admin.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string',
        ]);

        $news = new News([
            'title' => $validated['title'],
            'content' => Purify::clean($validated['content']),
            'published_at' => now(),
        ]);

        $news->save();

        return redirect()->route('home');

    }

    public function edit(News $news)
    {
        return view('news.admin.edit', ['news' => $news]);
    }

    public function update(Request $request, News $news)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string',
        ]);

        $news->title = $validated['title'];
        $news->content = Purify::clean($validated['content']);
        $news->save();

        return redirect()->route('admin.news.index');
    }
}
