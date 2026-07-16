# Media Query Negated Custom Range Layer Parity - 2026-06-01

## Scope

Implemented the `lightningcss-media-query-range-layer-parity-20260601T052814Z` slice for negated custom media range serialization through layered WordPress block CSS.

## Upstream Source Truth

Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Relevant upstream evidence:

- `src/media_query.rs` represents unknown and dashed custom media feature names separately from known standard feature ids.
- `QueryFeature::negate()` clones the parsed feature name before inverting the comparison, so authored custom casing is preserved.
- `QueryFeature::to_css()` lowers range syntax through `write_min_max()` without lowercasing custom feature names; only the fallback `min-` / `max-` prefix is added.

Before this slice, PHP `MediaQueryParser::invertSimpleRangeFeature()` lowercased the feature name while compacting `not (feature >= value)`, so layered CSS such as `@media not (Theme-Breakpoint >= 2)` became `(theme-breakpoint<2)` and Firefox fallback output became `not (min-theme-breakpoint:2)`.

## Implementation

- `MediaQueryParser::invertSimpleRangeFeature()` now canonicalizes the range feature through the same feature-name path used by direct range parsing.
- Known standard media features still lowercase, while custom and unknown names such as `Theme-Breakpoint` and `--WP-Breakpoint` preserve authored casing.
- Added parser, minifier, target-prefixer, and WordPress example coverage for negated custom ranges inside `@layer` blocks.

## Verification

Commands run for this slice:

```sh
php -l lanes/lightningcss/src/MediaQueryParser.php
php -l lanes/lightningcss/tests/MediaQueryParserTest.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php
php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php
php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test
php tools/run-tests.php lanes/lightningcss/tests
git diff --check -- lanes/lightningcss
```

Results:

- PHP lint: no syntax errors detected in all changed PHP files.
- Focused parser/prefixer tests: `2 test files, 1441 assertions, 0 failures`.
- WordPress media range layer example self-test: passed.
- Full LightningCSS lane tests: `13 test files, 6211 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`: passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `MediaQueryParser`, `CssMinifier`, and `TransitionPrefixer` pipeline.

## Non-Overlap

This is additive to the earlier case-sensitive custom range slice and fixes the remaining negation path only. It does not touch the stale 2025 custom-media import-tail rework note, bundle/import graph behavior, CSS Modules, source maps, CSSOM, custom at-rules, resolution prefix fallbacks, env/calc range parsing, or all/not-all layer pruning.
