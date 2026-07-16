# CSS Modules Host-Context Compose Parity

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T205434Z`.
- Targeted upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, especially `src/selector.rs` CSS Modules selector serialization.
- Upstream behavior: `:host(...)` and `::slotted(...)` selector arguments run through CSS Modules local scoping, but `:host-context(...)` remains a raw custom function argument. Native pinned artifact spot-checks showed `:host-context(.theme) .card` prints `:host-context(.theme) .EgL3uq_card` and exports only `card`, while `:host(.theme)` and `::slotted(.icon)` export scoped `theme` / `icon`.

## Implementation

- `CssModulesTransformer` no longer treats `host-context` as a selector function whose arguments allow CSS Modules rewrites.
- This keeps public WordPress/editor context classes in `:host-context(...)` unscoped while preserving ordinary local selectors and local `composes` metadata in the same stylesheet.
- Pure mode now ignores host-context-only selectors like upstream, but still accepts selectors that include an ordinary local class outside the host-context argument.
- Added `wordpress-css-modules-host-context.php` to model build-free block CSS delivery where an editor preview context remains public and component-local classes still compose.

## Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 257 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4314 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-host-context.php --self-test` => `OK`.
- Full upstream Rust/Node/WASM runners were not run for this isolated micro-slice.

## Coverage

- Focused CSS Modules transformer evidence moved from `251` to `257` assertions.
- Conservative mapped coverage remains `2100 / 3532`; this deepens the already represented CSS Modules local/global/composes selector-function cluster rather than adding a new denominator row.
- Non-overlap: does not repeat accepted selector-list validation, nested global/local mode precedence, escaped identifiers/specifiers, declaration-priority `composes`, functional `:local()` composes rejection, pure no-check/license handling, unusedSymbols pruning, grid/scope/container/dashed-ident/view-transition/content-hash handling, or bundle dependency flattening.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules selector scanner, export metadata model, minifier/nesting pipeline, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.
