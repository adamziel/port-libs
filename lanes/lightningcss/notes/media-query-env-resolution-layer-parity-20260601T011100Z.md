# LightningCSS Media Query Env Resolution Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T011100Z`
Base accepted HEAD: `6025aa0c35dc17d20b1c6c068ec52bbef5bf715c`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/media_query.rs::MediaList::transform_resolution()` clones each query for required WebKit/Mozilla resolution prefixes and deduplicates the transformed query list.
- `src/media_query.rs::MediaCondition::transform_resolution()` recursively visits `Not` and `Operation` conditions. It rewrites only resolution range values parsed as concrete `Resolution`; other resolution values such as `env(...)` remain unchanged in the vendor variant.
- `src/media_query.rs::MediaFeatureValue::parse_unknown()` accepts `EnvironmentVariable` values, and `check_type()` treats them as unknown-compatible for typed media features.
- This deepens the already represented media-query range/layer and resolution-prefix clusters rather than claiming a new denominator row.

## Red-First Evidence

A direct current-base probe before the implementation showed a layered query with one concrete resolution range and one `env()` resolution range emitted only the standard query:

```text
@layer blocks{@media (min-resolution:2dppx) and (min-resolution:env(--wp-ratio)){.wp-block-query{color:#ff0}}}
```

Pinned upstream behavior keeps the `env()` condition in each query variant while transforming the concrete resolution range for old WebKit/Mozilla targets.

## Native Delta

- `TransitionPrefixer::replaceResolutionMediaQueryConditions()` now skips only non-convertible resolution values while continuing to rewrite other concrete resolution ranges in the same query.
- Queries containing only `env()` resolution bounds still avoid duplicate vendor variants.
- Added focused parser coverage for `env()` resolution range parsing and legacy min/max lowering.
- Added focused prefixer coverage for:
  - mixed concrete + `env()` `min-resolution` conditions inside `@layer`;
  - negated `resolution >` plus `resolution < env(...)` lowering and prefixing;
  - `env()`-only resolution conditions preserving the single standard query.
- Updated `wordpress-media-range-layer-prefixer.php` with a block-style density query that combines concrete and `env()` resolution bounds.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 1199 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - Result: exited `0` and emitted the mixed `env()` resolution prefix fallback branch.

Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.
Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused PHP evidence increased in the edited test files; accepted full-lane `phpPass` remains unchanged until the integrator runs the full LightningCSS lane gate.
- Conservative mapped coverage remains `2250 / 3532` because this is additional behavior inside the already represented media-query resolution-prefix/range-layer cluster.

## Non-Overlap

- Does not repeat accepted x-resolution unit serialization, resolution equality fallbacks, compound numeric resolution prefixing, feature-flag range printing, media comment handling, bare-not/layer validation, typed/unknown width ranges, SourceMap, CSS Modules, CSSOM, bundle/import graph, custom at-rule, or property-value clusters.
- The stale 2026-05-25 custom-media import-tail rework note was inspected and excluded as unrelated to this media-query resolution-prefix slice.

## Dependency Closure

No new support component is needed. The slice reuses native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, focused PHP tests, and the lane-local WordPress media-range layer example.

## Next Task

Continue with non-overlapping LightningCSS media-query parser recovery/serialization, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler, property-value/font/grid/color, or custom-at-rule parity.
