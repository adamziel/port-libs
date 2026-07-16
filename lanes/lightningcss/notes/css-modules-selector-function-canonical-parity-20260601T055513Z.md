# CSS Modules Selector Function Canonical Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T055513Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant source: `src/selector.rs` serializes selector-valued `Component::Is`, `Component::Where`, `Component::Has`, `Component::Negation`, and prefixed `Component::Any` using canonical pseudo names, then serializes nested selector lists after CSS Modules local/global rewriting.
- Local pinned NAPI oracle confirmed the red case: PHP preserved authored escaped/uppercase names such as `:w\68 ere`, `:h\61 s`, `:n\6f t`, and `:-WEBKIT-ANY`, while upstream prints `:where`, `:has`, `:not`, and `:-webkit-any`.

## Implementation

- `CssModulesTransformer` now prints canonical selector-valued pseudo function names for forgiving selector functions after local/global rewriting.
- Added strict `:not()` selector-list rewriting instead of relying on incidental token scanning, so escaped and uppercase `:not()` names canonicalize while invalid `:local()` / `:global()` selector lists still throw in the non-forgiving context.
- Preserved existing single simple `:is()` unwrapping, forgiving invalid-list dropping for `:is()` / `:where()` / `:has()` / prefixed `:any()`, and local/global/dependency `composes` metadata.
- Added `wordpress-css-modules-selector-function-canonical.php` to prove block CSS Modules can use escaped selector-valued pseudo names without leaking non-canonical selector text.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-selector-function-canonical.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 418 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-selector-function-canonical.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6327 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- Full LightningCSS PHP evidence: `6322 -> 6327` assertions, `+5`.
- Focused CSS Modules evidence: `413 -> 418` assertions, `+5`.
- Conservative mapped coverage remains `2359 / 3532`; this deepens the represented CSS Modules local/global/composes selector cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, selector-list splitting, export metadata model, minifier pipeline, and WordPress example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted selector-comment guards, invalid escaped selector-newline guards, escaped `:local()` / `:global()` pseudo handling, forgiving invalid selector-list dropping, single `:is()` unwrapping, nth-child formula minification, host-context behavior, view-transition guards, bundled CSS Modules option propagation, bundle/import graph, source-map, media-query, property-value, CSSOM, target-prefixing, or custom at-rule slices. The patch is limited to canonical serialization of selector-valued pseudo function names after local/global CSS Modules rewriting while preserving `composes` export metadata.

## Next Task

Continue with non-overlapping CSS Modules selector/composes edges, or pivot to current-priority LightningCSS source-map, bundle/import graph, CSSOM, media-query, target-prefix, property/value, or custom at-rule parity.
