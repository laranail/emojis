<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Requests;

/**
 * The shape a `locale` query parameter must have. Shape only: whether the locale ships is the library's
 * question, and Locales::resolve() answers it with a fallback (pt-BR → pt, xx → en) rather than a 422.
 */
final class LocaleRule
{
    /** @return list<string> */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/'];
    }
}
