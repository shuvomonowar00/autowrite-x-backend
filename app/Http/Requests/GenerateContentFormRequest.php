<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateContentFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keywords' => 'required|string',
            'language' => 'required|string|in:English,Bangla',
            'numFAQs' => 'required|integer',
            'gptVersion' => 'required|string|in:gpt-4,gpt-4-turbo,gpt-3.5-turbo',
            'aiGeneratedTitle' => 'required|bool',
            'wordPressSites' => 'required|array',
            'wordCount' => 'required|integer|min:750|max:1500',
            'articleType' => 'required|string',
        ];
    }
}
