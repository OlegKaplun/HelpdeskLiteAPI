<?php
declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'version' => 'required|integer',
            'status' => 'sometimes|in:new,in_progress,done,closed',
            'comment' => 'sometimes|array',
            'comment.author' => 'required_with:comment|string|max:100',
            'comment.message' => 'required_with:comment|string|min:3',
        ];
    }
}
