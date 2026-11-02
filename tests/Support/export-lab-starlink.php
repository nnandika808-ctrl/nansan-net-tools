<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$module=new App\Generators\Modules\StarlinkModule;
$output=[];
foreach(Tests\Feature\StarlinkRegressionTest::scenarios() as $case){
    if($case['input']['count']>3)continue; // The disposable VM has three WAN NICs.
    $data=$module->validate($case['input']);$result=$module->generate($case['input']);
    $entries=$module->configuration($data);
    $output[]=['id'=>$case['id'],'ros_version'=>$result->rosVersion,'script'=>$result->text(),'wan_count'=>(int)$data['count'],'netwatch'=>(bool)$data['netwatchEnable'],
        'leases'=>array_values(array_map(fn($e)=>$e['fields']['script'],array_filter($entries,fn($e)=>$e['path']==='/ip dhcp-client'))),
        'watch_scripts'=>array_values(array_map(fn($e)=>['up'=>$e['fields']['up-script'],'down'=>$e['fields']['down-script']],array_filter($entries,fn($e)=>$e['path']==='/tool netwatch')))];
}
file_put_contents(base_path('.runtime/starlink-lab-cases.json'),json_encode($output,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
echo count($output)." independent Starlink cases exported for disposable local CHR only.\n";
