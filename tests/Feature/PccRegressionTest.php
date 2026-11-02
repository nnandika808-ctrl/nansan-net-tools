<?php
namespace Tests\Feature;
use App\Generators\Modules\PccModule;
use App\Generators\RouterOs;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class PccRegressionTest extends TestCase
{
    public function test_all_five_observed_pcc_rule_parameter_projections_match(): void
    {
        $cases=json_decode(file_get_contents(base_path('docs/reference/evidence/G03-cases-semantics.json')),true,512,JSON_THROW_ON_ERROR);$module=new PccModule;
        foreach($cases as $case){
            $actual=$module->configuration($module->validate($case['input'])); $expected=$case['entries'];
            foreach($actual as &$entry)ksort($entry['fields']);unset($entry);
            foreach($expected as &$entry)ksort($entry['fields']);unset($entry);
            $this->assertSame($expected,$actual,$case['test_id']);
        }
    }
    public function test_draft_generation_has_valid_escaping_and_warns_about_unverified_lab(): void
    {
        $result=(new PccModule)->generate([]);$this->assertNotEmpty($result->steps);$this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('DRAFT',$result->text());
        $this->assertSame('"name\\$x\\"\\\\"',RouterOs::quote('name$x"\\'));
    }
    public function test_unsupported_wan_count_and_interface_injection_are_rejected(): void
    {
        $module=new PccModule;
        foreach([['num-wan-lines'=>3],['wan-isp-1'=>'ether1; /system reset-configuration'],['interface-target'=>'192.168.1.0/99'],['wan-isp-2'=>'ether1'],['speed-isp-1'=>9999,'speed-isp-2'=>10000,'bandwidth-ratio-checkbox'=>true]] as $invalid){
            try{$module->validate($invalid);$this->fail('Invalid configuration accepted');}catch(ValidationException $exception){$this->assertNotEmpty($exception->errors());}
        }
    }
}
