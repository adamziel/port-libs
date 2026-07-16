# Custom At-Rule Indexed Env Visitor Parity 2026-06-01T08:42Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`.

Upstream evidence:

- `src/lib.rs::test_environment` includes `env(--branding-small 1)` and
  `env(--branding-small 1, 20px)` minification cases.
- `src/properties/custom.rs::EnvironmentVariable::parse_nested` parses a
  variable name, then zero or more integer indices, then optional fallback.
- `node/ast.d.ts` exposes `EnvironmentVariable.indices?: number[]`, and
  `node/index.d.ts` exposes `EnvironmentVariable` / `EnvironmentVariableExit`
  visitors.

Native PHP delta:

- `CustomAtRuleTransformer` now parses `env()` names separately from integer
  indices before visitor lookup.
- Exact-name `EnvironmentVariable` callbacks for `--branding-small` and
  `--branding-padding` now fire for indexed forms such as
  `env(--branding-small 1, 20px)`.
- Indexed environment variables serialize back as `env(<name> <indices>,
  <fallback>)`, and `DashedIdent` visitors rewrite only the dashed name, not
  the index suffix.
- `wordpress-custom-at-rule-variable-exit-visitor.php` now models an indexed
  WordPress block breakpoint env token and verifies the visitor sees
  `indices: [1]` plus the `640px` fallback.

Red-first probe before implementation:

`php -r 'require "tools/bootstrap.php"; use PortLibs\LightningCSS\CustomAtRuleTransformer; $seen=[]; $out=(new CustomAtRuleTransformer())->transform("@media (max-width: env(--branding-small 1, 20px)) { .card { padding: env(--branding-padding 2, 20px); } }", [], ["EnvironmentVariable" => ["--branding-small" => static function(array $env) use (&$seen): array { $seen[]=$env; return ["type"=>"length","value"=>["unit"=>"px","value"=>600]]; }, "--branding-padding" => static function(array $env) use (&$seen): array { $seen[]=$env; return ["type"=>"length","value"=>["unit"=>"px","value"=>20]]; }]]); echo $out, "\n", json_encode($seen), "\n";'`

Output before the fix kept both original `env()` calls and printed `[]` for
seen variables, proving exact-name visitor lookup missed indexed env names.

Verification:

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-variable-exit-visitor.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  passed with `1 test files, 326 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-variable-exit-visitor.php --self-test`
  printed `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed with
  `13 test files, 6988 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

Coverage accounting:

- Local LightningCSS PHP assertions move from `6984` to `6988`.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the
  already represented `test_environment` and custom at-rule
  `EnvironmentVariable` visitor clusters rather than adding a new denominator
  row.

Dependency closure:

- No new support component is needed. This reuses `CustomAtRuleTransformer`,
  the existing top-level splitter, visitor composition, `DashedIdent` visitor
  plumbing, and lane-local value serialization. No Node, Rust, WASM, browser,
  network, or external parser dependency is introduced.

Non-overlap:

- This does not repeat accepted direct env/var visitor replacement,
  EnvironmentVariableExit/VariableExit, raw env spacing, token-list prelude
  traversal, MediaQueryParser env range minification, CSS Modules env dashed
  identifiers, or bundle env dependency graph work. It is limited to indexed
  `env()` parser/visitor parity inside the custom at-rule transformer.

Root harness: not run - isolated micro-slice.
