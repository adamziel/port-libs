# Custom At-Rule Syntax Component Parser Visitor Parity

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T223208Z`
- Base accepted HEAD: `457d8df75c82fef3de304d8652d979a0fd3d1346`
- Upstream source truth: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `napi/src/at_rule_parser.rs` parses custom at-rule preludes with
  `SyntaxString::parse_value` and then visits prelude/body children through the
  normal LightningCSS visitor path.
- `src/values/syntax.rs::test_syntax` covers syntax alternatives and component
  values including `foo | <color>+ | <integer>`, `<length>` accepting
  `calc(25px + 25px)`, `<length> | <percentage>` rejecting
  `calc(100% - 25px)`, and `<transform-list>#` rejecting an invalid repeated
  transform-list grammar.
- `node/ast.d.ts` exposes the corresponding parsed component AST shapes for
  repeated values, literals, typed dimensions, images, transforms, and unit
  variants consumed by custom visitors.

## Implementation

- `CustomAtRuleTransformer` now parses additional upstream SyntaxString
  component preludes for `<length-percentage>`, `<image>`, `<angle>`, `<time>`,
  `<resolution>`, `<transform-function>`, and `<transform-list>`.
- `<length>` now folds simple same-unit `calc()` expressions such as
  `calc(25px + 25px)` and no longer accepts `%` as a length unit, preserving
  the upstream distinction between `<length>` and `<percentage>`.
- `<length-percentage>` now accepts percentages and mixed-unit `calc()` values
  while `<length> | <percentage>` rejects mixed-unit `calc()` values as
  upstream does.
- Transform visitors now expose and serialize `rotate()`/`rotateX()`/
  `rotateY()`/`rotateZ()` angle values alongside the existing `translateX()`
  shape.
- Serialization now preserves typed custom prelude values returned by visitors:
  repeated/literal values, angle/time/resolution unit variants, image values,
  transform functions, and transform lists.
- Added a WordPress-relevant example that consumes design-token custom
  at-rules for spacing, motion, tilt, density, transforms, images, and palettes
  without Node, Rust, or WASM at runtime.

## Verification

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 139 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-syntax-components.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-syntax-components.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 158 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4682 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-syntax-components.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Status Delta

- Focused custom at-rule test file: `139 -> 158` assertions.
- Full LightningCSS PHP lane: `4663 -> 4682` assertions.
- Conservative mapped coverage: unchanged at `2171 / 3532`; this deepens the
  already represented custom at-rule parser/visitor and SyntaxString component
  cluster rather than claiming a new denominator row.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
custom at-rule transformer, parser helpers, value serializers, visitor
composition, and minifier support. No browser, Node, Rust, WASM, parser
generator, network service, or new support-library activation gate is required.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source path. This patch does not repeat accepted custom
at-rule declaration-list/mixin/rule-list parser coverage, repeated prelude
basics, composed custom/unknown/token/function/FunctionExit top-level
env/var/declaration/stylesheet/selector visitors, URL visitors, returned rule
AST serialization, media/supports visitors, CSS Modules, bundler/import graph,
source-map, media-query, target-prefixing, CSSOM, or property-value clusters.
