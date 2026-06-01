# Custom At-Rules Keyframes Visitor Parity - 2026-06-01

## Source Truth

- Upstream pinned cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/transformer.rs` maps `CssRule::Keyframes(..)` to visitor rule type `keyframes`.
- `node/ast.d.ts` exposes `Rule.type = "keyframes"` with `KeyframesRule.name`, `vendorPrefix`, and `keyframes`.

## Implementation

- Added native `@keyframes` dispatch to `CustomAtRuleTransformer` so `Rule.keyframes` and `RuleExit.keyframes` callbacks run instead of falling through to `unknown`.
- Added bounded keyframes AST parsing for names, vendor prefixes, `from` / `to` / percentage selectors, and declaration blocks.
- Added returned `keyframes` rule serialization so visitor replacements can rename or inspect keyframe rules while child Color, Length, and CustomIdent visitors still run.
- Added WordPress smoke `wordpress-custom-at-rule-keyframes-visitor.php` for block animation keyframes.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-keyframes-visitor.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed: 1 file / 502 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-keyframes-visitor.php --self-test` passed.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: 13 files / 9110 assertions / 0 failures.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- `phpPass`: 9106 -> 9110 (+4).
- `benchmarkDenominator.mapped`: unchanged at 2439 / 3532 because this deepens an already represented visitor/custom-at-rule cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP custom at-rule transformer, declaration parser, value visitors, CSS minifier, and lane bootstrap.

## Non-Overlap

This does not repeat accepted CSS Modules `:has(:scope)`, source-map negative column, media-list range lowering, custom parser body traversal, token-list function visitor, or native media/supports/container/layer visitor slices. It targets the remaining native `@keyframes` visitor rule mapping from upstream.

## Next Task

Continue with remaining upstream-backed LightningCSS visitor/native rule parity, or run bounded Rust/Node/WASM upstream runner evidence and record exact runner blockers.
