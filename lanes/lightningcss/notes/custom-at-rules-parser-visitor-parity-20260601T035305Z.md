# Custom At-Rule Parser Visitor Parity 2026-06-01T03:53Z

## Scope

Implemented one bounded upstream-backed LightningCSS behavior cluster:
unknown at-rule visitors now receive an upstream-style `block` token/value list
when the unknown at-rule has a block.

## Upstream Source Truth

- Pinned upstream: `/home/claude/port-libs/.upstream-cache/lightningcss` at
  `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/rules/unknown.rs` defines `UnknownAtRule` with `name`, `prelude:
  TokenList`, and `block: Option<TokenList>`.
- `node/ast.d.ts` exposes `UnknownAtRule.block?: TokenOrValue[] | null`, so
  visitor code can inspect raw block tokens without parsing the block as a CSS
  rule list.

## Patch

- `CustomAtRuleTransformer::buildUnknownRule()` now keeps the existing
  compatibility `body` string and also exposes `block` as a parsed component
  value list for unknown at-rule blocks.
- Added focused PHP coverage where a `Rule.unknown` visitor removes
  `@wp-theme card { #056ef0 4px var(--wp-gap) }`, observes the prelude token,
  and verifies the block token/value list as `color`, `length`, and `var`
  components.
- Added a WordPress-facing example that lowers an unknown token-block at-rule
  into `:root` custom properties using only the visitor-visible block tokens.

## Evidence

- Red-first ad hoc check before implementation:
  `@wp-theme tokens { color: #056ef0; radius: 4px; }` exposed `body` but no
  `block` key to `Rule.unknown`.
- Focused: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  passed `1 test files, 254 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` passed
  `13 test files, 5862 assertions, 0 failures`.
- Example: `php lanes/lightningcss/examples/wordpress-custom-at-rule-unknown-block-tokens.php --self-test`
  exited `OK`.
- Syntax:
  `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`,
  `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`, and
  `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-unknown-block-tokens.php`
  all passed.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.
- Root harness status: not run - isolated micro-slice.
- Full upstream Rust/Node/WASM runners were not executed for this isolated
  micro-slice.

## Coverage

Local LightningCSS PHP assertions move from `5855` to `5862`. This deepens the
already represented custom at-rule visitor cluster rather than claiming a new
conservative denominator row; `UPSTREAM_TEST_MANIFEST.json` mapped coverage
remains `2320 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the native PHP custom at-rule
scanner, bounded component-value parser, visitor composition, declaration/value
serializer paths, and example harness; no Node, Rust, WASM, browser, network
service, parser generator, or new support library is introduced.

## Non-Overlap

This does not repeat accepted custom at-rule SyntaxString parsing, token-list
replacement re-visitation, image/url/ratio/length prelude visitor traversal,
style/media/support/selector/rule visitors, CSSOM, CSS Modules, source-map,
bundle/import graph, media-query, property-value, or target-prefixing slices.
The stale May 25 `CustomMediaTransformer` rework note was inspected and remains
unrelated to this current-base custom at-rule unknown-block behavior.

## Next

Continue with a distinct LightningCSS parity slice, preferably source-map, CSS
Modules, bundle/import graph, media-query, target-prefixing, CSSOM,
property-value, or remaining custom at-rule parser/visitor behavior that is not
unknown-block token-list exposure.
