<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cases=json_decode(file_get_contents(base_path('docs/reference/evidence/G03-cases-semantics.json')),true,512,JSON_THROW_ON_ERROR);
$output=[];
foreach($cases as $case){$result=(new App\Generators\Modules\PccModule)->generate($case['input']);$output[]=['id'=>$case['test_id'],'ros_version'=>$result->rosVersion,'script'=>$result->text()];}
file_put_contents(base_path('.runtime/pcc-lab-cases.json'),json_encode($output,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
echo count($output)." independent PCC draft cases exported for disposable local CHR only.\n";
