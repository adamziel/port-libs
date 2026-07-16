# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T044010Z`
- Base accepted HEAD: `a9f4989344098e67e1082ce806a8270acd26ace6`
- Source truth: upstream `parcel-bundler/lightningcss` manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

Used the pinned native LightningCSS NAPI artifact from `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` with `bundleAsync({ cssModules: true })`.

- `@import "component.css" screen` where `component.css` contains `.card { composes: local; color: green }` rejects with `The \`composes\` property cannot be used within nested rules`.
- The same rejection occurs for `layer(theme.blocks)` and `supports(display: grid)` import wrappers, including dependency composes such as `composes: token from "tokens.css"`.
- Conditional imports without `composes` still bundle under their wrappers.
- Repeated imports that merge to an unconditional final source, such as conditional-then-unconditional and unconditional-then-conditional media imports of the same CSS Module, are accepted by upstream and emit unwrapped module CSS.

## Implementation

- `CssBundler` now records whether each CSS Module source contains a top-level `composes` declaration and validates after the full import graph has loaded, so the decision uses the merged final media/layer/supports context rather than the first import that read the file.
- Conditional final contexts reject with the upstream message `The \`composes\` property cannot be used within nested rules`.
- Dependency reads and resolver diagnostics still occur before this validation, preserving existing source-provider diagnostic ordering.

## Verification

- Baseline before edit: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 545 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 550 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` -> `css-modules-conditional-composes: rejected`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 5983 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json valid\n";'` -> `lane-status json valid`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- `lanes/lightningcss/lane-status.json` `phpPass`: `5978 -> 5983`.
- Conservative mapped upstream coverage remains `2336 / 3532`; this deepens the represented bundle/import graph and CSS Modules cluster rather than adding a new manifest unit.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP bundle graph loader, CSS Modules transformer, resolver/read callbacks, and focused PHP test harness.

## Follow-Up

Remaining bundle/CSS Modules parity areas include structured source locations for this post-graph validation error, additional resolver diagnostic ordering edges, and source-map behavior around CSS Modules dependencies imported through media/layer/supports wrappers.
