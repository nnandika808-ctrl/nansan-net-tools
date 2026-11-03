<?php

namespace Database\Seeders;

use App\Generators\GeneratorRegistry;
use App\Models\GeneratorVersion;
use App\Models\Tool;
use App\Services\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GeneratorEvidenceSeeder extends Seeder
{
    public function run(): void
    {
        $registry = app(GeneratorRegistry::class);
        foreach ([
            'G01' => ['TESTING', 'Draf 47 pilihan: 188 kombinasi wrapper diamati, 92 snapshot parameter dataset tersedia. Shopify 404; lab dan cakupan aplikasi memerlukan review.', ['docs/reference/evidence/G01-cases-semantics.json', 'resources/datasets/static-routing/manifest.json']],
            'G02' => ['TESTING', 'Draf Queue QoS: 214 kasus parameter diamati. Dataset bersama mempertahankan duplikat; Shopify 404. Shaping/prioritas nyata belum diverifikasi.', ['docs/reference/evidence/G02-cases-semantics.json', 'resources/datasets/static-routing/manifest.json']],
            'G05' => ['TESTING', 'Draf 106 pilihan game: dua entri quote referensi tidak berpasangan ditandai FAILED. Kebaruan port dan trafik nyata belum diverifikasi.', ['docs/reference/evidence/G05-cases-semantics.json', 'resources/datasets/routing-games/manifest.json']],
            'G03' => ['TESTING', 'Draf dua WAN lulus proyeksi parameter dan paket UDP/NAT virtual. Cakupan opsi serta jaringan ISP nyata belum lengkap.', ['docs/reference/evidence/G03-cases-semantics.json', 'docs/lab/PCC-PACKETS-6.49.19.json', 'docs/lab/PCC-PACKETS-7.25.json']],
            'G04' => ['BLOCKED', 'Draf Starlink: kompatibilitas renewal gateway ROS6 memerlukan keputusan review; belum dipublikasikan.', ['docs/reference/evidence/G04-cases-semantics.json', 'docs/lab/STARLINK-PROTOCOL-6.49.19.json', 'docs/lab/STARLINK-PROTOCOL-7.25.json']],
        ] as $id => [$status,$reason,$reports]) {
            $tool = Tool::where('generator_id', $id)->firstOrFail();
            $module = $registry->module($id);
            $version = GeneratorVersion::firstOrCreate(['tool_id' => $tool->id, 'version' => $module->version()], ['status' => $status, 'changelog' => $reason, 'evidence' => ['authority' => 'reviewed-source', 'scope' => 'partial; private draft', 'reports' => $reports]]);
            if (($version->evidence['authority'] ?? null) !== 'reviewed-source') {
                throw new RuntimeException('Code version collides with an unreviewed database draft. No draft was overwritten.');
            }
            $version->update(['status' => $status, 'changelog' => $reason, 'evidence' => ['authority' => 'reviewed-source', 'scope' => 'partial; private draft', 'reports' => $reports]]);
            $tool->update(['status' => $status, 'blocked_reason' => $reason]);
            Audit::record('operator.generator_evidence_updated', 'tool', $tool->id, ['status' => $status, 'version' => $module->version()]);
            DB::table('generator_form_definitions')->updateOrInsert(['generator_version_id' => $version->id], ['schema' => json_encode($module->schema(), JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
