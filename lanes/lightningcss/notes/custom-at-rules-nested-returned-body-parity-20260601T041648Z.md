# Custom At-Rules Nested Returned Body Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T041648Z`

Source truth:

- Pinned upstream cache: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/index.d.ts` types custom at-rule parser bodies as `Rule[]` for rule-list bodies.
- `src/parser.rs` keeps registered custom at-rules on the custom rule path when parsing custom at-rule bodies, so nested registered rules remain custom rules instead of unknown at-rules.
- `node/test/customAtRules.mjs`, `node/test/visitor.test.mjs`, and `node/test/composeVisitors.test.mjs` are the upstream behavior cluster for custom parser functions and composed visitor traversal.

Native PHP movement:

- `CustomAtRuleTransformer::parseReturnedRuleList()` now emits `['type' => 'custom']` for registered nested custom at-rule statements and blocks inside a returned custom parser rule list.
- This lets one composed visitor return an outer custom at-rule body and a later composed `Rule.custom` visitor consume nested custom declarations.
- The focused WordPress smoke models a build-free `@wp-theme-bundle` wrapper containing nested `@tokens` and a block style that resolves `token(...)` calls from those nested declarations.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed `1 test files, 264 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 5951 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-nested-body.php --self-test` passed with `OK`.
- `php -l` was run on changed PHP files.
- `git diff --check -- lanes/lightningcss` passed.

Status delta:

- Full lane assertion evidence moves from `5945` to `5951`.
- Conservative mapped coverage moves from `2336 / 3532` to `2337 / 3532`.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses `CustomAtRuleTransformer`, the existing custom-rule registry, declaration-list body parsing, function visitors, and composed visitor dispatch.

Non-overlap:

- This slice does not repeat accepted custom at-rule unknown-block token-list parsing, Function/FunctionExit visitor traversal, Ratio/Length/Percentage prelude visitors, or style-rule/custom media/supports visitor behavior. It is scoped to preserving nested registered custom at-rule nodes in returned parser bodies.
