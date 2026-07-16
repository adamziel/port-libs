# LightningCSS CSS Modules Page Composes Descriptor Parity 2026-06-01T15:56Z

## Source Truth

- Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native NAPI oracle at the pinned commit confirms `@page { composes: print-card from global; margin: 1in }` serializes as `@page{composes:EgL3uq_print-card from global;margin:1in}` and adds an export for `print-card` without adding compose metadata.
- The same oracle scopes multiple descriptor names before `from "./print.css"`, preserves invalid fallback values such as `composes: from global`, and leaves ordinary class `composes` metadata unchanged.

## Implementation

- `CssModulesTransformer` now gives simple `@page` declaration lists a descriptor-specific path.
- Valid `@page` `composes` descriptor values scope/export their identifier tokens, preserve `from global` and quoted file tails as descriptor text, and do not record local/global/dependency compose references.
- Invalid `@page` `composes` descriptor values still serialize as ordinary fallback declarations.
- Added `wordpress-css-modules-page-composes.php` to cover WordPress print CSS that combines page descriptors and a normal module class compose.

## Verification

- Red-first PHP spot-check before the fix emitted unscoped `@page{composes:card from global;...}` and did not export the descriptor name.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-page-composes.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 645 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-page-composes.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 8504 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => passed.

## Status Delta

- `phpPass` moves `8500 -> 8504`.
- Conservative mapped coverage remains `2398 / 3532`; this deepens the existing CSS Modules local/global/composes descriptor cluster rather than claiming a new manifest row.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules transformer, compose tokenizer, CSS identifier decoder/escaper, descriptor declaration scanner, minifier, and existing PHP example harness.

## Non-Overlap

This does not repeat accepted functional `:local()` compose rejection, nested compose diagnostics, unknown at-rule raw-body preservation, counter-style/font-palette/view-transition/position-try descriptor handling, dashed-ident scoping, bundle/import graph, source-map, CSSOM, media-query, custom-at-rule visitor, target-prefix, or property-value slices. Follow-up can target nested `@page` margin-box descriptor edges or other non-overlapping CSS Modules descriptor/parser parity.
