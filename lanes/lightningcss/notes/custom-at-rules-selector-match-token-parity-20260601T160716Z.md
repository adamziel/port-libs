## Custom At-Rule Selector Match Token Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T160716Z`

Source truth:

- Pinned upstream manifest commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/ast.d.ts` exposes distinct `Token` union members for `include-match`, `dash-match`, `prefix-match`, `suffix-match`, and `substring-match`.
- `src/properties/custom.rs` keeps cssparser component tokens as `TokenOrValue::Token`, so universal custom at-rule token lists should not collapse selector match operators into generic delimiter tokens.
- `node/composeVisitors.js` composes Token visitors from all token visitor keys.

Implemented behavior:

- Universal custom at-rule preludes now parse selector match operators `~=`, `|=`, `^=`, `$=`, and `*=` into upstream-style token types instead of generic delimiter tokens.
- `CustomAtRuleTransformer::composeVisitors()` now routes composed Token visitors for those five token types.
- Returned token-list preludes serialize those token types back to their CSS operators.

WordPress scenario:

- `wordpress-custom-at-rule-selector-match-tokens.php` models a build-free WordPress block variant custom at-rule inspecting `[data-state~=draft]`, `[lang|=en]`, `[href^=shop]`, `[file$=pdf]`, and `[class*=wp-block]` selector fragments.

Verification:

- Baseline before patch: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 470 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-selector-match-tokens.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 474 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-selector-match-tokens.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8629 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> clean.

Dependency closure:

- No new support component is needed. This reuses the existing native `CustomAtRuleTransformer` component-value token parser, token visitors, and serializer.

Non-overlap:

- This does not repeat accepted custom at-rule attribute selector parser body work, SyntaxString token-list visitors, env/var fallback visitors, nested token-list block preludes, function argument visitors, StyleSheet/RuleExit visitors, CSSOM, CSS Modules, source-map, media-query, bundle/import graph, property-value, or target-prefixing slices.
