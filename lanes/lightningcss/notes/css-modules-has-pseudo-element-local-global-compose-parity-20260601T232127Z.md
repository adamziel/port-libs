# CSS Modules Has Pseudo-Element Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T232127Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted behavior: CSS Modules selector rewriting for direct `:has()` branches that contain pseudo-elements after `:scope`, combined with local class hashing, forgiving selector-list filtering, and local `composes` exports.
- Local pinned NAPI oracle confirmed:
  - `.card:has(:scope::before:hover, :scope::marker, :scope::part(icon), :scope::highlight(focus-ring), :scope::cue(.caption), .ok)` serializes with `:before:hover`, `::marker`, `::part(icon)`, scoped `::highlight(...)`, scoped `::cue(...)`, and the scoped `.ok` branch.
  - `.card:has(.drop::before .child, :scope::part(icon) .child, .kept)` drops the invalid pseudo-element descendant branches and keeps only the scoped `.kept` branch.
  - `.button { composes: card; }` still exports the rewritten button class composed with the rewritten card class.

## Implementation

- Taught the pseudo-element boundary preflight to skip top-level direct `:has(...)` contents so upstream-valid `:scope::before`, `:scope::marker`, `:scope::part(...)`, `:scope::highlight(...)`, and `:scope::cue(...)` branches are not rejected before forgiving selector processing.
- Added branch-local pseudo-element boundary validation inside the existing `:has()` forgiving rewrite path so invalid pseudo-element descendant branches are filtered instead of throwing for the whole rule.
- Reused the existing selector rewriting, scoped custom-ident handling, CSS Modules export collection, and `composes` flattening paths.
- Added `wordpress-css-modules-has-pseudo-elements.php` to smoke a block-module selector that combines a public `:global(.wp-block-card)` ancestor with local `:has()` pseudo-element branches and a composed button class.

## Evidence

- Red-first PHP spot-check before the fix threw `CSS pseudo-elements cannot be followed by selectors` for upstream-accepted direct `:has(:scope::before...)` and dropped-branch cases.
- Pinned upstream native binding emitted the expected retained pseudo-element branches and forgivingly removed invalid descendant branches for the selected CSS Modules cluster.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-has-pseudo-elements.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 701 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-has-pseudo-elements.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 9111 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `9106 -> 9111`.
- Conservative mapped coverage remains `2439 / 3532`; this deepens the existing CSS Modules selector/composes parity cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, forgiving selector-list handling, CSS identifier decoding, minifier pipeline, export metadata model, and existing example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted `:has(:scope ...)` relative selector elision, single-`:is()` unwrapping, forgiving invalid local/global branch dropping, dangling combinator recovery, nested `composes` rejection, transition-property custom-ident boundaries, page composes descriptors, view-transition guards, bundle/import graph work, source-map work, media-query range/layer work, target-prefixing, CSSOM read/write, parser recovery, or property-value minifier slices. The patch is limited to direct `:has()` pseudo-element branch parity after CSS Modules local/global rewriting while preserving composed export metadata.

## Next Task

Continue CSS Modules parity on remaining selector-mode and composed export edges, or pivot to current high-value LightningCSS bundle/import graph, source-map, media-query, CSSOM, custom at-rule, target-prefix, parser recovery, or property/value parity gaps.
