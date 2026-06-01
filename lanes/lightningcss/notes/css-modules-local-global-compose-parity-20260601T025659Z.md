# LightningCSS CSS Modules Bundle Option Compose Parity 2026-06-01T02:56Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T025659Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/lib.rs::test_css_modules` CSS Modules config cases for `animation: false`, `custom_idents: false`, and the existing `src/css_modules.rs` source-index compose flattening behavior.
- Local pinned NAPI oracle confirmed that `animation: false` keeps `@keyframes` names and `animation` references public while still scoping classes and preserving `composes` dependency metadata. It also confirmed that `customIdents: false` keeps `@counter-style` names and `list-style` references public while preserving the scoped export record for the custom ident.

Implementation:

- `CssBundler::cssModuleTransformOptions()` now forwards `animation` and `customIdents` / `custom_idents` into every CSS Modules transform in the import graph.
- The existing source-index compose resolver is unchanged; imported local and global `composes` metadata now combines with those forwarded options instead of accidentally localizing dependency animation and counter-style identifiers.
- `wordpress-css-modules-bundler-composes.php` now smokes a block module that composes imported and global classes while disabling both animation and custom-ident scoping for public WordPress-facing identifiers.

Evidence:

- Red-first PHP spot-check before the fix ignored `animation => false` in `bundleCssModules()`, emitted scoped `dep_card-pop` / `entry_entry-pop` keyframes, and exported `entry-pop`. The same spot-check now emits public `card-pop` / `entry-pop` names and keeps only the class export with composed `dep_card`.
- Red-first PHP spot-check before the fix ignored `custom_idents => false` in `bundleCssModules()`, localizing bundled counter-style/list-style identifiers even though the direct transformer already matched upstream. The bundled path now keeps those identifiers public while still flattening local/global composes.
- `php -l lanes/lightningcss/src/CssBundler.php && php -l lanes/lightningcss/tests/CssBundlerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-bundler-composes.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 501 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 371 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 5673 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-bundler-composes.php` => emitted the existing bundled compose CSS plus `css-module-options: forwarded`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

Status delta:

- Full LightningCSS PHP evidence moves from `5669` to `5673` pass / `0` fail.
- Conservative mapped coverage remains `2314 / 3532`; this deepens the already represented CSS Modules config and dependency-graph clusters rather than adding a new denominator row.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules transformer, native bundler import graph, source-index compose resolver, PHP file/source providers, and existing minifier/formatter paths. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Non-overlap:

- This does not repeat accepted direct transformer animation/keyframes scoping, counter-style/list-style scoping, dashed-ident/grid/container handling, escaped local/global/composes parsing, functional `:local()` composes rejection, pattern validation, pure-mode boundaries, source-index compose flattening, missing-export bundling, JSON-like source-map work, media-query, CSSOM, property-value, custom at-rule, or target-prefixing slices. It only fixes bundled CSS Modules option propagation while resolving local/global/dependency composes.

Next task:

- Continue CSS Modules parity on remaining bundled option paths such as `pure`, `unusedSymbols`, and `pseudoClasses`, or pivot to another high-priority LightningCSS cluster such as bundle/import graph, source maps, CSSOM read/write, media queries, target prefixing, property/value parity, or custom at-rules.
