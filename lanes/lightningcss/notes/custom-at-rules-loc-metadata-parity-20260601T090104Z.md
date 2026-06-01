# Custom At-Rule Location Metadata Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T090104Z`

## Upstream Evidence

- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/at_rule_parser.rs` stores `AtRule::loc` from `ParserState::source_location()` for both `parse_block` and `rule_without_block`.
- `node/ast.d.ts` exposes `UnknownAtRule.loc: Location2`; `Location2` has `source_index`, zero-based `line`, and one-based `column`.

## Native Change

- `CustomAtRuleTransformer` now threads source locations into custom and unknown at-rule visitor payloads on the normal Rule visitor path.
- The same location metadata is exposed through `StyleSheet` visitor AST entries for custom, unknown, media, supports, and style rules.
- Source locations are conservative single-source offsets with `source_index: 0`, matching the current lane's single-input transform path.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-loc-metadata.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` => `1 test files, 350 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-loc-metadata.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 7037 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP parser offsets and visitor infrastructure; full upstream Rust/Node/WASM runners remain out of scope for this isolated lane handoff.

## Non-Overlap

This does not repeat the accepted custom at-rule token-list component, function visitor, selector visitor, escaped-name visitor, body parser, or visitor composition clusters. It only adds source-location metadata parity for the already represented custom/unknown at-rule visitor surface.
