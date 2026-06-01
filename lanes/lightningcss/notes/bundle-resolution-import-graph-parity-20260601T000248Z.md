# LightningCSS Bundle Import Graph Parity - 2026-06-01T00:02Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream file: `src/bundler.rs`.
- Relevant upstream behavior:
  - `Bundler::order()` keeps the first discovered CSS Modules dependency instance, while ordinary non-module imports preserve the last instance.
  - `test_css_module()` covers an imported local-name collision where `/a.css` imports `/b.css` and both define `.a`; only the entry module exports its local `.a`, but both scoped rules are emitted.
  - `test_css_module()` also covers recursive dependency composition where entry `.a` composes dependency `.x`, and `.x` composes `.y`; the entry export flattens both dependency locals.

## Native Delta

- Added focused PHP coverage in `CssBundlerTest.php` for imported local-name collisions, recursive dependency composes, and first-instance dependency ordering when a CSS Modules dependency is reachable through both `@import` and `composes ... from`.
- Extended the WordPress import graph example with a block-style CSS Modules first-instance smoke that models `.wp-block-card` name collision plus token composition.
- No production source change was needed. The current native PHP bundler already matched the upstream behavior; this slice pins the parity at the PHP and WordPress-smoke layers.

## Evidence

- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 384 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - exits 0 and prints `css-modules-first-instance: stable`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 4992 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - no whitespace errors.

## Status Delta

- Focused `CssBundlerTest.php` assertions move `375 -> 384`.
- Full LightningCSS PHP lane assertions move `4983 -> 4992`.
- `lane-status.json` `phpPass` moves `4983 -> 4992`.
- `benchmarkDenominator.mapped` remains `2216 / 3532` because this deepens an already represented `src/bundler.rs::test_css_module` / bundle CSS Modules graph cluster rather than claiming a fresh denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `CssBundler::bundleCssModules()` resolver, import graph, CSS Modules export, and example bootstrap paths.

## Non-Overlap

- Avoided stale `port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md` custom-media import-tail rework; current accepted notes already contain later custom-media import-tail coverage, and this slice only touches bundle CSS Modules import graph parity.
- Did not duplicate the recent CSS Modules `project_root` hash parity or missing-export bundle dependency slices.
- Did not run or edit upstream Rust, Node, WASM, or full root harness artifacts.

## Follow-Up

Next bundle/import graph work can target unresolved CSS Modules source-map/export interactions or remaining resolver diagnostic parity that is not already represented by the current import graph clusters.
