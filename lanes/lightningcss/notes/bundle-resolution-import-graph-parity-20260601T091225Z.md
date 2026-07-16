# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01T09:12Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T091225Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native oracle: `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Targeted upstream NAPI probe with `bundleAsync({ minify: true })` confirmed literal null bytes in both quoted import strings and unquoted `url(...)` import sources are tokenized as U+FFFD before `resolver.resolve()`:
  - `@import "pkg:\0.css"` resolves `pkg:U+FFFD.css` as UTF-8 hex `706b673aefbfbd2e637373`.
  - `@import url(pkg:\0.css)` resolves the same replacement-character path rather than rejecting the URL token.

## Native Delta

- `CssBundler::decodeCssEscapes()` now replaces literal null bytes with U+FFFD while decoding import source tokens, matching upstream tokenizer behavior.
- Unquoted `url(...)` import-source validation no longer rejects literal null bytes before decoding, so the resolver receives the upstream replacement-character specifier.
- `wordpress-bundle-import-graph.php --self-test` now covers block-theme package imports with literal null bytes in quoted and unquoted sources.

## Evidence

- Red-first PHP probe before the patch: `@import "pkg:\0.css"` called the resolver with `pkg:\0.css` (`706b673a002e637373`), and `@import url(pkg:\0.css)` was rejected before resolver traversal.
- `php -l lanes/lightningcss/src/CssBundler.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 651 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed, including `null-byte-import-source: resolved`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 7077 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused `CssBundlerTest.php` assertions moved `649 -> 651`.
- Full LightningCSS PHP evidence moved `7075 -> 7077`.
- Conservative mapped coverage remains `2365 / 3532`; this deepens the already represented bundle/import graph source-token decoding cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CssBundler`, CSS token decoding, resolver callback boundary, `CssBundleException` model, and existing WordPress bundle import-graph smoke. It does not require Node, Rust, WASM, browser services, external package resolution, or credentialed providers at runtime.

## Non-Overlap

This does not repeat accepted surrogate escape handling, CRLF hex escapes, escaped import delimiters, invalid import source diagnostics, import media/supports/layer parsing, external import ordering, CSS Modules dependency graph handling, source-map remapping, media-query, CSSOM, target-prefixing, property-value, or custom-at-rule clusters. The patch is limited to literal null-byte replacement inside bundle import source resolution.

## Follow-Up

Remaining bundle/import graph parity work includes resolver diagnostic ordering edges that do not depend on upstream parallel callback order and any source-map remapping gaps through CSS Modules dependency imports not already covered by the current PHP tests.
