# CSS Modules Has Scope Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T230613Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted behavior: CSS Modules selector rewriting for relative `:has()` branches that start with `:scope`, combined with `:local()` / `:global()` mode pseudos and local `composes` exports.
- Local pinned NAPI oracle confirmed:
  - `.card:has(:scope > :global(.legacy))` serializes as `.EgL3uq_card:has(>.legacy)`.
  - `.card:has(:scope + :local(.child))` serializes as `.EgL3uq_card:has(+.EgL3uq_child)`.
  - `.card:has(:scope .desc)` serializes as `.EgL3uq_card:has( .EgL3uq_desc)`.
  - `.card:has(:scope)` serializes as `.EgL3uq_card:has()`.
  - `.card:has(:scope:is(:global(.legacy)), :scope > .child)` serializes as `.EgL3uq_card:has(.legacy,>.EgL3uq_child)`.

## Implementation

- Added a `:has()`-specific forgiving selector rewrite path in `CssModulesTransformer`.
- The new path strips a leading `:scope` after CSS Modules local/global rewriting, while preserving the semantic descendant combinator when `:scope .child` becomes ` .child`.
- Added an internal marker only for this semantic descendant edge so the existing minifier pass cannot collapse it to `.child`.
- Local/global exports and local `composes` metadata remain unchanged and continue to flatten through `exportClassList()`.
- Added `wordpress-css-modules-has-scope.php` to smoke a block-module variant class that composes a base class while matching public WordPress globals and local module selectors through `:has(:scope ...)`.

## Evidence

- Red-first PHP spot-check before the fix emitted `:has(:scope>.legacy)`, `:has(:scope+.EgL3uq_child)`, and `:has(:scope.legacy,:scope>.EgL3uq_child)` where upstream elides the leading `:scope`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-has-scope.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 696 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-has-scope.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 9106 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `9102 -> 9106`.
- Conservative mapped coverage remains `2439 / 3532`; this deepens the already represented CSS Modules selector/composes cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, forgiving selector-list handling, CSS identifier decoding, minifier pipeline, export metadata model, and existing example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted single-`:is()` unwrapping, forgiving invalid local/global branch dropping, dangling combinator recovery, nested `composes` rejection, transition-property custom-ident boundaries, page composes descriptors, view-transition guards, bundle/import graph work, source-map work, media-query range/layer work, target-prefixing, CSSOM read/write, or property-value minifier slices. The patch is limited to `:has(:scope ...)` relative selector serialization after CSS Modules local/global rewriting while preserving composed export metadata.

## Next Task

Continue CSS Modules parity on remaining selector-mode and composed export edges, or pivot to current high-value LightningCSS bundle/import graph, source-map, media-query, CSSOM, custom at-rule, target-prefix, parser recovery, or property/value parity gaps.
