# CSS Modules Escaped Composes Property Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T001248Z`

Base accepted HEAD: `9938ea0ca5f2430c11f7b91d23d2213507185488`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream areas: `src/properties/css_modules.rs::Composes::parse`, `src/css_modules.rs::CssModule::handle_composes`, and `src/lib.rs::test_css_modules`.
- Native NAPI spot checks through `lightningcss.linux-x64-gnu.node` confirmed that escaped declaration property identifiers are decoded before CSS Modules `composes` handling:
  - `c\6f mposes: base` is treated as `composes: base`.
  - `c\6f mposes: wp-block-card from g\6c obal` records a global compose.
  - `C\6f MPOSES: token from "./tokens.css"` records a dependency compose.

## Behavior

- `CssModulesTransformer` now decodes and normalizes declaration property identifier tokens before testing whether a declaration is `composes`.
- Escaped and case-varied `composes` property spellings are removed from emitted CSS, matching upstream CSS Modules behavior.
- Local, global, and dependency compose metadata is preserved for escaped property names, including escaped `global` keywords and quoted dependency specifiers.
- `exportClassList()` continues to flatten local/global/dependency composed classes for WordPress class attributes.

Red-first PHP spot check before the patch emitted a raw `c\6f mposes: foo` declaration and did not record compose metadata. The pinned upstream native artifact removed the declaration and exported the composed class.

## Evidence

- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-escaped-composes-property.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test files, 331 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-escaped-composes-property.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 5020 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss` -> passed.

Focused assertion delta: `CssModulesTransformerTest.php` moved from 327 to 331 assertions. Full LightningCSS PHP lane moved from 5016 to 5020 assertions.

Conservative mapped coverage remains `2218 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster instead of claiming a new denominator row.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP CSS Modules transformer, selector/declaration scanner, CSS identifier decoder, dependency metadata shape, dependency resolver callback, and existing example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This avoids accepted escaped `:local()` / `:global()` pseudos, escaped class identifiers, escaped composed class identifiers, escaped dependency specifiers, invalid-composes fallback behavior, pure-mode validation, priority/comment handling, state/highlight/host-context selectors, nested and `@nest` handling, source-index and bundler import graph work, CSSOM/media/source-map/target-prefix/property/custom-at-rule clusters, and mapped media-query/placeholder prefixing work. The patch is limited to CSS-escaped declaration property names for `composes` and their local/global/dependency export metadata.

## Next

Continue CSS Modules parity on remaining export graph and parser boundaries, or pivot to another high-priority LightningCSS cluster such as bundle/import graphs, source maps, CSSOM read/write, custom at-rules, media queries, target prefixing, or property/value parity.
