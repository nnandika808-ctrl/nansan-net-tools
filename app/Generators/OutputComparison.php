<?php
namespace App\Generators;
final class OutputComparison
{
    public static function exact(string $expected, string $actual): array
    {
        $before=explode("\n",$expected); $after=explode("\n",$actual); $diff=[];
        for($i=0;$i<max(count($before),count($after));$i++) if(($before[$i]??null)!==($after[$i]??null)) $diff[]=['line'=>$i+1,'expected'=>$before[$i]??null,'actual'=>$after[$i]??null];
        return ['matched'=>hash_equals($expected,$actual),'expected_lines'=>count($before),'actual_lines'=>count($after),'diff'=>$diff];
    }
    public static function projection(array $expected, array $actual): array
    {
        $diff=[]; foreach($expected as $key=>$value) if(!array_key_exists($key,$actual)||$actual[$key]!==$value) $diff[$key]=['expected'=>$value,'actual'=>$actual[$key]??null];
        return ['projection_matched'=>$diff===[],'diff'=>$diff,'scope'=>array_keys($expected)];
    }
}
