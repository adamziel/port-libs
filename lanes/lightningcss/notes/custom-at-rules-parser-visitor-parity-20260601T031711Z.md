# Custom At-Rule Parser Visitor Parity 2026-06-01T03:17Z

## Scope

Implemented one bounded upstream-backed LightningCSS behavior cluster: custom at-rule universal token-list preludes now re-parse visitor replacements and visit generated components before `Rule.custom` observes the prelude.

## Upstream Source Truth

- Pinned upstream: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/composeVisitors.js` restarts array/value visitor composition after a visitor replacement, while skipping the visitor that already produced the replacement.
- `napi/src/transformer.rs` visits token-list replacements by walking children after an enter visitor returns a replacement.
- `napi/src/at_rule_parser.rs` visits a custom at-rule prelude before invoking the custom rule visitor, so generated prelude values must be settled before `Rule.custom`.

## Patch

- `CustomAtRuleTransformer` now re-parses custom prelude token-list replacement CSS and revisits the parsed components with a bounded recursion guard.
- Token replacements skip the same token type on the immediate replacement pass to avoid re-running the producing token visitor on its own replacement, matching upstream composed visitor semantics.
- Added focused PHP coverage where a custom token-list prelude dimension token generates `fluid(3,var(--wp-fluid-step))`, the nested `Variable` visitor runs, `FunctionExit` returns `2rem`, and `Rule.custom` observes the final `2rem` prelude AST.
- Added a WordPress-facing example that lowers `@wp-fluid-token 3--wp-fluid-step;` into a `:root` custom property after the replacement traversal.

## Evidence

- Red-first: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` failed the new replacement traversal assertion before the source patch: token visitor output reached `Rule.custom` as `fluid(3,var(--wp-fluid-step))`; `Variable` and `FunctionExit` did not run (`1 test files, 240 assertions, 1 failures`).
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed `1 test files, 243 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 5738 assertions, 0 failures`.
- Example: `php lanes/lightningcss/examples/wordpress-custom-at-rule-token-replacement-revisit.php --self-test` exited `OK`.
- Syntax: `php -l` passed for `src/CustomAtRuleTransformer.php`, `tests/CustomAtRuleTransformerTest.php`, and `examples/wordpress-custom-at-rule-token-replacement-revisit.php`.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## Coverage

This deepens the already represented custom at-rule and `composeVisitors` behavior cluster rather than adding a new conservative denominator row. `UPSTREAM_TEST_MANIFEST.json` keeps mapped coverage at `2320 / 3532`. Local LightningCSS PHP assertions move from `5733` to `5738`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP custom at-rule parser, token-list parser, visitor composition, structured value visitor, and serializer infrastructure; no Node, Rust, WASM, browser, parser generator, network service, or new support library is introduced.

## Non-Overlap

This does not repeat accepted custom at-rule universal token-list parsing, raw function visitor replacement, variable/env exits, style/media/support/selector/rule visitors, property-value, CSSOM, CSS Modules, source-map, bundle/import graph, media-query, or target-prefixing slices. The stale May 25 `CustomMediaTransformer` rework note was inspected and remains unrelated to this current-base custom at-rule replacement traversal behavior.

## Next

Continue with a distinct LightningCSS parity slice, preferably source-map, CSS Modules, bundle/import graph, media-query, target-prefixing, CSSOM, property-value, or remaining custom at-rule parser/visitor behavior that is not token-list replacement re-visitation.
