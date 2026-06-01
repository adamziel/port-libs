# Custom At-Rules Parser Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T235807Z`
Base: `0e78c232d5f671d5140ddac2287b4ff3c85d5779`

## Source Truth

- Upstream `parcel-bundler/lightningcss` pinned commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/at_rule_parser.rs` custom `AtRule::visit_children` visits the parsed prelude before the custom rule body.
- `napi/src/transformer.rs` `visit_image` runs `Image`, then visits image children such as `Url`, then runs `ImageExit`.
- `node/index.d.ts` exposes `Visitor.Image` and `Visitor.ImageExit`.
- `src/values/syntax.rs` includes `ParsedComponent::Image` for `SyntaxString` custom at-rule preludes.

## Patch

- Added native PHP `Image` and `ImageExit` visitor hooks to `CustomAtRuleTransformer`.
- Parsed custom at-rule SyntaxString preludes now run through value visitor traversal before `Rule.custom` receives the rule.
- `<image>` preludes now support upstream visitor order: `Image` enter, nested `Url`, then `ImageExit`.
- Added direct and composed visitor coverage for URL-image and `none` image preludes.
- Added a WordPress-facing image-token smoke example for theme asset rewriting and fallback image insertion.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-image-prelude-visitor.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> 1 file / 184 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-image-prelude-visitor.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 files / 4993 assertions / 0 failures.
- JSON validity check for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` -> OK.
- `git diff --check -- lanes/lightningcss` -> clean.

## Coverage Delta

- `phpPass`: 4983 -> 4993.
- `benchmarkDenominator.mapped`: unchanged at 2216 / 3532; this deepens the already represented custom at-rule parser/visitor cluster instead of claiming a new upstream denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP custom at-rule parser, SyntaxString prelude AST, URL value visitor, and serializer.

## Non-Overlap

This avoids the stale custom-media rework note and recent accepted custom at-rule clusters for Function/Length/Color/Token/DashedIdent/CustomIdent/MediaQuery/SupportsCondition/RuleExit behavior. The new behavior is specifically `<image>` prelude traversal and `Image`/`ImageExit` visitor parity.
