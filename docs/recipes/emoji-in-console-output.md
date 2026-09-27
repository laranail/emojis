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

## Styled, with laranail/console's formatter

With this package installed, `laranail/console` resolves every shortcode in the catalogue, not just its own
map. Its `ConsoleUIFormatter` (console 0.1.4 and later) picks that up with no wiring:

```php
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;

ConsoleUIFormatter::create()
    ->txtColorWhite()->bgColorGreen()->bold()
    ->icon('unicorn')                  // 🦄, a name only this catalogue knows
    ->message('Deployed :rocket:')
    ->padding(1)
    ->write($this->output);
```

On a terminal that cannot draw emoji, the same call prints `:unicorn: Deployed ->`. Console's own map still
wins for the names it defines, such as `cross` (❌ there, ✝️ here). See
[terminal output](../tools/terminal.md#laranailconsole).

---

[← Docs index](../../README.md#documentation)
