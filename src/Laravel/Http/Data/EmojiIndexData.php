<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Data;

use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\Subgroup;
use Simtabi\Laranail\Emojis\Core\Catalogue\Query;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;

/** A validated GET /emojis: what to filter on, and which page. Built by EmojiIndexRequest::toData(). */
final readonly class EmojiIndexData
{
    public function __construct(
        public ?string $term = null,
        public ?Group $group = null,
        public ?Subgroup $subgroup = null,
        public ?EmojiVersion $supportedBy = null,
        public bool $skinTones = false,
        public ?string $locale = null,
        public int $limit = 50,
        public int $offset = 0,
    ) {}

    /** The filters, without the page: the policy is applied after them, so the page is cut after it too. */
    public function applyTo(Query $query): Query
    {
        $query = $this->term === null ? $query : $query->search($this->term, $this->locale);
        $query = $this->group instanceof Group ? $query->group($this->group) : $query;
        $query = $this->subgroup instanceof Subgroup ? $query->subgroup($this->subgroup) : $query;
        $query = $this->supportedBy instanceof EmojiVersion ? $query->supportedBy($this->supportedBy) : $query;

        return $query->withSkinToneVariants($this->skinTones);
    }
}
