<?php

namespace App\Http\Controllers;

use App\Generators\GeneratorRegistry;
use App\Models\GeneratorTemplate;
use App\Models\Tool;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TemplateController extends Controller
{
    public function index(Tool $tool, GeneratorRegistry $registry)
    {
        $module = $registry->module($tool->generator_id);

        return Inertia::render('Templates', ['tool' => $tool, 'schema' => $module->schema(), 'version' => $module->version(), 'templates' => GeneratorTemplate::where('tool_id', $tool->id)->latest()->get()]);
    }

    public function store(Request $request, Tool $tool, GeneratorRegistry $registry, ?int $id = null)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'input' => ['required', 'array', 'max:200'], 'is_active' => ['required', 'boolean'], 'code' => ['prohibited'], 'version' => ['prohibited']]);
        $module = $registry->module($tool->generator_id);
        $input = $module->validate($data['input']);
        $template = $id ? GeneratorTemplate::where('tool_id', $tool->id)->findOrFail($id) : new GeneratorTemplate;
        $template->fill(['tool_id' => $tool->id, 'name' => $data['name'], 'defaults' => $input, 'is_active' => $data['is_active'], 'version' => $module->version()])->save();
        Audit::record('admin.template_saved', 'template', $template->id, ['version' => $module->version()]);

        return back()->with('success', 'Template disimpan. Status publikasi generator tidak berubah.');
    }

    public function destroy(Tool $tool, int $id)
    {
        $template = GeneratorTemplate::where('tool_id', $tool->id)->findOrFail($id);
        $template->delete();
        Audit::record('admin.template_deleted', 'template', $id);

        return back();
    }
}
