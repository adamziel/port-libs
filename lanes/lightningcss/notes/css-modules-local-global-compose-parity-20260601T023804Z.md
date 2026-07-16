# CSS Modules Local/Global Compose Parity - 2026-06-01T02:38Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T023804Z`

Accepted base: `d66a5b3de6df2dc65a32a2f70e37d0a3eee8d74f`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs` rejects `Property::Composes` when the printer destination is nested and CSS Modules are enabled, returning `InvalidComposesNesting`.
- `src/error.rs` formats that diagnostic as `The `composes` property cannot be used within nested rules`.
- Native artifact checks at the pinned checkout reject valid `composes` declarations inside top-level `@media`, `@supports`, `@layer`, `@container`, and `@scope` child style rules, while the same at-rule child selectors without `composes` still scope normally.

## Implementation

- Threaded a CSS Modules `composes` nesting context through rule-list, at-rule-body, style-body, and declaration rewriting.
- Top-level at-rule child style rules still run selector/export rewriting at top-level selector depth, preserving pure selector behavior and local/global scoping.
- Valid `composes` declarations now throw the upstream nested-rule diagnostic inside at-rule bodies and nested style contexts.
- Updated bundled CSS Modules resolver diagnostics to keep the resolver fixture on an upstream-valid top-level style rule instead of nested `@media` `composes`.
- Updated `wordpress-css-modules-transformer.php` to remove upstream-invalid nested `composes` examples and add an explicit nested-composes rejection smoke.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test file, 371 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> 1 test file, 486 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 5526 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- `lane-status.json` `phpPass`: `5521 -> 5526`.
- Conservative mapped coverage remains `2303 / 3532`; this deepens the existing CSS Modules local/global/composes cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This is a native PHP transformer/bundler parity correction using existing CSS parser, selector scoping, media-query, and CSS Modules metadata helpers.

## Non-Overlap

This does not touch the stale `CustomMediaTransformer.php` rework note, source-map skipped-index guards, bundle import source-map comment handling, CSS Modules commented-composes boundaries, font typography target-prefixing, custom at-rule token-list preludes, CSSOM grid auto-flow, or media range import dedupe. The slice is limited to CSS Modules `composes` nesting parity and directly coupled tests/example/status evidence.
