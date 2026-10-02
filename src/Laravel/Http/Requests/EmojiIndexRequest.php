<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\Subgroup;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Laravel\Http\Data\EmojiIndexData;

/** GET /emojis. Every input is bounded, so no query can ask the server for more than one page of work. */
final class EmojiIndexRequest extends FormRequest
{
    public const int MAX_LIMIT = 250;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'q'        => ['nullable', 'string', 'max:100'],
            'group'    => ['nullable', Rule::enum(Group::class)],
            'subgroup' => ['nullable', Rule::enum(Subgroup::class)],
            'version'  => ['nullable', Rule::enum(EmojiVersion::class)],
            'tones'    => ['nullable', 'boolean'],
            'locale'   => LocaleRule::rules(),
            'limit'    => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
            'offset'   => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function toData(): EmojiIndexData
    {
        $term = trim($this->string('q')->toString());
        $locale = $this->string('locale')->toString();

        return new EmojiIndexData(
            term: $term === '' ? null : $term,
            group: $this->enum('group', Group::class),
            subgroup: $this->enum('subgroup', Subgroup::class),
            supportedBy: $this->enum('version', EmojiVersion::class),
            skinTones: $this->boolean('tones'),
            locale: $locale === '' ? null : $locale,
            limit: $this->filled('limit') ? $this->integer('limit') : 50,
            offset: $this->integer('offset'),
        );
    }
}
