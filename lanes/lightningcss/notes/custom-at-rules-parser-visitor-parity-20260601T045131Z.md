# Custom At-Rule Raw Env Spacing Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T045131Z`

Base accepted HEAD: `4a27fd6be5ffa953c7d918d551ebb545b1ce7b8d`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream file: `node/test/visitor.test.mjs`.
- Scenario: `spacing with env substitution`, specifically the raw `EnvironmentVariable` visitor cases that preserve token boundaries across adjacent variables, transform functions, cursor fallback lists, numeric lists, counters, and strings.

## Red-First Evidence

Before this patch, the native PHP custom at-rule visitor path substituted the raw values but the final minifier collapsed upstream-preserved boundaries:

```text
.test{background:var(--foo) var(--bar);border:var(--foo)solid;transform:scale(1.5)scale(1.5);padding:10px 20px;margin:10px auto;outline:red solid;cursor:url(cursor.png) 4 12,auto;stroke-dasharray:5 10 15;counter-increment:myCounter 2;content:"hello"" world"}
```

The missing parity was the space between transform functions, the cursor fallback comma space, and the space between adjacent string tokens.

## Implementation

- `CustomAtRuleTransformer` now tracks declaration properties that received raw `EnvironmentVariable` or `Variable` visitor replacements.
- After the normal minifier pass, the transformer restores only visitor-induced token boundaries for affected `transform`, `cursor`, and `content` declarations.
- The shared `CssMinifier` contract remains unchanged, preserving accepted standalone minifier and CSSOM expectations that intentionally compact those values.
- Added a focused upstream-backed PHP test for the remaining `visitor.test.mjs` raw env spacing cluster.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed: `1 test files, 270 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 6061 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status OK\n";'` passed.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- Focused custom at-rule assertions: `268 -> 270` (`+2`) for this micro-slice.
- `lane-status.json` `phpPass`: `6001 -> 6061`, matching the current full lane test output in this worktree.
- Conservative mapped coverage remains `2340 / 3532`; this deepens the already represented `node/test/visitor.test.mjs` raw env spacing case rather than adding a new denominator row.
- Full upstream Rust/Node/WASM runners were not executed in this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP custom at-rule transformer, structured `env()` / `var()` visitor dispatch, and existing minifier pipeline.

## Non-Overlap

This does not repeat accepted custom at-rule nested returned-body parsing, token-array visitor re-entry, generic-function env substitution for gradient/calc, or the shared CSS minifier transform/cursor compaction behavior. It is scoped to raw structured visitor replacements whose token boundaries must survive the custom at-rule transform pipeline.

## Next Task

Continue upstream custom at-rule visitor parity around remaining parser-level visitor re-entry and raw value boundary cases, especially where returned raw values interact with declaration visitors or nested returned style rules.
