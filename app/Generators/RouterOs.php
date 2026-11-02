<?php
namespace App\Generators;
use InvalidArgumentException;
final class RouterOs
{
    public static function quote(string $value): string
    {
        if(preg_match('/[\x00-\x1f\x7f]/',$value)) throw new InvalidArgumentException('Control characters are not allowed.');
        return '"'.strtr($value,['\\'=>'\\\\','"'=>'\\"','$'=>'\\$']).'"';
    }
    public static function render(array $entries): string
    {
        $lines=['# NANSAN NET Tools — independent implementation','# DRAFT: full traffic and failover verification is pending.'];$path=null;
        foreach($entries as $entry){
            if($path!==$entry['path']) { $path=$entry['path']; $lines[]=$path; }
            $parts=[$entry['operation']];
            foreach($entry['fields'] as $key=>$value) $parts[]=$value===true?$key:$key.'='.self::quote((string)$value);
            $lines[]=implode(' ',$parts);
        }
        return implode("\n",$lines)."\n";
    }
    public static function ipv4Subnet(string $value): bool
    {
        $parts=explode('/',$value);return count($parts)<=2&&filter_var($parts[0],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false&&(!isset($parts[1])||(ctype_digit($parts[1])&&(int)$parts[1]<=32));
    }
    public static function gateway(string $value): bool
    {
        $parts=explode('%',$value);
        if(count($parts)===2) return filter_var($parts[0],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false&&self::interfaceName($parts[1]);
        return count($parts)===1&&(filter_var($value,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false||self::interfaceName($value));
    }
    public static function interfaceName(string $value): bool { return preg_match('/^[a-zA-Z][a-zA-Z0-9_. -]{0,63}$/D',$value)===1; }
}
