<?php
declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:3|max:200',
            'description' => 'required|string',
            'author_email' => 'required|email|max:255',
        ];
    }
}
