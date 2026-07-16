# CSS Modules Escaped Numeric Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T022125Z`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/css_modules.rs::CssModule::handle_composes` with `src/lib.rs::test_css_modules` local/global/composes behavior.
- Pinned native NAPI oracle spot-check:
  - `.\31 23, .alpha { composes: \31 23-base; color: red } .\31 23-base { color: blue }`
  - Output: `.EgL3uq_123,.EgL3uq_alpha{color:red;}.EgL3uq_123-base{color:#00f}`
  - Exports include string local key `123` composing local `EgL3uq_123-base`, and `alpha` composing the same local.

## Implementation

- `CssModulesTransformer` now casts selector-local keys back to strings before returning the local list and before attaching composes metadata.
- This prevents PHP from converting decoded escaped numeric class names such as `\31 23` into integer array keys at the point where `ensureExport()` expects a string local name.
- Added a WordPress-facing escaped numeric CSS Modules smoke for block CSS where migrated numeric utility classes compose a reset class while public escaped numeric selectors remain global.

## Evidence

- Red-first PHP spot-check before the fix crashed with `TypeError: CssModulesTransformer::ensureExport(): Argument #1 ($local) must be of type string, int given` for `.\31 23 { composes: \31 23-base; color: red }`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 364 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-escaped-numeric-compose.php --self-test` => `OK`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-escaped-numeric-compose.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 5456 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules evidence moves from `359` to `364` assertions.
- Full LightningCSS PHP evidence moves from `5451` to `5456 pass / 0 fail`.
- Conservative mapped coverage remains `2297 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules selector scanner, CSS identifier escape decoder, export metadata model, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules escaped pseudo names, escaped selector delimiters, escaped composes properties/comments, functional `:local()` composes rejection, view-transition selector guards, host-context behavior, unused-symbol pruning, grid/scope/container/dashed-ident handling, bundle dependency diagnostics, media-query, source-map, target-prefix, CSSOM, or custom at-rule visitor slices. It only closes escaped numeric local class names becoming integer locals when `composes` metadata is attached.
