<?php
namespace App\Http\Controllers;
use App\Models\FeedbackReport;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class FeedbackController extends Controller
{
    public function index(Request $request) { return Inertia::render('Feedback',['reports'=>$request->user()->feedback()->with('attachments')->latest()->paginate(15)]); }
    public function store(Request $request, MediaStorage $storage)
    {
        $data=$request->validate(['type'=>['required','in:bug,suggestion'],'title'=>['required','string','min:5','max:180'],'body'=>['required','string','min:15','max:10000'],'screenshot'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120']]);
        DB::transaction(function () use ($request,$data,$storage) {
            $report=$request->user()->feedback()->create(collect($data)->only(['type','title','body'])->all());
            if ($request->hasFile('screenshot')) $report->attachments()->attach($storage->store($request->file('screenshot'),$request->user(),false,'feedback')->id);
        });
        return back()->with('success','Laporan berhasil dikirim.');
    }
}
