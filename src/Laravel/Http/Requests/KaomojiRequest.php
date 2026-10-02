<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** GET /kaomoji: an optional group slug, and whether to keep only the ASCII ones. */
final class KaomojiRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'group' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'ascii' => ['nullable', 'boolean'],
        ];
    }

    public function group(): ?string
    {
        $group = $this->string('group')->toString();

        return $group === '' ? null : $group;
    }
}
