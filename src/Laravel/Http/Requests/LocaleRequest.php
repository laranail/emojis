<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** An endpoint whose only input is the locale: describe, picker, one emoji. */
final class LocaleRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['locale' => LocaleRule::rules()];
    }

    public function locale(): ?string
    {
        $locale = $this->validated('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}
