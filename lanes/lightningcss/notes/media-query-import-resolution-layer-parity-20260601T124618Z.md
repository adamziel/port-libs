# Media Query Import Resolution Layer Parity

Date: 2026-06-01
Micro-slice: `lightningcss-media-query-range-layer-parity-20260601T124618Z`
Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source Truth

Pinned upstream media query transformation walks media lists, including the media tail on `@import` rules. The same resolution-prefix behavior used for `@media (resolution >= 2dppx)` therefore applies to layered `@import` media lists before old target range syntax is lowered.

Relevant upstream source:

- `src/rules/import.rs::ImportRule` stores the import media tail as a `MediaList` and prints it after `layer(...)` / `supports(...)` modifiers.
- `src/media_query.rs::MediaCondition::get_necessary_prefixes()` requests `Feature::AtResolution` prefixes for `resolution` media ranges.
- `src/media_query.rs::MediaCondition::transform_resolution()` rewrites resolution conditions for WebKit and Mozilla prefix targets.
- `src/lib.rs::test_media()` pins resolution prefix and range fallback output for old Safari/Firefox targets.

## Red-First Evidence

Before this patch, layered `@import` media tails lowered the resolution range but skipped WebKit/Mozilla resolution aliases:

```sh
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets("@import \"blocks/density.css\" layer(theme.blocks) ((resolution >= 2dppx)); @layer theme.blocks { .wp-block-query { color: yellow; } }", ["safari"=>15,"firefox"=>10]), "\n";'
```

Observed before the fix:

```text
@import "blocks/density.css" layer(theme.blocks) (min-resolution:2dppx);@layer theme.blocks{.wp-block-query{color:#ff0}}
```

## Implementation

`TransitionPrefixer::rewriteImportMediaRangeTails()` now considers resolution-prefix targets as a reason to scan top-level `@import` rules.

`TransitionPrefixer::rewriteImportMediaRangeTail()` now runs the existing `prefixResolutionMediaQueries()` step on the parsed media tail before the existing range lowering and resolution unit conversion. This mirrors the `@media` prelude path while preserving import source, `layer(...)`, and `supports(...)` modifiers.

The WordPress media-range layer example now self-checks a layered density stylesheet import with wrapped modern resolution range syntax and Safari/Firefox prefix fallback output.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1285 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - exited `0` and printed the expected layered import resolution prefix output
- `git diff --check -- lanes/lightningcss`
  - passed

Root harness: not run - isolated micro-slice.

## Status Delta

`lane-status.json` now reports `phpPass: 7844` and `phpFail: 0`.

This adds one focused PHP TestRunner assertion. Conservative mapped coverage remains `2392 / 3532` because this deepens the already represented media-query import-tail/range-prefix cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the native `TransitionPrefixer`, `MediaQueryParser`, existing top-level import scanner, and WordPress media-range layer example smoke.

## Non-Overlap

This does not repeat accepted direct resolution prefixing inside `@media`, x-resolution unit serialization, media range target thresholds, custom-media import-tail scanner work, bundle/import graph media propagation, CSS Modules, SourceMap, CSSOM, custom at-rule, property-value, or selector-prefix clusters. It is limited to upstream-style resolution prefixing for layered `@import` media tails before range fallback lowering.
