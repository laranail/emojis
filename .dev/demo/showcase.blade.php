<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>laranail/emojis — Emoji picker showcase</title>
<meta name="description" content="Every feature of the laranail/emojis picker, live: popover, inline, placement, image sets, kaomoji, symbols, autocomplete and skin tones.">
<x-laranail-emojis::styles picker />
<style>
  :root {
    --bg: #f6f7fb;
    --surface: #ffffff;
    --surface-2: #f1f3f8;
    --text: #111827;
    --muted: #5b6475;
    --border: #e3e6ee;
    --accent: #5b5bd6;
    --accent-soft: #ecebff;
    --bubble-me: #5b5bd6;
    --bubble-me-text: #ffffff;
    --code-bg: #0f172a;
    --code-text: #e2e8f0;
    --shadow: 0 1px 2px rgb(16 24 40 / 0.04), 0 8px 24px rgb(16 24 40 / 0.06);
    --radius: 16px;
    color-scheme: light;
  }

  :root[data-theme='dark'] {
    --bg: #0b0d12;
    --surface: #141821;
    --surface-2: #1b202b;
    --text: #e8ebf2;
    --muted: #98a2b3;
    --border: #262c38;
    --accent: #8b8bff;
    --accent-soft: #23234a;
    --bubble-me: #6d6df0;
    --code-bg: #0a0d14;
    --shadow: 0 1px 2px rgb(0 0 0 / 0.3), 0 8px 24px rgb(0 0 0 / 0.35);
    color-scheme: dark;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font: 15px/1.55 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
  }

  a { color: var(--accent); }

  code, pre, kbd { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

  .wrap { max-width: 1120px; margin: 0 auto; padding: 0 20px; }

  /* Top bar */
  .bar {
    position: sticky;
    top: 0;
    z-index: 10;
    background: color-mix(in srgb, var(--bg) 82%, transparent);
    backdrop-filter: saturate(1.4) blur(10px);
    border-bottom: 1px solid var(--border);
  }

  .bar .wrap { display: flex; align-items: center; gap: 16px; height: 60px; }

  .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; letter-spacing: -0.01em; white-space: nowrap; }

  .mark {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: var(--accent);
    color: #fff;
    font-size: 18px;
  }

  .nav { display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; flex: 1; }

  .nav a {
    padding: 6px 10px;
    border-radius: 8px;
    color: var(--muted);
    font-size: 13.5px;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
  }

  .nav a:hover { background: var(--surface-2); color: var(--text); }

  .seg { display: inline-flex; padding: 3px; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--border); }

  .seg button {
    border: 0;
    padding: 5px 11px;
    border-radius: 999px;
    background: transparent;
    color: var(--muted);
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
  }

  .seg button[aria-pressed='true'] { background: var(--surface); color: var(--text); box-shadow: 0 1px 2px rgb(0 0 0 / 0.12); }

  /* Hero */
  .hero { padding: 56px 0 28px; display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 40px; align-items: center; }

  .eyebrow { display: inline-block; padding: 4px 10px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); font-size: 12.5px; font-weight: 700; letter-spacing: 0.02em; }

  h1 { font-size: clamp(32px, 5vw, 48px); line-height: 1.08; letter-spacing: -0.03em; margin: 14px 0 12px; }

  .lede { color: var(--muted); font-size: 17px; max-width: 54ch; margin: 0 0 22px; }

  .install { display: flex; align-items: center; gap: 8px; max-width: 460px; padding: 10px 10px 10px 16px; border-radius: 12px; border: 1px solid var(--border); background: var(--code-bg); color: var(--code-text); }

  .install code { flex: 1; font-size: 14px; overflow-x: auto; white-space: nowrap; }

  .hero-demo { display: grid; gap: 10px; padding: 18px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); }

  .hero-demo .laranail-emoji-picker-panel { box-shadow: none; }

  .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }

  .chip { padding: 5px 10px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--muted); font-size: 12.5px; font-weight: 500; }

  /* Cards */
  section { scroll-margin-top: 76px; }

  .card {
    margin: 22px 0;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: clip;
    overflow-clip-margin: 0;
  }

  .card-head { padding: 22px 24px 0; }

  .card h2 { margin: 0 0 6px; font-size: 20px; letter-spacing: -0.015em; }

  .card p.desc { margin: 0; color: var(--muted); max-width: 72ch; }

  .card-body { padding: 20px 24px 24px; }

  .split { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }

  details.code { border-top: 1px solid var(--border); }

  details.code summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 24px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    list-style: none;
  }

  details.code summary::-webkit-details-marker { display: none; }

  details.code summary::after { content: 'Show'; color: var(--accent); }

  details.code[open] summary::after { content: 'Hide'; }

  .snippet { position: relative; margin: 0 24px 20px; }

  .snippet pre {
    margin: 0;
    padding: 16px 18px;
    border-radius: 12px;
    background: var(--code-bg);
    color: var(--code-text);
    font-size: 13px;
    line-height: 1.6;
    overflow-x: auto;
  }

  .copy {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 4px 10px;
    border: 1px solid rgb(255 255 255 / 0.15);
    border-radius: 8px;
    background: rgb(255 255 255 / 0.06);
    color: var(--code-text);
    font: inherit;
    font-size: 12px;
    cursor: pointer;
  }

  .copy:hover { background: rgb(255 255 255 / 0.12); }

  /* Form controls */
  label.field-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--muted); }

  .input,
  .textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font: inherit;
    font-size: 16px; /* 16px keeps iOS from zooming into the field */
    transition: border-color 120ms, box-shadow 120ms;
  }

  .textarea { resize: vertical; min-height: 96px; }

  .input:focus,
  .textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent); }

  .hint { margin-top: 8px; color: var(--muted); font-size: 13px; }

  kbd { padding: 1px 6px; border: 1px solid var(--border); border-bottom-width: 2px; border-radius: 6px; background: var(--surface-2); font-size: 12px; }

  /* Field with the trigger inside it, the way most apps place it */
  .with-trigger { position: relative; }

  .with-trigger .input { padding-right: 52px; }

  /* No transform here: a transformed ancestor would trap a fixed-position popover in old browsers. */
  .with-trigger .laranail-emoji-picker { position: absolute; top: 0; bottom: 0; right: 8px; display: flex; align-items: center; }

  /* Chat */
  .chat { display: flex; flex-direction: column; height: 420px; border: 1px solid var(--border); border-radius: 14px; background: var(--surface-2); overflow: hidden; }

  .chat-head { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--border); background: var(--surface); }

  .avatar { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: var(--accent-soft); font-size: 18px; }

  .chat-head strong { display: block; font-size: 14px; }

  .chat-head span { color: var(--muted); font-size: 12.5px; }

  .messages { flex: 1; display: flex; flex-direction: column; gap: 8px; padding: 16px; overflow-y: auto; }

  .msg { max-width: 78%; padding: 8px 12px; border-radius: 16px; background: var(--surface); border: 1px solid var(--border); word-wrap: break-word; }

  .msg.me { align-self: flex-end; background: var(--bubble-me); border-color: transparent; color: var(--bubble-me-text); border-bottom-right-radius: 6px; }

  .msg.them { align-self: flex-start; border-bottom-left-radius: 6px; }

  .composer { display: flex; align-items: flex-end; gap: 8px; padding: 10px; border-top: 1px solid var(--border); background: var(--surface); }

  .composer textarea {
    flex: 1;
    min-height: 42px;
    max-height: 140px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface-2);
    color: var(--text);
    font: inherit;
    font-size: 16px;
    resize: none;
  }

  .composer textarea:focus { outline: none; border-color: var(--accent); }

  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 42px; padding: 0 16px; border: 0; border-radius: 12px; background: var(--accent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }

  .btn:hover { filter: brightness(1.08); }

  /* Placement stage */
  .stage {
    position: relative;
    height: 360px;
    border: 1px dashed var(--border);
    border-radius: 14px;
    background:
      radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--muted) 28%, transparent) 1px, transparent 0) 0 0 / 18px 18px,
      var(--surface-2);
  }

  .spot { position: absolute; display: flex; flex-direction: column; align-items: center; gap: 4px; }

  .stage .laranail-emoji-picker-trigger { background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow); }

  .spot small { color: var(--muted); font-size: 11.5px; font-weight: 600; white-space: nowrap; }

  .spot.tl { top: 14px; left: 14px; align-items: flex-start; }
  .spot.tr { top: 14px; right: 14px; align-items: flex-end; }
  .spot.c { top: 50%; left: 50%; transform: translate(-50%, -50%); }
  .spot.bl { bottom: 14px; left: 14px; align-items: flex-start; flex-direction: column-reverse; }
  .spot.br { bottom: 14px; right: 14px; align-items: flex-end; flex-direction: column-reverse; }

  /* Rendering table */
  .sets { display: grid; grid-template-columns: 120px repeat(var(--n), 1fr); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }

  .sets > div { padding: 12px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

  .sets > div:nth-last-child(-n + 1) { border-bottom: 0; }

  .sets .name { font-weight: 600; font-size: 13px; color: var(--muted); background: var(--surface-2); }

  .sets .row-emoji { font-size: 28px; --laranail-emoji-image-size: 28px; }

  .grid-features { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; }

  .feature { padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-2); }

  .feature b { display: block; margin-bottom: 2px; font-size: 14px; }

  .feature span { color: var(--muted); font-size: 13px; }

  /* Event log */
  .log { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; min-height: 120px; }

  .log li { display: grid; grid-template-columns: 40px 1fr auto; align-items: center; gap: 10px; padding: 8px 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-2); animation: pop 180ms ease-out; }

  .log .glyph { font-size: 24px; text-align: center; }

  .log .meta { min-width: 0; }

  .log .meta b { display: block; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  .log .meta code { color: var(--muted); font-size: 12px; }

  .log .where { color: var(--muted); font-size: 12px; white-space: nowrap; }

  .log .empty { display: block; padding: 28px; text-align: center; color: var(--muted); border-style: dashed; grid-template-columns: none; }

  @keyframes pop { from { transform: translateY(-4px); opacity: 0; } }

  footer { padding: 32px 0 56px; color: var(--muted); font-size: 13px; text-align: center; }

  @media (max-width: 860px) {
    .hero { grid-template-columns: 1fr; padding-top: 36px; gap: 24px; }
    .split { grid-template-columns: 1fr; }
    .nav { display: none; }
    .bar .wrap { justify-content: space-between; }
  }

  @media (max-width: 560px) {
    .wrap { padding: 0 14px; }
    .card-head { padding: 18px 16px 0; }
    .card-body { padding: 16px; }
    details.code summary { padding: 12px 16px; }
    .snippet { margin: 0 16px 16px; }
    .sets { grid-template-columns: 1fr; }
    .sets .name { border-bottom: 0; padding-bottom: 0; background: transparent; }
    .stage { height: 320px; }
    .chat { height: 380px; }
  }

  @media (prefers-reduced-motion: reduce) {
    .log li { animation: none; }
  }
</style>
</head>
<body>

<header class="bar">
  <div class="wrap">
    <div class="brand"><span class="mark" aria-hidden="true">☺</span>laranail/emojis</div>
    <nav class="nav" aria-label="Sections">
      <a href="#composer">Composer</a>
      <a href="#inline">Inline</a>
      <a href="#placement">Placement</a>
      <a href="#sets">Image sets</a>
      <a href="#more">Kaomoji &amp; symbols</a>
      <a href="#autocomplete">Autocomplete</a>
      <a href="#events">Events</a>
      <a href="#config">Config</a>
    </nav>
    <div class="seg" role="group" aria-label="Page theme">
      <button type="button" data-theme-choice="light" aria-pressed="true">Light</button>
      <button type="button" data-theme-choice="dark" aria-pressed="false">Dark</button>
    </div>
  </div>
</header>

<main class="wrap">
  <div class="hero">
    <div>
      <span class="eyebrow">Blade · Livewire · React · vanilla JS</span>
      <h1>An emoji picker that fits your Laravel app.</h1>
      <p class="lede">Every Unicode emoji, plus kaomoji, symbols and your own custom emoji, in a picker that positions itself like a Bootstrap popover, turns into a bottom sheet on phones, and draws with the device's emoji or Twemoji, Noto and OpenMoji.</p>
      <div class="install">
        <code>composer require laranail/emojis</code>
        <button type="button" class="copy" style="position:static" data-copy="composer require laranail/emojis">Copy</button>
      </div>
      <div class="chips">
        <span class="chip">Full Unicode catalogue</span>
        <span class="chip">Skin tones per person</span>
        <span class="chip">Keyboard &amp; screen reader</span>
        <span class="chip">CSP-safe</span>
        <span class="chip">24 languages</span>
      </div>
    </div>
    <div>
      <div class="hero-demo">
        <label class="field-label" for="hero-field">Try it: pick, search, or right-click a 👋 for its tones</label>
        <input id="hero-field" class="input" placeholder="Your picks land here…" autocomplete="off">
        <x-laranail-emojis::picker target="#hero-field" inline :columns="8" />
      </div>
    </div>
  </div>

  <section id="composer" class="card">
    <div class="card-head">
      <h2>Chat composer</h2>
      <p class="desc">The popover opens beside its trigger, inserts at the text caret, and keeps a Frequently used row. Press Enter to send.</p>
    </div>
    <div class="card-body">
      <div class="chat">
        <div class="chat-head"><span class="avatar" aria-hidden="true">🦊</span><div><strong>Ada</strong><span>online</span></div></div>
        <div class="messages" id="messages" aria-live="polite">
          <div class="msg them">Did the picker ship? 👀</div>
          <div class="msg me">It did. Try the tabs at the top 🎉</div>
        </div>
        <form class="composer" id="composer-form">
          <x-laranail-emojis::picker target="#composer-input" />
          <textarea id="composer-input" rows="1" placeholder="Message Ada" aria-label="Message"></textarea>
          <button type="submit" class="btn">Send</button>
        </form>
      </div>
    </div>
    <details class="code">
      <summary>Blade</summary>
      <div class="snippet"><pre><code>&lt;textarea id="message"&gt;&lt;/textarea&gt;
&lt;x-laranail-emojis::picker target="#message" /&gt;

{{-- once per page --}}
&lt;x-laranail-emojis::styles picker /&gt;
&lt;x-laranail-emojis::scripts /&gt;</code></pre></div>
    </details>
  </section>

  <section id="inline" class="card">
    <div class="card-head">
      <h2>Inline</h2>
      <p class="desc">Embedded in the page instead of floating: for a reaction bar, a status editor or a sidebar.</p>
    </div>
    <div class="card-body split">
      <div>
        <label class="field-label" for="status">Your status</label>
        <textarea id="status" class="textarea" placeholder="Pick an emoji, then say what you're up to"></textarea>
        <p class="hint">Right-click or long-press 🤝, 👫 and other people emoji to give each person a tone.</p>
      </div>
      <x-laranail-emojis::picker target="#status" inline :columns="8" />
    </div>
    <details class="code">
      <summary>Blade</summary>
      <div class="snippet"><pre><code>&lt;x-laranail-emojis::picker target="#status" inline :columns="8" /&gt;</code></pre></div>
    </details>
  </section>

  <section id="placement" class="card">
    <div class="card-head">
      <h2>Placement</h2>
      <p class="desc">Like Bootstrap 5's popover: it prefers a side, flips when there is no room, shifts to stay on screen, and the arrow follows the trigger. Open each corner to see it. Below 640px it becomes a bottom sheet.</p>
    </div>
    <div class="card-body">
      <div class="stage">
        <div class="spot tl"><x-laranail-emojis::picker placement="bottom-start" /><small>bottom-start</small></div>
        <div class="spot tr"><x-laranail-emojis::picker placement="bottom-end" /><small>bottom-end</small></div>
        <div class="spot c"><x-laranail-emojis::picker placement="end" /><small>end (flips when the right side is short)</small></div>
        <div class="spot bl"><x-laranail-emojis::picker placement="auto" /><small>auto</small></div>
        <div class="spot br"><x-laranail-emojis::picker placement="top-end" /><small>top-end</small></div>
      </div>
      <p class="hint">These pickers have no target field: they only fire the select event, logged in <a href="#events">Events</a>.</p>
    </div>
    <details class="code">
      <summary>Blade</summary>
      <div class="snippet"><pre><code>&lt;x-laranail-emojis::picker placement="top-end" :offset="8" :arrow="true" /&gt;

{{-- or for every picker, in config/laranail/emojis.php --}}
'placement' =&gt; 'auto', // auto | top | bottom | start | end, optionally -start / -end
'sheet_breakpoint' =&gt; 640,</code></pre></div>
    </details>
  </section>

  <section id="sets" class="card">
    <div class="card-head">
      <h2>Image sets</h2>
      <p class="desc">Draw with the device's own emoji, or every emoji from an image set. With <code>set_switcher</code> on, people choose their style in the picker's settings (the gear). In auto mode, emoji the device cannot draw fall back to images.</p>
    </div>
    <div class="card-body">
      <div class="sets" style="--n: 1">
        <div class="name">Native</div>
        <div class="row-emoji">
          @foreach (['grinning', 'wave', 'rocket', 'tada', 'fire', 'sparkles'] as $name)<x-laranail-emojis::emoji :name="$name" />@endforeach
        </div>
        @foreach ($sets as $set)
          <div class="name">{{ ['twemoji' => 'Twemoji', 'noto' => 'Noto', 'openmoji' => 'OpenMoji'][$set] ?? $set }}</div>
          <div class="row-emoji">
            @foreach (['grinning', 'wave', 'rocket', 'tada', 'fire', 'sparkles'] as $name)<x-laranail-emojis::emoji :name="$name" mode="image" :set="$set" />@endforeach
          </div>
        @endforeach
      </div>
      <div class="split" style="margin-top:20px">
        <div>
          <label class="field-label" for="images-field">A picker drawn entirely from images</label>
          <div class="with-trigger">
            <input id="images-field" class="input" placeholder="render=&quot;image&quot;" autocomplete="off">
            <x-laranail-emojis::picker target="#images-field" render="image" />
          </div>
        </div>
        <p class="hint" style="margin:28px 0 0">Open it, then the gear: <b>Emoji style</b> switches between Native, Twemoji, Noto and OpenMoji, and remembers the choice.</p>
      </div>
    </div>
    <details class="code">
      <summary>Blade</summary>
      <div class="snippet"><pre><code>&lt;x-laranail-emojis::picker target="#field" render="image" /&gt;
&lt;x-laranail-emojis::emoji name="rocket" mode="image" set="noto" /&gt;</code></pre></div>
    </details>
  </section>

  <section id="more" class="card">
    <div class="card-head">
      <h2>Kaomoji, symbols and tabs</h2>
      <p class="desc">Turn on <code>kaomoji</code> and <code>symbols</code> and the picker gains Emoji, Kaomoji and Symbols tabs: about 2,100 kaomoji in named groups and 7,300 symbols (arrows, currency, maths, letters). Search covers each tab.</p>
    </div>
    <div class="card-body split">
      <div>
        <label class="field-label" for="kao-field">Signature</label>
        <div class="with-trigger">
          <input id="kao-field" class="input" placeholder="¯\_(ツ)_/¯" autocomplete="off">
          <x-laranail-emojis::picker target="#kao-field" />
        </div>
        <p class="hint">Open it and choose <b>Kaomoji</b> or <b>Symbols</b> at the top. Search "arrow", "euro" or "shrug".</p>
      </div>
      <div class="grid-features">
        <div class="feature"><b>Recents</b><span>Frequently used, by count or recency</span></div>
        <div class="feature"><b>Skin tones</b><span>A default tone, or one per person</span></div>
        <div class="feature"><b>Preview</b><span>The hovered emoji with its :shortcode:</span></div>
        <div class="feature"><b>Custom emoji</b><span>Your own images, beside Unicode</span></div>
      </div>
    </div>
  </section>

  <section id="autocomplete" class="card">
    <div class="card-head">
      <h2>:shortcode autocomplete</h2>
      <p class="desc">Type a colon and a few letters; suggestions open at the text caret. Arrow keys choose, Enter or Tab inserts.</p>
    </div>
    <div class="card-body split">
      <div>
        <label class="field-label" for="auto-field">Comment</label>
        <textarea id="auto-field" class="textarea" placeholder="Type :hea or :roc"></textarea>
        <x-laranail-emojis::picker target="#auto-field" :features="['autocomplete' => true]" />
      </div>
      <div class="grid-features">
        <div class="feature"><b><kbd>/</kbd></b><span>Focus search</span></div>
        <div class="feature"><b><kbd>Alt</kbd> + <kbd>1</kbd>–<kbd>9</kbd></b><span>Jump to a category</span></div>
        <div class="feature"><b><kbd>?</kbd></b><span>Every shortcut</span></div>
        <div class="feature"><b><kbd>Esc</kbd></b><span>Close, focus back to the field</span></div>
      </div>
    </div>
    <details class="code">
      <summary>Blade</summary>
      <div class="snippet"><pre><code>&lt;x-laranail-emojis::picker target="#comment" :features="['autocomplete' =&gt; true]" /&gt;</code></pre></div>
    </details>
  </section>

  <section id="events" class="card">
    <div class="card-head">
      <h2>Events</h2>
      <p class="desc">Every pick fires a bubbling <code>laranail-emoji:select</code> event with the emoji, its name, shortcode and hexcode. Picks from anywhere on this page land here.</p>
    </div>
    <div class="card-body split">
      <ul class="log" id="log"><li class="empty">Pick an emoji anywhere on the page.</li></ul>
      <div class="snippet" style="margin:0"><pre><code>document.addEventListener('laranail-emoji:select', (event) =&gt; {
  const { emoji, name, shortcode, hexcode } = event.detail;
  console.log(emoji, name, shortcode, hexcode);
});</code></pre></div>
    </div>
  </section>

  <section id="config" class="card">
    <div class="card-head">
      <h2>Configuration</h2>
      <p class="desc">Each part of the picker can be switched off, in <code>config/laranail/emojis.php</code> or per picker with <code>:features</code>.</p>
    </div>
    <div class="snippet" style="margin-top:16px"><pre><code>'picker' =&gt; [
    'features' =&gt; [
        'search' =&gt; true,  'recents' =&gt; true,     'skin_tones' =&gt; true,
        'per_person_tones' =&gt; true, 'preview' =&gt; true, 'category_tabs' =&gt; true,
        'custom' =&gt; true,  'kaomoji' =&gt; false,    'symbols' =&gt; false,
        'set_switcher' =&gt; false, 'settings' =&gt; true, 'autocomplete' =&gt; false,
    ],
    'placement' =&gt; 'auto', 'offset' =&gt; 8, 'arrow' =&gt; true, 'sheet_breakpoint' =&gt; 640,
    'render' =&gt; 'auto', // auto | native | image
    'theme' =&gt; 'auto',  // auto | light | dark
],</code></pre></div>
  </section>
</main>

<footer>
  <div class="wrap">laranail/emojis · MIT · Simtabi LLC · <a href="https://github.com/laranail/emojis">GitHub</a></div>
</footer>

<x-laranail-emojis::scripts />
<script>
  (() => {
    const root = document.documentElement;
    const read = () => { try { return localStorage.getItem('showcase-theme'); } catch { return null; } };
    const save = (value) => { try { localStorage.setItem('showcase-theme', value); } catch { /* private mode */ } };
    const apply = (theme) => {
      root.dataset.theme = theme;
      for (const button of document.querySelectorAll('[data-theme-choice]')) {
        button.setAttribute('aria-pressed', String(button.dataset.themeChoice === theme));
      }
    };

    apply(read() ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
    document.querySelectorAll('[data-theme-choice]').forEach((button) => button.addEventListener('click', () => {
      apply(button.dataset.themeChoice);
      save(button.dataset.themeChoice);
    }));

    // Copy buttons: one on the install line, one added to every snippet.
    for (const pre of document.querySelectorAll('.snippet pre')) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'copy';
      button.textContent = 'Copy';
      button.dataset.copy = pre.textContent;
      pre.parentElement.append(button);
    }

    document.addEventListener('click', async (event) => {
      const button = event.target.closest?.('[data-copy]');
      if (!button) return;
      try {
        await navigator.clipboard.writeText(button.dataset.copy);
        button.textContent = 'Copied';
      } catch {
        button.textContent = 'Press ⌘C';
      }
      setTimeout(() => { button.textContent = 'Copy'; }, 1400);
    });

    // The chat: Enter sends, Shift+Enter is a new line.
    const form = document.getElementById('composer-form');
    const input = document.getElementById('composer-input');
    const messages = document.getElementById('messages');
    const send = () => {
      const text = input.value.trim();
      if (!text) return;
      const bubble = document.createElement('div');
      bubble.className = 'msg me';
      bubble.textContent = text;
      messages.append(bubble);
      messages.scrollTop = messages.scrollHeight;
      input.value = '';
    };
    form.addEventListener('submit', (event) => { event.preventDefault(); send(); });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); send(); }
    });

    // The event log.
    const log = document.getElementById('log');
    document.addEventListener('laranail-emoji:select', (event) => {
      const { emoji, name, shortcode, hexcode } = event.detail ?? {};
      const section = event.target.closest?.('section, .hero');
      const where = section?.querySelector('h2')?.textContent ?? 'Hero';
      log.querySelector('.empty')?.remove();
      const item = document.createElement('li');
      const glyph = document.createElement('span');
      glyph.className = 'glyph';
      glyph.textContent = emoji ?? '';
      const meta = document.createElement('div');
      meta.className = 'meta';
      const title = document.createElement('b');
      title.textContent = name || emoji || '';
      const code = document.createElement('code');
      code.textContent = [shortcode ? `:${shortcode}:` : null, hexcode].filter(Boolean).join(' · ');
      meta.append(title, code);
      const origin = document.createElement('span');
      origin.className = 'where';
      origin.textContent = where;
      item.append(glyph, meta, origin);
      log.prepend(item);
      while (log.children.length > 6) log.lastElementChild.remove();
    });
  })();
</script>
</body>
</html>
