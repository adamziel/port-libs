# Custom At-Rule Returned Unknown Block Token-List Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T124343Z`

Base accepted HEAD: `687c594e4d06eca0127679aada46331adea32e3c`

## Source Truth

- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/rules/unknown.rs` models `UnknownAtRule` with `block: Option<TokenList>` and serializes a returned block token list between braces when present.
- `node/ast.d.ts` exposes `UnknownAtRule.block?: TokenOrValue[] | null`, matching the Node visitor shape used for returned unknown rules.

## Red-First Evidence

Before the source change, the new focused PHP test failed because a visitor-returned upstream-shaped unknown at-rule with a `block` token list and no intended legacy body still serialized the stale body string:

```text
FAIL custom at-rules serialize returned upstream unknown block token lists
Expected: '@wp-token-block card-live{#abcdef 1.5rem var(--wp-scale)}.wp-block-card{color:red}'
Actual: '@wp-token-block card-live{stale-body-should-not-print}.wp-block-card{color:red}'
1 test files, 402 assertions, 1 failures
```

An initial broader implementation serialized every unknown-rule block token list before the parsed body fallback and regressed the existing keyframes fallback path. The final implementation only uses returned `block` token lists for upstream-shaped returned unknown rules with no non-empty legacy `body`.

## Implementation

- Added `CustomAtRuleTransformer::serializeUnknownRuleBlockValue()` and wired it into unknown-rule stylesheet and visitor-return serialization.
- The serializer now emits returned unknown at-rule `block` token lists when the visitor returns a token-list `block` and omits a non-empty legacy `body`.
- Existing parsed-body fallback is preserved for current unknown/keyframes paths that still carry parsed `body` text.
- Added focused PHP coverage for non-empty and empty returned `block` token lists.
- Added `wordpress-custom-at-rule-returned-block-tokens.php` as a local WordPress-relevant smoke for design-token block at-rules.

## Verification

```text
php lanes/lightningcss/examples/wordpress-custom-at-rule-returned-block-tokens.php --self-test
OK

php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php

php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php

php -l lanes/lightningcss/examples/wordpress-custom-at-rule-returned-block-tokens.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-returned-block-tokens.php

php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 405 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7847 assertions, 0 failures

git diff --check -- lanes/lightningcss
passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused `CustomAtRuleTransformerTest.php`: `401 -> 405` assertions, `+4`.
- Full LightningCSS lane: `7843 -> 7847` assertions, `+4`.
- `lane-status.json` `phpPass`: `7843 -> 7847`.
- Conservative mapped coverage remains `2392 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the existing native component-value parser, visitor value serializer, unknown at-rule AST path, and PHP example self-test harness. No Node, Rust, WASM, browser, network, or live-service dependency was introduced.

## Non-Overlap

This slice does not repeat accepted custom at-rule block-token exposure, returned prelude token-list serialization, custom prelude token visitors, nested returned body parsing, RuleExit handling, selector/media/supports custom at-rule visitor slices, source-map, CSS Modules, target-prefix, or property-value clusters. It is specifically scoped to serializing upstream-shaped visitor-returned unknown-rule `block` token lists.
