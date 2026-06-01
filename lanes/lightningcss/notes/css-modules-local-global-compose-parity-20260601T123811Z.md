# LightningCSS CSS Modules Local Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T123811Z`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Direct upstream Node NAPI oracle used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Oracle behavior: a single `:is(:global(...))` or `:is(:local(...))` argument is transparent after CSS Modules mode rewriting, even when the inner selector contains a descendant, child, or sibling combinator.
- Counter-boundary: an ordinary single complex local selector such as `.card:is(.wrapper .child)` remains wrapped as `:is(...)`.

## Implementation

- `CssModulesTransformer` now recognizes a single transparent CSS Modules mode pseudo as the whole `:is()` argument and unwraps it after local/global rewriting.
- Existing `:is()` unwrapping boundaries remain in place for type selectors, ordinary complex local selector arguments, and invalid/dropped forgiving selector branches.
- The focused CSS Modules test covers descendant, child, and sibling combinators through `:global(...)` and `:local(...)`, plus local `composes` export preservation.
- The WordPress single-`:is()` smoke now covers block-group and editor-variant selectors without requiring Node/WASM at runtime.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-single-is-local-global.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 573 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-single-is-local-global.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7775 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lanes/lightningcss/lane-status.json` `phpPass`: `7771 -> 7775`.
- Conservative mapped coverage remains `2392 / 3532`; this deepens the already represented CSS Modules local/global/composes selector cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, forgiving selector-list handling, local/global mode rewriting, export/composes metadata model, and existing WordPress example harness.

## Non-Overlap

This does not repeat accepted escaped `:local`/`:global` delimiter diagnostics, terminal pseudo-element boundaries, selector comment guards, invalid escaped selector-newline guards, language pseudos, host/slotted/cue handling, nested `composes` diagnostics, source-index bundle composes, source-map, bundle/import graph, media-query, CSSOM, custom-at-rule, property-value, or target-prefixing clusters. The patch is limited to transparent single `:is(:local(...))` / `:is(:global(...))` selector argument unwrapping while preserving composed export metadata.

## Next Task

Continue with non-overlapping CSS Modules parser/source-index edges or pivot to current-priority LightningCSS source-map, bundle/import graph, CSSOM, media-query, target-prefix, property/value, or custom at-rule parity.
