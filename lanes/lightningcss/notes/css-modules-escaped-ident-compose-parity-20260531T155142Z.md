# CSS Modules Escaped Identifier Compose Parity

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T155142Z`.
- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files: `src/selector.rs` functional `:local(...)` / `:global(...)` selector parsing and pure selector component checks, plus `src/css_modules.rs::CssModule::handle_composes`.
- Upstream behavior: CSS escaped identifiers are decoded for CSS Modules export metadata and `composes` references, while printed selectors re-escape characters such as `:` and `@`. Local spot-check against the pinned native artifact showed `.sm\:m-1 { composes: base; ... }` exports `sm:m-1`, prints `.EgL3uq_sm\:m-1`, and records local/global `composes` names decoded.

## Implementation

- `CssModulesTransformer` now reads CSS identifier tokens with backslash escapes in local class/id selector positions, including hex escapes with terminator whitespace.
- Scoped local selector names are emitted through CSS identifier escaping, so metadata keeps decoded names like `foo@bar` while CSS prints `.EgL3uq_foo\@bar`.
- `composes` parsing now decodes escaped identifier tokens before handling `from`, `global`, local references, dependency references, and CSS-wide keyword rejection, and keeps escaped hex terminator whitespace inside the identifier token.
- The simple-class-selector guard for `composes` now accepts escaped class tokens while preserving rejection for functional `:local(...)`, complex selectors, ids, and globals.
- The WordPress CSS Modules example now covers a block module class such as `.card\:featured` composing a local class and a global escaped utility name.

## Evidence

- Red-first PHP spot-check before the implementation: transforming `.sm\:m-1 { composes: base; color: red } .base { color: blue }` failed with `CSS Modules composes may only be used in a simple local class selector`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 97 assertions, 0 failures`.
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2028 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- PHP lint: `php -l` passed for `CssModulesTransformer.php`, `CssModulesTransformerTest.php`, and `wordpress-css-modules-transformer.php`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CSS Modules transformer, selector scanner, and lane-local minifier/nesting pipeline.

## Non-Overlap

This does not repeat accepted pure-mode selector boundaries, functional `:local()` composes rejection, selector-list validation, nested global precedence, dashed-ident dependency graphs, view-transition scoping, missing dependency export flattening, or bundle import graph behavior. The older lane rework note for `CustomMediaTransformer.php` is historical for this slice because current accepted LightningCSS status already includes the custom-media import-tail cluster; this patch stays on the assigned CSS Modules escaped local/global/composes behavior.
