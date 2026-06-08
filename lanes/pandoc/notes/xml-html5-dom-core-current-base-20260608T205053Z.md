# XML/HTML5 DOM Quote Cite Metadata Slice

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260608T205053Z`
Base accepted HEAD: `760ca6aa9f81ad19edcddbf9a887d409a553e927`

## Behavior

- Added native `Html5DomFragment` handling for `q[cite]` and `blockquote[cite]`.
- Safe quote cite URLs are normalized, resolved against trusted HTML base metadata, and serialized as inert `data-pandoc-quote-cite`.
- Unsafe quote cite schemes and source-owned `data-pandoc-quote-cite` spoofing remain diagnostic-only and are stripped before WordPress raw HTML handoff.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before work began.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1464 assertions, 0 failures`.
- Red-first: same focused command failed before implementation with `1 test files, 1431 assertions, 4 failures` because quote `cite` attributes still serialized as live `cite` URLs.
- Final: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1480 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed.

## Dependency Closure

No new support component is needed. The patch reuses native HTML fragment parsing, URL normalization, safe-fetch filtering, base URL resolution, reserved `data-pandoc-*` stripping, and WordPress raw HTML handoff. No Pandoc, Cabal/Haskell runners, browser renderers, external XML/HTML tools, online sanitizers, online services, live provider tests, or live-service provider tests were run.
