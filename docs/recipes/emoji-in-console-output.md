# Emoji in console output

Print emoji where the terminal can draw them and readable ASCII everywhere else, including CI logs.

```php
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Facades\Emojis;

$this->line(Emojis::text(':white_check_mark: Deployed :rocket:')->to(Mode::Auto));
// ✅ Deployed 🚀     on a capable terminal
// :white_check_mark: Deployed :rocket:     on the Linux console, legacy Windows, or TERM=dumb
```

Force it either way with `LARANAIL_EMOJIS=1` or `0`. Align columns with `->width()` and cut with
`->truncate()`, which count each emoji as two columns — see [terminal output](../tools/terminal.md).

---

[← Docs index](../../README.md#documentation)
