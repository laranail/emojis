<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Security;

/** What the sanitizer removed, by kind. A report counts these; it never records the text itself. */
enum Threat: string
{
    /** Bytes that are not valid UTF-8, replaced before anything else runs. */
    case InvalidUtf8 = 'invalid_utf8';

    /** C0/C1 control characters other than tab, line feed and carriage return. */
    case Control = 'control';

    /** Bidirectional embeddings, overrides and isolates — the "Trojan Source" reordering attack. */
    case Bidi = 'bidi';

    /** Zero-width and invisible filler characters that carry no visible text. */
    case Invisible = 'invisible';

    /** Unicode tag characters outside a valid emoji tag sequence — invisible "ASCII smuggling". */
    case Tag = 'tag';

    /** Variation selectors beyond the one a character can take — "emoji smuggling" hides bytes in these. */
    case VariationSelector = 'variation_selector';

    /** A zero-width joiner or non-joiner that joins nothing: not inside an emoji, not between letters. */
    case Joiner = 'joiner';

    /** Combining marks past the per-character limit ("Zalgo" text). */
    case Combining = 'combining';

    /** An emoji component with nothing to modify: a lone skin tone, keycap mark or regional indicator. */
    case OrphanComponent = 'orphan_component';

    /** An emoji the policy does not allow. */
    case Policy = 'policy';

    /** An emoji past the policy's maximum count. */
    case Limit = 'limit';
}
