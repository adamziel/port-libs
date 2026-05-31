# Custom At-Rule Variable Exit Visitor Parity

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T224837Z`
- Base accepted HEAD: `33a65237308053a0654b3629f3bffe8d77c73515`
- Upstream source truth: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `node/index.d.ts` exposes `VariableExit` and `EnvironmentVariableExit` on
  the public visitor interface.
- `node/composeVisitors.js` composes both exit visitors through the same token
  visitor machinery as `FunctionExit`.
- `napi/src/transformer.rs` wires `Variable`/`VariableExit` and
  `EnvironmentVariable`/`EnvironmentVariableExit` through `VisitorsRef`, and
  applies the exit stage after child token traversal.

## Implementation

- `CustomAtRuleTransformer` now configures specific and generic
  `VariableExit` and `EnvironmentVariableExit` callbacks.
- `composeVisitors()` now exposes bounded composed exit visitors for CSS
  variables and environment variables.
- Structured `var()` and `env()` traversal now invokes enter visitors first,
  preserves original values when enter visitors return null, then applies exit
  visitors before serialization.
- Raw value scanning now activates when only exit visitors are present, so
  declaration values and media query preludes can use exit-only visitors.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 150 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4682 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-variable-exit-visitor.php --self-test`
  - `OK`

Additional required gates were run after the note update:

- PHP lint on changed PHP files.
- `git diff --check -- lanes/lightningcss`.

## Status Delta

- Focused custom at-rule test file: `146 -> 150` assertions.
- Full LightningCSS PHP lane: `4680 -> 4682` assertions in this worktree.
- Conservative mapped coverage: unchanged at `2173 / 3532`; this deepens the
  already represented custom at-rule/visitor cluster rather than claiming a
  new denominator row.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## WordPress Smoke

- Added `wordpress-custom-at-rule-variable-exit-visitor.php`, which models
  block CSS replacing an editor breakpoint `env()` token and a spacing
  `var()` token from exit visitors without Node, Rust, or WASM at runtime.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
custom at-rule transformer, visitor composition, value scanner,
`MediaQueryParser`, `DeclarationBlock`, and CSS minifier. No browser, Node,
Rust, WASM, parser generator, network service, or support-library activation
gate is introduced.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated. This patch does not repeat accepted RuleExit custom/unknown/style/
media visitor behavior, declaration visitors, FunctionExit/Length visitors,
env()/var() enter visitors, nested env raw-function scanning, style attribute
visitors, selector/URL/identifier visitors, CSS Modules, bundler/import graph,
source-map, media-query, target-prefixing, CSSOM, or property-value clusters.
