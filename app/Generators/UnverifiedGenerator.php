<?php
namespace App\Generators;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;
class UnverifiedGenerator implements GeneratorModule
{
    public function __construct(private string $generatorId) {}
    public function id(): string { return $this->generatorId; }
    public function schema(): array { return json_decode(file_get_contents(resource_path('generators/'.$this->id().'.json')),true,512,JSON_THROW_ON_ERROR); }
    public function validate(array $input): array
    {
        $rules=[];
        foreach ($this->schema()['fields'] as $field) {
            $rules[$field['key']]=match ($field['type']) {
                'select'=>['nullable','string',Rule::in(array_column($field['options'],'value'))],
                'checkbox'=>['boolean'],
                'number'=>['nullable','numeric','min:0','max:10000'],
                default=>['nullable','string','max:500','not_regex:/[\x00-\x1f\x7f]/'],
            };
        }
        Validator::make(['input'=>$input],['input'=>['array:'.implode(',',array_keys($rules))]])->validate();
        return Validator::make($input,$rules)->validate();
    }
    public function generate(array $input): GeneratedScript
    {
        throw new HttpException(423,'Output belum terverifikasi. Generator belum dipublikasikan.');
    }
}
