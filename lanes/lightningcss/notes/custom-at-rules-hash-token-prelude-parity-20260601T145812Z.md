# Custom At-Rules Hash Token Prelude Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T145812Z`
Base: `230af65eea9aebb1e5494b80a95d24a010885d55`

## Source Truth

- Upstream pinned LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/test/visitor.test.mjs` exposes `Token.hash` and `Token.id-hash` visitor callbacks.
- `node/test/customAtRules.mjs` is the custom at-rule parser/visitor traversal source-truth cluster.

## Implemented Behavior

- `CustomAtRuleTransformer` now parses non-color `#...` components in universal custom at-rule preludes (`prelude: '*'`) as token values instead of raw values.
- Hash tokens are classified as upstream-style `id-hash` when their decoded value is a valid CSS identifier and `hash` otherwise.
- Escaped hash identifiers such as `#wp\2d icon` are decoded before visitor dispatch, so `Token.id-hash` visitors see `wp-icon` before `Rule.custom` visitors receive the final prelude.
- Existing color parsing remains first, so already covered color prelude behavior keeps its `color` AST shape.

## Evidence

- Red-first before source patch:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 444 assertions, 1 failures`
- After implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 448 assertions, 0 failures`
  - `php lanes/lightningcss/examples/wordpress-custom-at-rule-token-list-prelude.php --self-test`
  - Result: `OK`
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 8311 assertions, 0 failures`

## Non-Overlap

- This does not repeat existing declaration-value `Token.hash` / `Token.id-hash` coverage.
- This does not repeat accepted dashed-ident, custom-ident, function, env/var, dimension, ratio, URL, image, or space-separated function custom-prelude slices.
- This slice is specifically the parser/visitor gap for hash and id-hash tokens in custom at-rule universal preludes before custom rule visitors.

## Dependency Closure

No new support component is needed. The implementation reuses the existing CSS escape decoder, identifier classifier, token visitor dispatch, and token serializer.

## Next

Continue custom at-rule parity with any remaining upstream visitor surfaces that are not already covered by declaration values or custom prelude component clusters, especially parser/body interactions that can be verified with focused PHP tests.
