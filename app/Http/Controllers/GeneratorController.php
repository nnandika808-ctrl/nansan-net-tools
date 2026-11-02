<?php
namespace App\Http\Controllers;
use App\Generators\GeneratorRegistry;
use App\Http\Requests\GenerateRequest;
use App\Models\{Tool,ScriptHistory};
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
class GeneratorController extends Controller
{
    public function preview(Tool $tool, GeneratorRegistry $registry)
    {
        abort_unless($tool->generator_id==='G03',423,'Draft generator ini belum diimplementasikan.');
        return \Inertia\Inertia::render('Tool',['tool'=>$tool->load('category'),'schema'=>$registry->module($tool->generator_id)->schema(),'available'=>false,'draft'=>true,'documentation'=>[]]);
    }
    public function previewOutput(GenerateRequest $request, Tool $tool, GeneratorRegistry $registry)
    {
        abort_unless($tool->generator_id==='G03',423,'Draft generator ini belum diimplementasikan.');
        $output=$registry->module($tool->generator_id)->generate($request->input('input'));
        Audit::record('generator.draft_preview','tool',$tool->id,['version'=>$output->version]);
        return response()->json(['steps'=>$output->steps,'rosVersion'=>$output->rosVersion,'version'=>$output->version,'warnings'=>$output->warnings]);
    }
    public function store(GenerateRequest $request, Tool $tool, GeneratorRegistry $registry)
    {
        abort_unless($registry->isPublished($tool->generator_id),423,'Generator belum dipublikasikan. Output dan kompatibilitas RouterOS belum terverifikasi.');
        $module=$registry->module($tool->generator_id); $input=$module->validate($request->input('input'));
        $output=$module->generate($input);
        DB::transaction(function () use ($request,$tool,$input,$output) {
            $tool->increment('usage_count');
            if ($request->boolean('save_history')) ScriptHistory::create(['user_id'=>$request->user()->id,'tool_id'=>$tool->id,'input'=>$input,'output'=>$output->text(),'generator_version'=>$output->version,'ros_version'=>$output->rosVersion,'output_sha256'=>hash('sha256',$output->text())]);
            Audit::record('generator.generated','tool',$tool->id,['version'=>$output->version]);
        });
        return response()->json(['steps'=>$output->steps,'rosVersion'=>$output->rosVersion,'version'=>$output->version,'warnings'=>$output->warnings]);
    }
}
