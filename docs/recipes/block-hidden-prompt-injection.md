# Block hidden prompt injection in user text

Strip instructions hidden in invisible characters before user text reaches an LLM, a reviewer or a log.

```php
use Simtabi\Laranail\Emojis\Facades\Emojis;

$result = Emojis::sanitize($request->input('message'))->run();

if (! $result->report->isClean()) {
    logger()->warning('hidden characters removed from message', $result->report->toArray());   // counts only
}

$prompt = $result->text;
```

This removes ASCII written in Unicode tag characters, bytes smuggled in variation selectors, bidi controls
and zero-width fillers, and keeps every real emoji and every script. To reject instead, validate with
`new NoHiddenCharacters`. See [security and safety](../tools/security.md).

---

[← Docs index](../../README.md#documentation)
