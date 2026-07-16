# LightningCSS CSS Modules Empty Compose Statement Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T091903Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream areas: `src/lib.rs::test_css_modules` CSS Modules helper behavior, `src/css_modules.rs::CssModule::handle_composes`, and the pinned local NAPI artifact for empty declaration serialization around removed `composes`.
- Local pinned NAPI check: `.test { ; composes: foo; ; color:red; } .foo{color:blue}` serializes without empty declaration semicolons while preserving the local compose export.

## Change

- `CssModulesTransformer` now drops style-body statements that become empty after semicolon stripping.
- This matches upstream when redundant empty statements surround removed CSS Modules `composes` declarations.
- Local, global, and dependency compose metadata still flattens through `exportClassList()` in source order.
- Added a WordPress block CSS Module smoke for editor CSS that contains redundant semicolons from generated tooling around `composes`.

## Red-First Evidence

- Before the source fix, the new focused PHP test failed with:
  - expected `.EgL3uq_card{color:red}.EgL3uq_base{color:#00f}`
  - actual `.EgL3uq_card{;;color:red;;;}.EgL3uq_base{color:#00f}`

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-empty-compose-statements.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 506 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-empty-compose-statements.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7118 assertions, 0 failures`.

## Status Delta

- `lane-status.json` now records `phpPass: 7118`.
- Conservative mapped coverage remains `2365 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP CSS Modules transformer and the existing test/example harness.

## Non-Overlap

This does not repeat accepted escaped custom-ident scoping, terminal pseudo-element boundaries, nested composes rejection, single `:is()` local/global unwrapping, bundle import graph composes flattening, source-map, media-query, CSSOM, property-value, custom at-rule, or target-prefix slices. The patch is limited to upstream empty declaration pruning around removed CSS Modules `composes`.
