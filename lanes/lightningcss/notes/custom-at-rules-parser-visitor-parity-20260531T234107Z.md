# Custom At-Rule Parser Visitor Parity 2026-05-31T23:41Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/composeVisitors.js` `createArrayVisitor`, where composed visitors
  restart earlier visitors after a replacement emits new items and skip the
  visitor that produced the current replacement to avoid cycles.
- `node/test/composeVisitors.test.mjs` `composed properties`, where a custom
  `size` declaration visitor expands to `width` and `height`, and a separate
  `width` visitor removes the generated width in either visitor order.
- `node/index.d.ts` declares both `Declaration` and `DeclarationExit` visitor
  shapes as replacement-capable visitor entries.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now routes `Declaration` and
  `DeclarationExit` through a restart-on-replacement helper matching the
  upstream composed visitor behavior.
- Generated declaration replacements are rechecked by earlier declaration
  visitors, so composing a custom declaration expander with a generated
  property remover is order-independent.
- Added a WordPress smoke that models a block declaration utility expanded by
  one plugin visitor and pruned by another without depending on visitor order.

Evidence:

- Red-first reproduction before patch:
  `php -r 'require "tools/bootstrap.php"; ... reversed composed Declaration visitors ...'`
  returned `.foo{width:16px;height:16px}` instead of the upstream-equivalent
  `.foo{height:16px}`.
- Focused test:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 174 assertions, 0 failures`.
- Full LightningCSS lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 4879 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-composed-declaration-order.php`
  all reported no syntax errors.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rule-composed-declaration-order.php --self-test`
  => `OK`.
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners were not executed for this isolated
  micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `2202 / 3532` to `2203 / 3532`.
- Local LightningCSS PHP assertions move from `4877` to `4879`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `CustomAtRuleTransformer`, `DeclarationBlock`, visitor composition, and
  value serialization paths; no Node, Rust, browser, parser generator, or
  external service is introduced.

Non-overlap:

- This avoids the accepted custom at-rule parser basics, RuleExit, variable
  visitors, token visitors, style attribute visitors, CSSOM, media query,
  source-map, target-prefixing, and CSS Modules clusters. The stale May 25
  rework note about `CustomMediaTransformer` import-tail conflicts was
  inspected and left untouched because it is unrelated to this current-base
  custom at-rule visitor slice.

Next task:

- Continue custom at-rule visitor parity around remaining upstream visitor
  composition edges such as returned Rule arrays and value visitor restart
  behavior, or pivot to another high-value LightningCSS parity cluster.
