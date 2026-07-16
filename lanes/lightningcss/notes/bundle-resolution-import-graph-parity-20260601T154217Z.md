# LightningCSS bundle resolution/import graph parity - 2026-06-01T154217Z

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T154217Z`
- Base accepted HEAD: `de04df5ab9c205f5cdb96c4bf526f27b74297a55`
- Upstream source truth: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source-Truth Evidence

- Inspected pristine upstream `src/bundler.rs` from the pinned commit with `git show`.
- Upstream collects CSS Modules `composes` and dashed-ident variable dependencies by iterating only direct top-level `CssRule::Style` declarations in `load_file`.
- Upstream maps CSS Modules dashed-ident dependency resolver/read errors through the owning style-rule location in `add_css_module_dep`; nested conditional `var(... from "...")` references are not dependency graph entries.

## Implementation

- Updated `CssBundler::cssModuleDependencyLocations()` so CSS Modules dependency locations for `composes` and dashed-ident `var(... from "...")` are recorded only from direct top-level style rules.
- Added a declaration-value guard so conditional at-rule preludes, nested rules, and top-level at-rules cannot claim a dependency location when a matching direct style dependency exists later in the file.
- Added focused regression coverage where a nested `@media` `var(... from "pkg:tokens.css")` appears before the real top-level style dependency. The resolver and read diagnostics now report the direct `.card` rule location, not the nested preview rule.
- Extended the WordPress bundle/import graph example with the same diagnostic edge for block preview CSS.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> 1 test file, 815 assertions, 0 failures
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 8511 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> passed, including `css-modules-direct-var-location: direct-style`

## Status Delta

- `phpPass`: 8500 -> 8511
- `phpFail`: 0
- Mapped coverage remains conservative at `2398 / 3532`; this deepens an already represented CSS Modules bundle/import graph dependency cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP bundler, CSS Modules transformer, resolver, and source-provider paths.

## Non-Overlap / Follow-Up

- Avoided the already accepted inline source-map offset, repeated layered import descendant, unknown statement import, and CSS Modules conditional dashed-ident no-resolve slices.
- Follow-up candidates: continue auditing CSS Modules dependency graph diagnostics where escaped `from` specifiers, external module references, and source-map locations share a specifier across direct and non-direct rule contexts.
