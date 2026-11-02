<?php
namespace App\Generators\Modules;
use App\Generators\{GeneratorModule,GeneratedScript,UnverifiedGenerator,RouterOs};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\{Rule,ValidationException};
final class PccModule implements GeneratorModule
{
    private const LOCAL=['192.168.0.0/16','172.16.0.0/12','10.0.0.0/8'];
    public function id(): string { return 'G03'; }
    public function schema(): array { return (new UnverifiedGenerator($this->id()))->schema(); }
    public function validate(array $input): array
    {
        $defaults=array_column($this->schema()['fields'],'default','key');
        $data=array_replace($defaults,$input);
        $allowed=array_keys($defaults);
        if(array_diff(array_keys($input),$allowed)) throw ValidationException::withMessages(['input'=>'Field yang tidak dikenal ditolak.']);
        $classifiers=array_column(collect($this->schema()['fields'])->firstWhere('key','pccType')['options'],'value');
        $rules=[
            'num-wan-lines'=>['required','integer',Rule::in([2])], // First audited implementation: two WAN only.
            'routeros-version'=>['required',Rule::in(['v6.xx','v7.xx'])],
            'local-target-type'=>['required',Rule::in(['IP Address List','In. Interface','In. Interface List'])],
            'interface-target'=>['required','string','max:500'],
            'pccType'=>['required',Rule::in($classifiers)],'bandwidth-ratio-checkbox'=>['required','boolean'],'recursive-checkbox'=>['required','boolean'],
        ];
        for($i=1;$i<=2;$i++){
            $rules['wan-isp-'.$i]=['required','string',fn($attribute,$value,$fail)=>RouterOs::interfaceName($value)?:$fail('Interface tidak valid.')];
            $rules['gateway-isp-'.$i]=['required','string',fn($attribute,$value,$fail)=>RouterOs::gateway($value)?:$fail('Gateway IPv4 atau interface tidak valid.')];
            $rules['speed-isp-'.$i]=[$data['bandwidth-ratio-checkbox']?'required':'nullable','integer','min:1','max:10000'];
            $rules['ip-public-check-isp-'.$i]=[$data['recursive-checkbox']?'required':'nullable','string','max:253','regex:/^[a-zA-Z0-9][a-zA-Z0-9.\-]*$/D'];
        }
        $data=Validator::make($data,$rules)->validate();
        if($data['wan-isp-1']===$data['wan-isp-2']) throw ValidationException::withMessages(['wan-isp-2'=>'Interface WAN harus berbeda.']);
        if($data['local-target-type']==='IP Address List'){
            $subnets=array_map('trim',explode(',',$data['interface-target']));
            foreach($subnets as $subnet) if(!RouterOs::ipv4Subnet($subnet)) throw ValidationException::withMessages(['interface-target'=>'Gunakan IPv4/CIDR yang valid, dipisahkan koma.']);
            if(count($subnets)!==count(array_unique($subnets))) throw ValidationException::withMessages(['interface-target'=>'Subnet duplikat ditolak.']);
            $data['interface-target']=implode(', ',$subnets);
        } elseif(!RouterOs::interfaceName($data['interface-target'])) throw ValidationException::withMessages(['interface-target'=>'Nama interface/list tidak valid.']);
        if($data['bandwidth-ratio-checkbox']){
            $gcd=$this->gcd((int)$data['speed-isp-1'],(int)$data['speed-isp-2']);
            if(($data['speed-isp-1']+$data['speed-isp-2'])/$gcd>256) throw ValidationException::withMessages(['speed-isp-1'=>'Rasio menghasilkan terlalu banyak rule PCC (maksimal 256).']);
        }
        return $data;
    }
    public function configuration(array $data): array
    {
        $entries=[];$add=function(string $path,array $fields) use (&$entries){$entries[]=['path'=>$path,'operation'=>'add','fields'=>$fields];};
        $ros7=$data['routeros-version']==='v7.xx';$recursive=(bool)$data['recursive-checkbox'];
        $local=$data['local-target-type']==='IP Address List'?array_map('trim',explode(',',$data['interface-target'])):self::LOCAL;
        foreach($local as $subnet) $add('/ip firewall address-list',['address'=>$subnet,'list'=>'LOCAL-IP']);
        for($i=1;$i<=2;$i++) $add('/ip firewall nat',['chain'=>'srcnat','out-interface'=>$data['wan-isp-'.$i],'dst-address-list'=>'!LOCAL-IP','action'=>'masquerade']);
        if($ros7)for($i=1;$i<=2;$i++)$add('/routing table',['name'=>'to-ISP'.$i,'fib'=>true]);
        if($recursive)for($i=1;$i<=2;$i++)$add('/ip route',['dst-address'=>$data['ip-public-check-isp-'.$i],'distance'=>'1','gateway'=>$data['gateway-isp-'.$i],'target-scope'=>'10','scope'=>'10']);
        for($group=0;$group<2;$group++)for($i=1;$i<=2;$i++){
            $fields=['check-gateway'=>'ping','distance'=>(string)($group===0?1:$i),'gateway'=>$data[($recursive?'ip-public-check-isp-':'gateway-isp-').$i],'scope'=>'30','target-scope'=>$recursive?'30':'10'];
            if($group===0||$ros7)$fields[$ros7?'routing-table':'routing-mark']=$group===0?'to-ISP'.$i:'main';
            $add('/ip route',$fields);
        }
        $add('/ip firewall mangle',['action'=>'accept','chain'=>'prerouting','hotspot'=>'from-client,!auth','disabled'=>'yes']);
        foreach(['prerouting','postrouting','forward','input','output'] as $chain)$add('/ip firewall mangle',['action'=>'accept','chain'=>$chain,'dst-address-list'=>'LOCAL-IP','src-address-list'=>'LOCAL-IP']);
        for($i=1;$i<=2;$i++)$add('/ip firewall mangle',['action'=>'mark-connection','chain'=>'input','connection-mark'=>'no-mark','in-interface'=>$data['wan-isp-'.$i],'new-connection-mark'=>'ISP'.$i.'-conn','passthrough'=>'yes']);
        for($i=1;$i<=2;$i++)$add('/ip firewall mangle',['action'=>'mark-routing','chain'=>'output','connection-mark'=>'ISP'.$i.'-conn','new-routing-mark'=>'to-ISP'.$i,'passthrough'=>'no']);
        $target=match($data['local-target-type']){'IP Address List'=>['dst-address-list'=>'!LOCAL-IP','src-address-list'=>'LOCAL-IP'],'In. Interface'=>['in-interface'=>$data['interface-target']],'In. Interface List'=>['in-interface-list'=>$data['interface-target']]};
        $weights=[1,1];if($data['bandwidth-ratio-checkbox']){$gcd=$this->gcd((int)$data['speed-isp-1'],(int)$data['speed-isp-2']);$weights=[(int)($data['speed-isp-1']/$gcd),(int)($data['speed-isp-2']/$gcd)];}
        $remainder=0;$denominator=array_sum($weights);
        foreach($weights as $idx=>$weight)for($j=0;$j<$weight;$j++)$add('/ip firewall mangle',['action'=>'mark-connection','chain'=>'prerouting','connection-state'=>'new','connection-mark'=>'no-mark','new-connection-mark'=>'ISP'.($idx+1).'-conn','passthrough'=>'yes','per-connection-classifier'=>$data['pccType'].':'.$denominator.'/'.$remainder++,'dst-address-type'=>'!local',...$target]);
        for($i=1;$i<=2;$i++)$add('/ip firewall mangle',['action'=>'mark-routing','chain'=>'prerouting','connection-mark'=>'ISP'.$i.'-conn','new-routing-mark'=>'to-ISP'.$i,'passthrough'=>'yes',...$target]);
        return $entries;
    }
    public function generate(array $input): GeneratedScript
    {
        $data=$this->validate($input);
        return new GeneratedScript([['title'=>'PCC — DRAFT / LAB UNVERIFIED','script'=>RouterOs::render($this->configuration($data))]],$data['routeros-version']==='v7.xx'?'7':'6','0.1.0-draft',['Hanya lima skenario dua WAN yang dibandingkan. Pengujian RouterOS belum dilakukan.','Komentar dan formatting berbeda dari referensi; tidak diklaim exact match.']);
    }
    private function gcd(int $a,int $b): int { while($b!==0){[$a,$b]=[$b,$a%$b];}return $a; }
}
