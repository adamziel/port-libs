# Bundle Resolution Import Graph Parity - 2026-06-01T08:33Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T083358Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native oracle: `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Upstream NAPI probe with `bundleAsync({ minify: true })` confirmed `@import "pkg:\d800.css"` and `@import url(pkg:icon\dfff.css)` call the resolver with U+FFFD replacement-character paths before reading the resolved files:
  - `pkg:U+FFFD.css` as UTF-8 hex `706b673aefbfbd2e637373`
  - `pkg:iconU+FFFD.css` as UTF-8 hex `706b673a69636f6eefbfbd2e637373`

## Implementation

- `CssBundler::codepointToUtf8()` now treats UTF-16 surrogate code points as invalid CSS escaped code points and replaces them with U+FFFD, matching upstream tokenizer behavior.
- Quoted and unquoted `url()` import source escapes now reach the resolver instead of fataling before graph traversal.
- `wordpress-bundle-import-graph.php --self-test` now covers a block-theme package import containing a surrogate escape and verifies resolver traversal with the replacement-character specifier.

## Evidence

- Red-first PHP probe before the source change: `@import "pkg:\d800.css"` raised `TypeError: PortLibs\LightningCSS\CssBundler::codepointToUtf8(): Return value must be of type string, false returned` before resolver traversal.
- `php -l lanes/lightningcss/src/CssBundler.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 648 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed, including `surrogate-import-escape: resolved`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 6954 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - passed.

## Status Delta

- Focused `CssBundlerTest.php` assertions moved `646 -> 648`.
- Full LightningCSS lane assertions moved `6952 -> 6954`.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the represented bundle/import graph source-token decoding cluster rather than claiming a new denominator row.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CssBundler`, CSS escape decoder, resolver callback boundary, `CssBundleException` model, and existing WordPress bundle import-graph smoke. It does not require Node, Rust, WASM, browser services, or a separate parser at runtime.

## Non-Overlap

This does not repeat accepted resolver result-shape validation, reader/source-provider path identity, escaped import delimiters, CRLF hex escapes, invalid import source diagnostics, import media/supports/layer parsing, external import ordering, CSS Modules dependency graph handling, source-map remapping, media-query, CSSOM, target-prefixing, property-value, or custom-at-rule clusters. The patch is limited to invalid CSS escaped code point replacement inside bundle import source resolution.
