# LightningCSS Bundle Escaped Import Identifier Parity 2026-05-31T22:03Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T220340Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `git show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/parser.rs` around import parsing shows `@import` consumes the source with `expect_url_or_string()`, then recognizes `layer`, `layer(...)`, and `supports(...)` as parsed CSS identifiers/functions before the remaining media query list.
  - `git show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/bundler.rs` around `load_file()` and `inline()` shows the parsed source, layer, supports, and media metadata feed dependency resolution and the final supports > media > layer wrapper order.
  - `git show 22bdda3d190f1cd321d98026225cfc964af64ad9:node/test/bundle.test.mjs` confirms resolver/read callbacks are the bundle source-provider boundary this PHP port mirrors.

## Native Delta

- `CssBundler::parseImportStatement()` now recognizes escaped CSS identifier spellings for import source and modifier keywords before dependency resolution:
  - `u\72l(...)`, `\75rl(...)`, and whitespace-terminated `\75 rl(...)` are parsed as `url(...)`.
  - `l\61yer`, `l\61yer(...)`, and whitespace-terminated `\6c ayer` are parsed as cascade-layer import modifiers.
  - `s\75pports(...)` is parsed as the supports import modifier before the remaining media list.
- Added a bounded CSS identifier-token reader that decodes valid CSS escapes and returns the token end offset so existing layer/supports/media parsing and validation still own the semantic checks.
- `wordpress-bundle-import-graph.php` now smokes a block-theme bundle that imports escaped `url()`, `layer`, and `supports()` identifiers through a custom resolver callback.

## Evidence

- Red-first gap: before this patch, the bundler only matched raw `url`, `layer`, and `supports` spellings in import preludes, so escaped keyword spellings were not decoded before the resolver/wrapper graph path.
- `php -l lanes/lightningcss/src/CssBundler.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`: no syntax errors.
- Focused before local assertion count: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` was `1 test files, 356 assertions, 0 failures`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 358 assertions, 0 failures`.
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4516 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` exits 0 and prints `escaped-import-identifiers: resolved`.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `4514` to `4516` assertions.
- Conservative mapped coverage: `2152 / 3532` to `2153 / 3532`.

## Dependency Closure

No new support component is needed. This reuses `CssBundler` import scanning, the existing CSS escape decoder, custom resolver callbacks, source-provider reads, wrapper composition, `CssMinifier`, and the current bundle exception model. No Node, Rust, WASM, parser generator, network resolver, or filesystem crawler is introduced.

## Non-Overlap And Follow-Up

This avoids accepted escaped import specifier decoding, quoted url() import source parsing, CRLF escaped import resolution, escaped layer name parsing, malformed import-source rejection, malformed import-layer diagnostics, external import ordering, duplicate supports/media/layer import merging, custom-media import-tail substitution, CSS Modules dependency graphs, source-map offset work, target-prefix/property-value slices, CSSOM work, and custom at-rule visitor slices.

Follow-up should target a distinct upstream import-graph gap such as direct minifier import-prelude escaped modifier parity, source-map preservation across escaped import source forms, or resolver diagnostics for malformed escaped function keywords.
