<?php

declare(strict_types=1);

namespace App\Modules\Auction\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'starting_bid' => ['required', 'numeric', 'gt:0'],
            'minimum_increment' => ['required', 'numeric', 'gt:0'],
            'buy_now_price' => ['nullable', 'numeric', 'gt:starting_bid'],
            'reserve_price' => ['nullable', 'numeric', 'gte:starting_bid'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
