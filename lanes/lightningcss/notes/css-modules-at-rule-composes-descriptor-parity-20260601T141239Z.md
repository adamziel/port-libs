# CSS Modules At-Rule Composes Descriptor Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T141239Z`

Base accepted HEAD: `3077b90a0c2ff1e27da878d6b65b2167c49abaf7`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine upstream reads used `git show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs`; the local upstream checkout has unrelated dirty files, so the pinned object was treated as source truth.
- Direct upstream NAPI oracle used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Oracle behavior:
  - `@counter-style x { composes: foo; symbols: A }` drops the valid descriptor-level `composes`, keeps `symbols`, and exports only the scoped counter-style name.
  - `@font-palette-values --x { composes: foo; font-family: A }` drops descriptor-level `composes`, keeps `font-family`, and exports only the scoped dashed at-rule name.
  - `@view-transition { composes: foo; types: bar }` drops descriptor-level `composes`, scopes `types`, and exports only the view-transition type.
  - `@position-try --x { composes: foo; left: anchor(left) }` drops descriptor-level `composes`, keeps `left`, and exports only the scoped dashed at-rule name.
  - Invalid descriptor values such as `composes: foo calc(1 + 2)` are preserved as fallback declarations for `@counter-style` and `@position-try`, but do not create CSS Modules export composition.

## Implementation

- `CssModulesTransformer` now routes `@counter-style`, `@font-palette-values`, `@view-transition`, and `@position-try` declaration-list bodies through descriptor-aware `composes` handling instead of the style-rule `composes` export path.
- Valid descriptor-level `composes` declarations are dropped without adding local/global/dependency references.
- Invalid descriptor-level `composes` fallbacks are preserved for the upstream counter-style and position-try cases that keep them as ordinary descriptors.
- Position-try descriptors continue to scope dashed identifiers in non-composes declarations, so `left: anchor(left)` and scoped `@position-try --name` output remain intact.
- The WordPress position-try smoke now includes descriptor-level `composes` lines to prove block popover CSS does not throw or pollute module exports.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-position-try.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 628 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-position-try.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8180 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lanes/lightningcss/lane-status.json` `phpPass`: `8173 -> 8180`.
- Focused CSS Modules evidence moved from `621` to `628` assertions.
- Conservative mapped coverage remains `2393 / 3532`; this deepens the represented CSS Modules at-rule descriptor/composes parity cluster rather than claiming a new manifest denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules transformer, descriptor declaration scanner, dashed-ident scoping, minifier, focused PHP test harness, and existing WordPress example self-test path. The native NAPI binding was used only as a local oracle, not as runtime support.

## Non-Overlap

This does not repeat accepted unknown-at-rule raw-body preservation, position-try dashed-ident scoping, font-palette dashed-ident scoping, counter-style/list-style references, view-transition type scoping, nested-composes diagnostics, dependency graph flattening, source-map, bundle/import graph, CSSOM, media-query, custom-at-rule visitor, property-value, or target-prefixing slices. The new behavior is limited to descriptor-level `composes` parity inside known at-rules.

## Next Task

Follow up on non-overlapping CSS Modules descriptor and parser edges, such as `@page` descriptor value scoping around authored `composes`, or pivot to the current LightningCSS priority buckets: bundle/import graph, source maps, CSSOM, visitor/custom at-rule, media-query, selector, parser recovery, and target-prefix/property value parity.
