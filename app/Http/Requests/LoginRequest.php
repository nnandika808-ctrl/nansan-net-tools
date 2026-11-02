<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { $this->merge(['username'=>strtolower(trim((string)$this->input('username')))]); }
    public function rules(): array { return ['username'=>['required','string','max:32'],'password'=>['required','string','max:1024'],'remember'=>['boolean']]; }
}
