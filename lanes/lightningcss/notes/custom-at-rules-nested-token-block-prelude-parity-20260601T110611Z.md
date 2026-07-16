# Custom At-Rule Nested Token Block Prelude Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T110611Z`

Base accepted HEAD: `87b9b5e4231e455752546908281e85ed6f228913`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Relevant upstream behavior:
  - `napi/src/at_rule_parser.rs` parses configured custom at-rule prelude syntax and visits custom at-rule preludes before the custom rule visitor observes the rule.
  - `src/properties/custom.rs::TokenList::parse_into()` recursively emits token-list entries for parenthesis, square-bracket, and curly-bracket blocks, so values nested inside those blocks are visitable.
  - `src/values/syntax.rs` maps universal `*` syntax to a token list.

## Implementation

- `CustomAtRuleTransformer` now uses a recursive token-list parser for custom/unknown at-rule preludes while leaving declaration value parsing on the existing conservative path.
- Universal `*` custom at-rule preludes now expose square-bracket and parenthesis block token boundaries plus nested values to Token and Length visitors before `Rule.custom` callbacks run.
- Added serialization spacing support for block tokens and common delimiter tokens so visited nested preludes round-trip without extra spaces around `[`, `]`, `(`, `)`, `=`, `/`, `:`, and `;`.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 384 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7505 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-nested-token-block-prelude.php --self-test`
  - `OK`
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-nested-token-block-prelude.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/lightningcss`
  - passed with no output
- JSON status/manifest sanity check
  - `lanes/lightningcss/lane-status.json: OK`
  - `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json: OK`

## Handoff

- Dashboard movement: `phpPass` updates from `7496` to `7505` in lane status.
- Conservative mapped coverage remains `2369 / 3532`; this deepens the already represented custom at-rule parser/visitor cluster rather than claiming a new denominator row.
- Dependency closure: no new support component is needed. The slice reuses existing native PHP parser/visitor infrastructure.
- Non-overlap: avoids recent bundle/import graph, CSS Modules, source-map, media-query, property-value, CSSOM, and target-prefix clusters; touches only custom at-rule universal token-list prelude traversal.
- Root harness: not run - isolated micro-slice.
