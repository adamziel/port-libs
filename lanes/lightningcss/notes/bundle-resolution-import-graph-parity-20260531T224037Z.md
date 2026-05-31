# LightningCSS Bundle Escaped At-Keyword Import Graph Parity 2026-05-31T22:40Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T224037Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/parser.rs` `TopLevelRuleParser::parse_prelude` matches decoded at-rule names for `import`, `charset`, and `layer` with case-insensitive token comparison.
- Upstream import and layer printers serialize canonical at-rules after parsing, so escaped source spellings are accepted but output is normalized.

## Native Delta

- `CssBundler` now decodes top-level CSS at-keyword identifiers before matching `@charset`, `@layer`, and `@import`.
- `parseImportStatement()` and `validateCharsetStatement()` slice after the raw escaped at-keyword token instead of a fixed literal length.
- `rewriteLayerStatement()` handles escaped layer statement names and canonicalizes them before minification/prefixing.
- The WordPress bundle import graph smoke now covers escaped top-level `@charset`, `@layer`, and `@import` source rules plus escaped import modifiers.

## Evidence

- Red-first probes on the accepted base showed `@\69mport` was emitted literally instead of resolving through the import graph, and escaped `@charset` / `@layer` before an import caused late-import diagnostics.
- `php -l lanes/lightningcss/src/CssBundler.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`: no syntax errors.
- Before patch focused gate: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` passed `1 test files, 358 assertions, 0 failures`.
- After patch focused gate: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` passed `1 test files, 365 assertions, 0 failures`.
- Full lane check: `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 4687 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` exited 0 and printed `escaped-at-keyword-import: resolved`.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

Status delta: focused `CssBundlerTest.php` assertions move `358 -> 365`; lane `phpPass` moves `4680 -> 4687`. Mapped coverage remains conservative at `2173 / 3532` because this deepens the existing import graph/parser cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing native CSS identifier escape decoder, bundle resolver, source provider, import parser, and minifier/layer rewriting components.

## Non-Overlap And Follow-Up

This does not overlap the stale custom-media import-tail rework note; current base already contains that behavior. The follow-up import graph work should target non-overlapping source-map resolver evidence, CSS Modules import/export graph parity, or additional upstream-backed bundle diagnostics.
