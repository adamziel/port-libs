# Custom At-Rules Repeated Prelude Parity 2026-05-31T21:03Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files:
  - `node/index.d.ts` `SyntaxString`, `Repetitions`, `MappedPrelude`, and `CustomAtRule` prelude typing.
  - `node/ast.d.ts` `ParsedComponent.repeated` and `Multiplier` AST shapes.
  - `src/values/syntax.rs` `SyntaxString::parse_value` handling for alternatives, `+` space-separated repetitions, and `#` comma-separated repetitions.

## Implementation

- `CustomAtRuleTransformer` now validates custom at-rule preludes through the same AST parser path exposed to visitors.
- Custom prelude grammars now support upstream-style syntax strings for `*`, type components, compact literal alternatives, `<custom-ident>+`, `<length>#`, and typed alternatives such as `compact|<number>`.
- Repeated prelude ASTs now expose `type: repeated`, component arrays, and `multiplier` values of `space` or `comma`, matching the upstream Node AST contract.
- Invalid repeated preludes reject before visitor dispatch, including CSS-wide identifiers for `<custom-ident>+` and missing commas for `<length>#`.
- Added a WordPress-relevant example that consumes repeated block theme part and breakpoint custom at-rules without Node/WASM.

## Verification

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 122 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 135 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 4321 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rule-repeated-prelude.php --self-test`
  => `OK`.
- Syntax checks passed for:
  - `lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `lanes/lightningcss/examples/wordpress-custom-at-rule-repeated-prelude.php`
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

## Counting

- PHP assertion delta: `+13` focused assertions, from `4308` to `4321` full-lane assertions.
- Conservative mapped coverage delta: `+0`; this deepens already mapped custom at-rule parser/visitor coverage rather than claiming a new manifest denominator row.
- Counted checks: space-repeated custom identifiers, comma-repeated lengths, compact literal alternatives, typed alternatives, and invalid repeated prelude rejection.

## Non-Overlap

This slice does not repeat accepted custom at-rule declaration-list/mixin/rule-list parser coverage, custom body rule-list/style-block visitor replacement, returned rule AST objects, composed custom/unknown/token/function visitors, FunctionExit/Length chaining, environment-variable/variable visitors, native media visitors, style-rule visitor composition, visitor factory dependencies, selector/media/supports visitors, or returned Declaration raw overflow-scrolling coverage. The stale rework note for `CustomMediaTransformer.php` was reviewed and is unrelated to this custom at-rule SyntaxString parser slice.

## Dependency Closure

No new support component is needed. The patch reuses the bounded native `CustomAtRuleTransformer`, existing token-list parser, CSS value parsers, and visitor dispatch. No Node, Rust, WASM, parser generator, browser service, network dependency, or external CSS engine is introduced.

Root harness status: not run - isolated micro-slice.
