# LightningCSS Bundle URL Import Modifier Parity 2026-05-31T16:11Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T161119Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/lib.rs::test_import` includes `@import url(foo.css) print;` and `@import url(foo.css) supports(display: flex) print;`, both serialized as quoted imports while preserving the trailing media tail.
  - `src/bundler.rs::load_file()` parses `ImportRule` values and wraps resolved imported stylesheets by layer, media, and supports conditions.

## Native Delta

- `CssBundler::startsFunction()` now treats offset zero as having no previous character instead of reading PHP's negative string offset as the last character.
- Unquoted `url(...)` import sources with trailing identifier media now parse before bundle resolution.
- Added focused bundle assertions for `url(b.css) print`, `url(b.css) supports(display: flex) print`, and `url(tokens.css) layer(theme.tokens) screen`.
- `wordpress-bundle-import-graph.php` now smokes a block-theme print stylesheet imported as `url(blocks/print.css) supports(print-color-adjust: exact) print`.

## Evidence

- Red-first after adding the focused assertions:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 105 assertions, 1 failures` with `Invalid @import source`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 108 assertions, 0 failures`.
- Full LightningCSS lane:
  `php tools/run-tests.php lanes/lightningcss/tests` =>
  `13 test files, 2095 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` =>
  exits 0 and prints `late-import: rejected`, `resolver-shape: rejected`,
  `reader-provider: resolved`, and `css-modules: dependency graph resolved`.
- PHP lint:
  `php -l lanes/lightningcss/src/CssBundler.php`,
  `php -l lanes/lightningcss/tests/CssBundlerTest.php`, and
  `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2092` to `2095` assertions.
- Conservative mapped coverage remains `1349 / 3532` because the relevant
  upstream `src/lib.rs::test_import` helper rows were already represented by
  import-rule minifier coverage; this slice verifies the same source truth
  through native bundle graph resolution and wrapping.

## Dependency Closure

No new support component is needed. This reuses the native `CssBundler`,
resolver/read boundary, import scanner, condition wrapper, `CssMinifier`, and
existing bundle exception model. No Node, Rust, browser service, parser
generator, filesystem crawler, or external resolver package is introduced.

## Non-Overlap

This slice avoids accepted SourceProvider reads, malformed resolver-result
shape diagnostics, default relative resolution, external import ordering,
media/layer/supports wrapping for quoted imports, repeated import
last-position behavior, custom-media sharing, CSS Modules composes/dashed-ident
dependency graphs, source-map offsets, CSSOM shorthand work, target prefixing,
media-range, and custom at-rule visitor slices. It only fixes the url() import
function-token boundary that prevented trailing identifier media from entering
the existing bundle graph.
