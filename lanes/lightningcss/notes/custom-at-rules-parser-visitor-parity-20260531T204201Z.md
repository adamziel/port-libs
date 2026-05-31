# LightningCSS Custom At-Rules Parser Visitor Parity - 2026-05-31 20:42 UTC

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T204201Z`
- Base accepted HEAD: `91b42fe7029899440b4b46f38b3f903a76f3b322`
- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `node/test/visitor.test.mjs` `supports returning raw values for tokens`: a `Function` visitor returns `{ raw: "rgba(255, 0, 0)" }` and the output minifies to `red`.
- `node/test/visitor.test.mjs` `supports returning raw values as variables`: a `Function` visitor returns `{ raw: "var(--foo)" }` and the returned variable remains eligible for native dashed-ident/CSS Modules handling before serialization.

## Implementation

- `CustomAtRuleTransformer::callFunctionVisitor()` now detects raw array replacements from `Function` visitors and reparses only structured `env()`, `var()`, and `url()` functions inside that raw replacement.
- Raw color/function text that is not a structured value visitor target is preserved for the existing declaration-value minifier, so `rgba(255, 0, 0)` continues to serialize as `red`.
- Added focused PHP coverage for raw `Function` visitor returns that produce both `rgba(...)` and `var(...)`, with the returned variable passing through the configured `DashedIdent` visitor.
- Added `wordpress-custom-at-rule-raw-function-var.php` as a narrow WordPress smoke for design-token helper functions returning raw CSS variables without Node/WASM.

## Verification

- Red-first probe before the source change:
  - `theme()` returning `['raw' => 'var(--foo)']` with a `DashedIdent` visitor emitted `.foo{color:var(--foo)}`.
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-raw-function-var.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 122 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 4183 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-raw-function-var.php --self-test`
  - Result: `OK`.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused custom at-rule assertions: `120 -> 122`.
- Full LightningCSS lane assertions/pass count: `4181 -> 4183`.
- Conservative mapped coverage: `2078 -> 2080 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the existing native
`CustomAtRuleTransformer`, value visitor scanner, declaration minifier,
`DashedIdent` visitor path, and lane-local WordPress smoke. No external
parser, browser service, Node/WASM runtime, Rust binary, or shell-out is
introduced.

## Non-Overlap

This avoids the accepted custom at-rule declaration-list/mixin/rule-list
parser, returned media/style/supports, unknown/token, FunctionExit/Length,
EnvironmentVariable/Variable direct replacement, selector, URL, DashedIdent,
CustomIdent, native media, StyleSheet, CSS Modules, source-map, bundler,
CSSOM, color, and target-prefixing clusters. The stale May 25
`CustomMediaTransformer` rework note was inspected and is unrelated to this
raw `Function` visitor value-shape slice.

## Next Task

Continue with non-overlapping visitor value-shape gaps only if they map a
remaining upstream Node/Rust behavior; otherwise prioritize stronger
LightningCSS target-prefix, property-value, CSSOM, source-map, bundler, media,
CSS Modules, and parser recovery parity slices.
