# HTTP API

Nine read-only JSON endpoints over the emoji catalogue, symbols, kaomoji, emoticons and status tags, for a
picker or another service. Off by default.

## Enable it

```dotenv
LARANAIL_EMOJIS_API=true
```

Off means the routes are never registered — not registered and blocked — so a disabled API is absent from
`route:list` and cannot be exposed by loosening middleware later. The routes are registered at boot from
controllers, so `route:cache` keeps them.

| Key | Default | Effect |
|---|---|---|
| `api.enabled` | `false` | Registers the routes. |
| `api.prefix` | `'laranail/emojis/api'` | URL prefix. |
| `api.version` | `'v1'` | Appended to the prefix. |
| `api.middleware` | `['api', 'throttle:120,1']` | Your stack. Put authentication here if the data is not public to you. |
| `api.cache_headers` | `'public;max_age=3600;etag'` | Laravel's `cache.headers`: an ETag, and 304 for `If-None-Match`. Use `private;…` behind auth, or `''` for none. |

## Endpoints

Paths are relative to `/laranail/emojis/api/v1`. Every one is a `GET` (and `HEAD`), named
`laranail.emojis.api.*`.

| Path | Returns |
|---|---|
| `/` | dataset version, counts, locales, groups and links |
| `/emojis` | a page of emoji: `q`, `group`, `subgroup`, `version` (only emoji that version can show), `tones`, `locale`, `limit` (1–250, default 50), `offset`; `meta.total` |
| `/emojis/{key}` | one emoji, by character, hexcode, shortcode or name — percent-encode it (`%F0%9F%9A%80`, `%23%EF%B8%8F%E2%83%A3` for `#️⃣`) |
| `/picker` | everything an emoji picker draws for one locale, grouped, with custom emoji |
| `/symbols`, `/symbols/{group}` | the symbol groups, or every symbol in one |
| `/kaomoji` | kaomoji, optionally one `group`, optionally `ascii` only |
| `/emoticons` | every emoticon, mapped to its emoji's hexcode; `meta.opt_in_only` lists the risky ones |
| `/tags` | the [status tags](tags.md) |

```bash
curl -s 'https://example.com/laranail/emojis/api/v1/emojis?q=fus%C3%A9e&locale=fr&limit=1'
# {"data":[{"emoji":"🚀","hexcode":"1F680","name":"fusée","english_name":"rocket",…}],"meta":{"total":1,"limit":1,"offset":0}}
```

The full contract is `resources/openapi/emojis-api.yaml` (OpenAPI 3.1), which ships with the package; a test
fails when a route is missing from it.

## Responses

- Success is `{"data": …, "meta": …}`. A key, symbol group or kaomoji group that matches nothing is a 404
  with a `message`, never an empty 200 that reads as a match with no fields.
- Out-of-bounds input is a 422 naming the field. The message never repeats what was sent.
- `locale` is checked for shape only. An unshipped locale falls back (`pt-BR` → `pt` → `en`) rather than
  failing, and a missing one follows the application locale.
- Every response carries `Vary: Accept-Language`, because a request without `locale` follows the
  application locale, which a middleware commonly sets from that header.

## Security

- **Read-only.** Nothing here writes, and every input is bounded: no query asks for more than one page.
- **The emoji policy applies.** An emoji `policy` denies is neither listed nor shown, and `/picker` leaves
  out emoji newer than `policy.max_version`, and custom emoji when `allow_custom` is off. Everything is
  built by `Core\Picker\PayloadBuilder`, which the Blade picker shares.
- **Throttled per client IP** by default. Behind a proxy or load balancer, configure Laravel's
  `TrustProxies`, or every client shares the proxy's address and its limit.
- **No cross-origin access by default.** The prefix sits outside `api/*`, so Laravel's CORS defaults do not
  apply. To allow other origins, add the prefix to `config/cors.php`:

  ```php
  'paths' => ['api/*', 'laranail/emojis/api/*'],
  'allowed_origins' => ['https://app.example.com'],
  ```

---

[← Docs index](../../README.md#documentation)
