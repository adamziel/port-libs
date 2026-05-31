# Custom At-Rule Nested Env Visitor Parity

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T215700Z`
- Base accepted HEAD: `9ef60eb910c3006c081a236c1ec05f4d0e7024c4`
- Upstream source truth: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `node/test/visitor.test.mjs` has `spacing with env substitution`, where an
  `EnvironmentVariable` visitor returning raw values is applied inside ordinary
  declaration values, including `linear-gradient(red env(--percentage1), blue
  env(--percentage2))` and `calc(env(--length1) - env(--length2))`.
- `napi/src/at_rule_parser.rs` routes custom parser bodies through normal
  LightningCSS declaration parsing and visitor traversal, so custom at-rule
  declaration-list visitors must compose with the same value visitor behavior.

## Red-First Evidence

Before this patch, the accepted PHP transformer replaced top-level `env()`
values but left nested structured values inside generic functions:

```text
background:linear-gradient(red env(--percentage1),#00f env(--percentage2));width:calc(env(--length1) - env(--length2))
```

The focused custom at-rule test file passed before this slice with
`1 test files, 137 assertions, 0 failures`, so the missing behavior was an
unasserted visitor traversal gap.

## Implementation

- `CustomAtRuleTransformer::visitFunctionExit()` now runs serialized generic
  function arguments back through the raw visitor function scanner before
  returning the raw function value.
- `rewriteRawVisitorFunctions()` now recurses into non-structured function
  arguments while preserving quoted strings, so nested `env()`, `var()`, and
  `url()` structured visitors can fire inside generic functions without
  changing unrelated function visitor APIs.
- Added a focused test for nested `EnvironmentVariable` replacements inside
  `linear-gradient()` and `calc()`. The lane minifier then applies its existing
  color and same-unit calc simplification behavior.
- Added `wordpress-custom-at-rule-nested-env-visitor.php`, a block media smoke
  that replaces nested design-token environment values without Node, Rust, or
  WASM at runtime.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-nested-env-visitor.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-nested-env-visitor.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 139 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4516 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-nested-env-visitor.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Status Delta

- Focused custom at-rule test file: `137 -> 139` assertions.
- Full LightningCSS PHP lane: `4514 -> 4516` assertions.
- Conservative mapped coverage: unchanged at `2152 / 3532`; this deepens the
  already represented upstream EnvironmentVariable visitor cluster rather than
  claiming a new denominator row.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
custom at-rule transformer, visitor composition, value scanner, and CSS
minifier; no browser, Node, Rust, WASM, parser generator, network service, or
new support-library activation gate is required.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source path. This patch does not repeat accepted custom
at-rule parser basics, composed custom/unknown/token/function/FunctionExit
top-level env/var/declaration/stylesheet/selector visitors, URL visitors,
returned rule AST serialization, media/supports visitors, CSS Modules,
bundler/import graph, source-map, media-query, target-prefixing, CSSOM, or
property-value clusters.
