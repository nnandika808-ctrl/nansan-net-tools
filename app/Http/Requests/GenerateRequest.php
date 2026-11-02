<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class GenerateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->status==='active'; }
    public function rules(): array { return ['input'=>['required','array','max:200'],'save_history'=>['boolean']]; }
}
