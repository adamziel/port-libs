# CSS Modules Local/Global Compose Parity - 2026-06-01T04:09Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T040957Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Local pinned NAPI oracle was loaded directly from `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Oracle spot-checks reject invalid backslash-newline escapes in CSS Modules selector context, including `:local(.card\\\nTitle)`, `:global(.wp-block\\\nbutton) .card`, and selectors with a previous valid `composes` rule in the same stylesheet.

## Implementation

- Added selector-list validation in `CssModulesTransformer` before local/global selector rewriting.
- Backslash followed by LF, CR, or FF outside strings now throws `Invalid CSS escape in selector` instead of emitting a partially scoped invalid selector.
- The guard runs through the same selector path used by top-level selectors, `:local()` / `:global()`, `@scope` preludes, and rules that later attach `composes` export metadata.
- Updated `wordpress-css-modules-escaped-pseudos.php` to self-test that a WordPress block selector with a backslash-newline inside `:global()` is rejected before compose output is trusted.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-escaped-pseudos.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test file, 380 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-escaped-pseudos.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 5916 assertions, 0 failures.

## Status Delta

- `lane-status.json` `phpPass`: `5912 -> 5916`.
- Conservative mapped coverage remains `2336 / 3532`; this deepens the represented CSS Modules local/global/composes selector cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, transform pipeline, export metadata model, and existing WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not touch the stale May 25 `CustomMediaTransformer.php` rework note, bundle/import graph, source-map, media-query, property-value, CSSOM, target-prefixing, custom at-rule, nested-composes rejection, escaped composes property, escaped numeric compose, view-transition guard, or bundled option propagation slices. It is limited to invalid selector escape parity on the CSS Modules local/global/composes path.

## Next Task

Continue with non-overlapping CSS Modules selector/composes edges or pivot to current-priority LightningCSS source-map, bundle/import graph, CSSOM, media-query, target-prefix, property/value, or custom at-rule parity.
