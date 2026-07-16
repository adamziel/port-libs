# Custom At-Rule Unit Prelude Visitor Parity 2026-06-01T01:08Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/index.d.ts` exposes `Visitor.Angle`, `Visitor.Time`, and
  `Visitor.Resolution`.
- `node/ast.d.ts` exposes parsed custom at-rule component values for
  `angle`, `time`, and `resolution` SyntaxString preludes.
- `node/composeVisitors.js` composes `Angle`, `Time`, and `Resolution` as
  simple value visitors, matching the existing `Length`/`Color` composition
  model.
- `src/visitor.rs` exposes `visit_angle`, `visit_time`, and
  `visit_resolution` visitor hooks.

Native PHP delta:

- `CustomAtRuleTransformer` now stores `Angle`, `Time`, and `Resolution`
  visitor callbacks from the configured visitor map.
- `composeVisitors()` now composes those unit variant visitors in order and
  accepts replacements from earlier visitors before passing values to later
  visitors.
- Parsed custom at-rule SyntaxString preludes for `<angle>`, `<time>+`, and
  `<resolution>` now run unit visitors before `Rule.custom` observes the final
  custom rule, so both `prelude` text and `preludeAst` reflect replacements.
- Added a WordPress smoke that converts unit-valued design-token at-rules into
  `:root` custom properties without Node/WASM.

Evidence:

- Focused test:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 203 assertions, 0 failures`.
- Full LightningCSS lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 5258 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-unit-prelude-visitor.php`
  all reported no syntax errors.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rule-unit-prelude-visitor.php --self-test`
  => `OK`.
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners were not executed for this isolated
  micro-slice.
- Root harness status: not run - isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage remains `2248 / 3532` because this deepens the
  already represented custom at-rule parser/visitor cluster rather than
  claiming a new denominator row.
- Local LightningCSS PHP assertions move from `5247` to `5258`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `CustomAtRuleTransformer`, SyntaxString prelude parser, visitor composition,
  value visitor dispatch, and value serialization paths; no Node, Rust,
  browser, parser generator, or external service is introduced.

Non-overlap:

- This avoids the accepted custom at-rule identifier, image prelude, Function,
  Color, Length, variable/env, Declaration, DeclarationExit, RuleExit, token,
  style attribute, CSSOM, media-query, source-map, target-prefixing, and CSS
  Modules clusters.
- The stale May 25 rework note about `CustomMediaTransformer` import-tail
  conflicts was inspected and left untouched because it is unrelated to this
  current-base custom at-rule visitor slice.

Next task:

- Continue custom at-rule visitor parity around remaining typed value surfaces
  such as `Ratio`, or pivot to another high-value LightningCSS parity cluster.
