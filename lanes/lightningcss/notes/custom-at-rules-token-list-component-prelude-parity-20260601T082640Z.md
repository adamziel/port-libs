# Custom At-Rule Token-List Component Prelude Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T082640Z`

Accepted base: `e307345b68a0844266e5b42b8d4ac54edb9f105d`

## Source Truth

- Upstream pinned checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `node/ast.d.ts` exposes `UnknownAtRule.prelude` and `UnknownAtRule.block` as `TokenOrValue[]`.
- `src/rules/unknown.rs` parses unknown at-rule preludes and blocks with `TokenList`.
- `src/properties/custom.rs::TokenList::parse_into()` maps component values such as dashed identifiers, colors, URLs, variables, environment variables, lengths, angles, times, resolutions, and fallback raw tokens into typed `TokenOrValue` nodes.
- `node/test/customAtRules.mjs`, `node/test/visitor.test.mjs`, and `node/test/composeVisitors.test.mjs` are the upstream behavior cluster for custom parser output and visitor traversal.

## Patch

- `CustomAtRuleTransformer::parseUnknownPreludeTokens()` now reuses the shared component-value parser instead of a whitespace-only raw/ident parser.
- Component values now classify CSS unit dimensions as `length`, `angle`, `time`, or `resolution` instead of treating every alpha unit as a length.
- Token-list parsing now produces structured `url()` and `env()` values alongside the existing `var()` values.
- Custom `prelude: "*"` token-list traversal now sends direct dashed identifiers through the existing `DashedIdent` visitor and direct `url()` components through the existing `Url` visitor path.
- Focused coverage proves both registered custom at-rule token-list preludes and unknown at-rule preludes expose structured dashed-ident, color, angle, time, resolution, and URL components.
- Updated the WordPress token-list prelude smoke to exercise the same component mix without requiring Node/WASM.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 336 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-token-list-prelude.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 6961 assertions, 0 failures
```

Final hygiene was run after the lane note and status update:

```text
php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
php -l lanes/lightningcss/examples/wordpress-custom-at-rule-token-list-prelude.php
php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "OK\n";'
git diff --check -- lanes/lightningcss
```

## Status Delta

- Full lane PHP evidence: `6942 -> 6961` assertions.
- Focused `CustomAtRuleTransformerTest.php`: `317 -> 336` assertions.
- `benchmarkDenominator.mapped`: unchanged; this deepens an already represented custom at-rule parser/visitor cluster rather than adding a new upstream manifest row.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP component-value parser, visitor normalizers, URL/identifier visitor plumbing, and custom at-rule transform pipeline.

## Non-Overlap

This does not repeat accepted custom at-rule StyleSheet enter replacement, nested returned body preservation, identifier SyntaxString visitors, unknown block token-list parsing, Function/FunctionExit traversal, Ratio/Length/Percentage preludes, or media/supports/selector/style-attribute visitor behavior. The new behavior is limited to structured component parsing and visitor traversal for unknown and universal token-list preludes.

## Next Task

Continue custom at-rule parity with source-location exposure for token-list components or remaining visitor replacement re-entry edges, or pivot to another high-priority LightningCSS cluster in bundle/import graph, source maps, CSS Modules, CSSOM, media queries, target prefixing, or property/value parity.
