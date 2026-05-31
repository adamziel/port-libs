# LightningCSS Media Query Explicit Type OR Guard Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T180416Z`

Base: `e83ba68ab62e3e93ee2dcf9fc87ea144ffeb366d`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source: `src/media_query.rs` `MediaQuery::parse_with_options` and `parse_query_condition`.
- Upstream parses a condition after an explicit media type with `QueryConditionFlags::empty()`, so a top-level `or` after `screen and`, `print and`, `all and`, `not screen and`, or `only screen and` is invalid.
- The same parser allows `or` when it is wrapped inside the media-type condition, e.g. `screen and ((color) or (hover))`.

## Red-First Evidence

Before this patch, the native PHP parser accepted invalid explicit-media-type OR conditions:

```bash
php -r 'require "tools/bootstrap.php"; $p = new PortLibs\LightningCSS\MediaQueryParser(); echo $p->minifyList("screen and (color) or (hover)"), PHP_EOL;'
```

Observed before implementation:

```text
screen and (color) or (hover)
```

Layered CSS also shipped the invalid prelude instead of rejecting it.

## Native Delta

- `MediaQueryParser` now rejects top-level `or` inside the condition following an explicit media type.
- Wrapped OR conditions remain valid and serialize unchanged, including inside `@layer`-wrapped block-theme CSS.
- `wordpress-media-layer-minifier.php --self-test` now covers both the valid wrapped OR form and the invalid unwrapped OR guard.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: `1 test files, 154 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 426 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2833 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - Result: exited `0`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2825 -> 2833 pass / 0 fail`.
- Conservative mapped coverage remains `1616 / 3532`; this deepens the already represented media-query parser validation cluster rather than claiming a fresh denominator row.

## Non-Overlap

This avoids accepted source-map byte-stream VLQ parsing, aspect-ratio value minification, HWB/HSL color-mix clusters, file-backed CSS Modules bundling, media-query all-media elision, parenthesized range negation, typed/unknown/equality range lowering, resolution prefixes and `x` unit serialization, media feature include/exclude flags, cascade-layer merging, custom-media scanner/import-tail behavior, CSSOM, bundler, CSS Modules, target-prefix, property/color/grid/font, and custom at-rule visitor slices.

## Dependency Closure

No new support component is needed. This slice reuses the native `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, and lane-local tests/examples. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.
