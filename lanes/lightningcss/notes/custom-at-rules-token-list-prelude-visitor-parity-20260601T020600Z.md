# Custom At-Rule Token-List Prelude Visitor Parity 2026-06-01T02:06Z

## Scope

Implemented one bounded upstream-backed LightningCSS behavior cluster: custom at-rule `SyntaxString` universal preludes (`*`) now parse as token-list components, run value/token/function visitors before `Rule.custom`, and serialize the transformed `prelude` and `preludeAst` observed by custom rule visitors.

## Upstream Source Truth

- Pinned upstream: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/index.d.ts` exposes `SyntaxString` including `*`, plus `Function`, `Variable`, `EnvironmentVariable`, and `Token` visitors.
- `src/values/syntax.rs` maps `SyntaxString::Universal` to `ParsedComponent::TokenList(TokenList::parse(...))`.
- `napi/src/at_rule_parser.rs` visits custom at-rule `prelude` before walking the body.
- `napi/src/transformer.rs` traverses token lists through function, variable, environment variable, and token visitor hooks before the rule-specific custom visitor.

## Patch

- `CustomAtRuleTransformer` now visits `token-list` custom preludes through the existing structured value, function, token, and value visitor paths.
- Universal custom prelude parsing now recognizes custom-unit dimensions such as `3--wp-step` and at-keywords such as `@--wp-accent` as token components.
- `serializeVisitorValue()` now serializes token-list AST values without re-running declaration value visitors.
- Added a WordPress-facing example that lowers a token-list custom at-rule into a `:root` custom property after visiting `theme()`, `var()`, `env()`, custom dimension tokens, and at-keyword color tokens.

## Evidence

- Baseline before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed `1 test files, 218 assertions, 0 failures`.
- Red-first after adding the focused test but before implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` failed the new universal token-list prelude test, with only `Rule.custom` firing (`1 test files, 220 assertions, 1 failures`).
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed `1 test files, 223 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 5456 assertions, 0 failures`.
- Example: `php lanes/lightningcss/examples/wordpress-custom-at-rule-token-list-prelude.php --self-test` exited `OK`.
- Syntax: `php -l` passed for `src/CustomAtRuleTransformer.php`, `tests/CustomAtRuleTransformerTest.php`, and `examples/wordpress-custom-at-rule-token-list-prelude.php`.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## Coverage

Conservatively maps one additional upstream behavior check: custom at-rule universal SyntaxString token-list prelude visitor traversal. `UPSTREAM_TEST_MANIFEST.json` mapped coverage moves from `2297 / 3532` to `2298 / 3532`. Local LightningCSS PHP assertions move from `5451` to `5456`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP custom at-rule parser, visitor composition, structured value visitor, token visitor, and serializer infrastructure; no Node, Rust, browser, parser generator, or external service is introduced.

## Non-Overlap

This does not repeat accepted bundle nested-import rejection, custom at-rule image/identifier/unit/ratio/env/var/token/declaration/style/media/rule-exit visitor slices, HSL/yellow color-mix parity, cursor target-prefix boundaries, CSS Modules, CSSOM, or source-map coverage. The stale May 25 `CustomMediaTransformer` rework note was inspected and remains unrelated to this current-base custom at-rule token-list prelude behavior.

## Next

Continue with a distinct LightningCSS parity slice, preferably source-map, CSS Modules, bundle/import graph, media-query, target-prefixing, CSSOM, property-value, or any remaining custom at-rule parser/visitor behavior not already covered by universal token-list preludes.
