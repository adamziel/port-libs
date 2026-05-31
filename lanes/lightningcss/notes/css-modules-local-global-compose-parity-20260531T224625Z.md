# CSS Modules Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T224625Z`

Base accepted HEAD: `33a65237308053a0654b3629f3bffe8d77c73515`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream areas: `src/lib.rs::test_css_modules` and `src/css_modules.rs::CssModule::handle_composes`.
- Local native oracle spot checks confirmed the upstream export metadata remains graph-shaped for transitive local composes. The PHP-facing `exportClassList()` helper now flattens that graph for WordPress class attributes.

## Behavior

- `CssModulesTransformer::exportClassList()` now recursively follows local `composes` references.
- Nested local composes append their own nested local, global, and dependency references in class-list order.
- Dependency composes still require the existing resolver callback. Resolver output can be a string, list of strings, or `null`.
- A local stack guard prevents cyclic local compose graphs from recursing forever.
- Export metadata and emitted CSS remain upstream-shaped; only the PHP class-list helper flattens transitive local references.

The touched WordPress example also updates its stale `invalidComposes` self-test expectation to the already accepted invalid-composes fallback behavior, where malformed composes declarations are preserved rather than rejected.

## Evidence

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test file, 305 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 4685 assertions, 0 failures.

Focused assertion delta: `CssModulesTransformerTest.php` moved from 300 to 305 assertions. Full LightningCSS PHP lane moved from 4680 to 4685 assertions.

Conservative mapped coverage remains `2173 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster instead of claiming a new denominator row.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP CSS Modules transformer, export metadata shape, dependency resolver callback, and existing WordPress CSS Modules example harness. Full upstream Rust/Node/WASM runners were not executed for this isolated batch.

## Non-Overlap

This slice avoids accepted selector-mode rejection, functional local-composes rejection, invalid-composes fallback implementation, host-context scoping, pseudo-element boundaries, unused-symbol pruning, repeated source-index dependency composes, bundler import graph work, source-map offsets, media/query/property/CSSOM/custom-at-rule clusters, and target-prefixing work. The patch is limited to transitive class-list flattening from already produced CSS Modules export metadata.

## Next

Continue CSS Modules parity on remaining export graph boundaries or pivot to another high-priority LightningCSS cluster such as bundle/import graphs, source maps, CSSOM read/write, custom at-rules, media queries, target prefixing, or property/value parity.
