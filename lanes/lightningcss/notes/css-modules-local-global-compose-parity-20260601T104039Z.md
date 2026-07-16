# LightningCSS CSS Modules Local Global Compose Parity

Slice: `lightningcss-css-modules-local-global-compose-parity-20260601T104039Z`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Direct upstream Node NAPI oracle used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Upstream with CSS Modules and Chrome 95 nesting lowering preserves an empty parent rule when a removed `composes` declaration appears before nested style or at-rule blocks:
  - `.foo { composes: bar from global; .baz { color: red } }` -> `.EgL3uq_foo{}.EgL3uq_foo .EgL3uq_baz{color:red}`
  - `.foo { composes: bar; @media (min-width:1px){ color: blue } color: green }` -> `.EgL3uq_foo{}@media (min-width:1px){.EgL3uq_foo{color:#00f}}.EgL3uq_foo{color:green}`
- Upstream does not synthesize that empty parent when `composes` appears after an earlier nested block; that source-order boundary is covered in the PHP test.

## Implementation

- `CssModulesTransformer::rewriteStyleBody()` now reuses the existing empty-compose marker before appending the first nested block when all previous parent declarations were removed `composes` declarations.
- The marker is restored after `NestingTransformer` lowers nested CSS, producing the upstream ordered `.local{}` parent rule before lowered nested output.
- Existing direct-declaration rules and `composes` declarations that appear after nested blocks keep their prior output order.
- The WordPress CSS Modules empty-compose example now covers a compose-only nested module root and class-list export flattening.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 525 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-empty-compose-statements.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7438 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-empty-compose-statements.php` -> no syntax errors.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS lane assertions moved from the current base status `7433` to `7438` with `0` failures.
- Conservative mapped coverage remains `2369 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules selector/declaration scanner, existing empty-compose marker restoration, nesting lowerer, export metadata model, and WordPress example harness.

## Non-Overlap

This does not repeat accepted escaped `:local`/`:global` delimiters, terminal pseudo-elements, `@nest` prelude rewriting, invalid/nested `composes` diagnostics, source-index bundle composes, pure selector boundaries, dashed-ident/property scoping, source-map, CSSOM, media-query, bundle/import graph, custom at-rule, property-value, or target-prefixing clusters. The patch is limited to source-ordered empty parent rule preservation when valid `composes` declarations precede nested CSS.

## Next Task

Continue with non-overlapping CSS Modules parser-level source-index/import graph edges, source-map remapping through CSS Modules dependencies, or selector function diagnostics not already covered by the accepted local/global/composes clusters.
