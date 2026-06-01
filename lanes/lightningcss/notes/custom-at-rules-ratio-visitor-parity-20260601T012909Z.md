# Custom At-Rule Ratio Visitor Parity - 2026-06-01

## Scope

Implemented one bounded upstream-backed LightningCSS behavior cluster: custom at-rule `SyntaxString` ratio preludes (`<ratio>` and `<ratio>#`) now parse to ratio AST values, run the upstream-shaped `Ratio` visitor before custom rule visitors, and serialize ratio replacements in custom rule/media outputs.

## Upstream Source Truth

- Pinned upstream: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/index.d.ts` exposes `Visitor.Ratio?(ratio: Ratio): Ratio | void` and `type Ratio = [number, number]`.
- `src/visitor.rs` exposes `fn visit_ratio(&mut self, ratio: &mut Ratio)`.
- `src/values/ratio.rs` parses `first` plus optional `/ second`, defaults the denominator to `1`, and serializes `/1` as a single number.

## Patch

- `CustomAtRuleTransformer` parses ratio component preludes and aspect-ratio media feature values into `['type' => 'ratio', 'value' => [first, second]]`.
- Direct and composed `Ratio` visitors are applied through the same value visitor path as Length/Angle/Time/Resolution.
- Ratio visitor replacements are normalized to two-number arrays and serialized as `4/3` or `2` when denominator is `1`.
- Added a WordPress-facing smoke for a custom aspect-ratio rule and generated theme ratio variables.

## Evidence

- Red-first before implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` failed the new ratio test with `Invalid custom at-rule prelude for <ratio>: 16 / 9` (`1 test files, 211 assertions, 1 failures`).
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed `1 test files, 218 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 5393 assertions, 0 failures`.
- Example: `php lanes/lightningcss/examples/wordpress-custom-at-rule-ratio-visitor.php --self-test` exited `OK`.
- Syntax: `php -l` passed for `src/CustomAtRuleTransformer.php`, `tests/CustomAtRuleTransformerTest.php`, and `examples/wordpress-custom-at-rule-ratio-visitor.php`.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.

## Coverage

Conservatively maps one additional upstream behavior check: custom at-rule ratio component parser/visitor traversal and serialization. `UPSTREAM_TEST_MANIFEST.json` mapped coverage moves from `2289 / 3532` to `2290 / 3532`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP custom at-rule parsing, visitor composition, media-rule serialization, and example smoke infrastructure.

## Non-Overlap

This does not repeat the stale custom-media rework note, accepted bundle import supports validation, CSS Modules escaped comments, transform CSSOM, unit custom at-rule visitors, or env media prefixing. It targets only the still-missing `Ratio` visitor/value surface for custom at-rule preludes.

## Next

Continue with a distinct custom at-rule visitor/value surface not already covered by image, identifier, unit, ratio, env/var, token, selector, or style-rule visitor slices.
