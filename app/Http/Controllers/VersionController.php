<?php

namespace App\Http\Controllers;

use App\Generators\GeneratorRegistry;
use App\Models\GeneratorVersion;
use App\Models\Tool;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VersionController extends Controller
{
    public function index(Tool $tool, GeneratorRegistry $registry)
    {
        $versions = GeneratorVersion::where('tool_id', $tool->id)->latest()->get()->map(function ($version) {
            $schema = DB::table('generator_form_definitions')->where('generator_version_id', $version->id)->value('schema');

            return [...$version->toArray(), 'schema' => $schema ? json_decode($schema, true, 512, JSON_THROW_ON_ERROR) : null];
        });

        return Inertia::render('Versions', ['tool' => $tool, 'baseline' => $registry->module($tool->generator_id)->schema(), 'versions' => $versions]);
    }

    public function store(Request $request, Tool $tool, ?int $id = null)
    {
        $version = $id ? GeneratorVersion::where('tool_id', $tool->id)->findOrFail($id) : new GeneratorVersion;
        abort_if($id && $version->status !== 'DRAFT', 423, 'Versi yang sudah diaudit tidak dapat diubah. Salin sebagai draf baru.');
        $data = $request->validate([
            'version' => ['required', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/D', Rule::unique('generator_versions')->where('tool_id', $tool->id)->ignore($id)],
            'changelog' => ['required', 'string', 'max:4000'], 'fields' => ['required', 'array', 'min:1', 'max:200'],
            'fields.*' => ['array:key,label,type,default,placeholder,options,min,max,conditional,group'],
            'fields.*.key' => ['required', 'string', 'distinct', 'regex:/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/D'],
            'fields.*.label' => ['required', 'string', 'max:180'], 'fields.*.type' => ['required', 'in:text,number,select,checkbox'],
            'fields.*.default' => ['present'], 'fields.*.placeholder' => ['nullable', 'string', 'max:200'], 'fields.*.group' => ['nullable', 'string', 'max:100'],
            'fields.*.conditional' => ['nullable', 'string', 'max:80'], 'fields.*.min' => ['nullable', 'numeric', 'min:-10000', 'max:10000'], 'fields.*.max' => ['nullable', 'numeric', 'min:-10000', 'max:10000'],
            'fields.*.options' => ['array', 'max:200'], 'fields.*.options.*' => ['array:label,value,disabled'], 'fields.*.options.*.label' => ['required', 'string', 'max:180'], 'fields.*.options.*.value' => ['present', 'nullable', 'string', 'max:200'], 'fields.*.options.*.disabled' => ['boolean'],
            'status' => ['prohibited'], 'evidence' => ['prohibited'], 'code' => ['prohibited'], 'handler' => ['prohibited'],
        ]);
        $checkboxes = collect($data['fields'])->where('type', 'checkbox')->pluck('key')->all();
        foreach ($data['fields'] as $index => &$field) {
            if ($field['type'] !== 'checkbox' && $field['default'] === null) {
                $field['default'] = '';
            }
            $field['options'] = array_map(fn ($option) => array_replace($option, ['value' => $option['value'] ?? '']), $field['options'] ?? []);
            $error = null;
            $default = $field['default'];
            if ($field['type'] === 'checkbox' ? ! is_bool($default) : (! is_string($default) || strlen($default) > 500 || preg_match('/[\x00-\x1f\x7f]/', $default))) {
                $error = 'Default tidak sesuai jenis field.';
            }
            if ($field['type'] === 'select' && (! count($field['options'] ?? []) || ! in_array($default, array_column($field['options'], 'value'), true))) {
                $error = 'Default harus terdapat dalam pilihan.';
            }
            if (! empty($field['conditional']) && (! in_array($field['conditional'], $checkboxes, true) || $field['conditional'] === $field['key'])) {
                $error = 'Conditional harus merujuk checkbox lain.';
            }
            foreach (['min', 'max'] as $bound) {
                if (isset($field[$bound])) {
                    $field[$bound] = (float) $field[$bound];
                }
            }
            if (isset($field['min'],$field['max']) && $field['min'] > $field['max']) {
                $error = 'Min tidak boleh lebih besar dari max.';
            }
            if ($field['type'] === 'number' && (! is_numeric($default) || (isset($field['min']) && (float) $default < $field['min']) || (isset($field['max']) && (float) $default > $field['max']))) {
                $error = 'Default numerik harus berada dalam batas.';
            }
            if ($error) {
                throw ValidationException::withMessages(['fields.'.$index.'.default' => $error]);
            }
        }unset($field);
        DB::transaction(function () use ($version, $tool, $data) {
            $version->fill(['tool_id' => $tool->id, 'version' => $data['version'], 'changelog' => $data['changelog'], 'status' => 'DRAFT', 'evidence' => null])->save();
            DB::table('generator_form_definitions')->updateOrInsert(['generator_version_id' => $version->id], ['schema' => json_encode(['id' => $tool->generator_id, 'status' => 'DRAFT', 'source' => 'admin-review-draft', 'fields' => $data['fields']], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            Audit::record('admin.version_draft_saved', 'generator_version', $version->id, ['version' => $data['version']]);
        });

        return back()->with('success', 'Draf disimpan. Implementasi aktif tidak berubah.');
    }

    public function destroy(Tool $tool, int $id)
    {
        $version = GeneratorVersion::where('tool_id', $tool->id)->findOrFail($id);
        abort_unless($version->status === 'DRAFT', 423);
        $version->delete();
        Audit::record('admin.version_draft_deleted','generator_version',$id);

        return back();
    }
}
