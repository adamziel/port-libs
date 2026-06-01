# Custom At-Rule RuleExit Traversal Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T022635Z`

Upstream source truth:

- Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/visitor.rs` keeps the default rule visitor path as child traversal when the visitor does not replace the rule.
- `napi/src/transformer.rs` runs rule enter visitors, then `visit_children`, then `RuleExit`; direct native NAPI probing at the pinned checkout showed a generic `RuleExit` on `@media` observed a declaration value after a `Length` visitor rewrote `16px` to `1rem`.

Implemented behavior:

- `processMediaRule()` now builds the `RuleExit` payload from the visited media query and visited body CSS, so generic or media-specific `RuleExit` callbacks see child value/prelude visitor effects.
- Custom at-rules without a visitor replacement now process their block children before `RuleExit`, expose the visited body AST/body rules/declarations to `RuleExit`, and serialize the visited prelude/body instead of falling back to the original parser text.
- Custom at-rule statements without block bodies now serialize visited preludes when no `RuleExit` replacement is returned.

Focused verification:

- Before implementation, a local PHP probe showed `RuleExit` on `@media (hover){.card{width:16px}}` saw the first declaration raw value as `16px` even though the final CSS was `1rem`.
- After implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed with `1 test files, 224 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`, `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`, and `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-exit-traversal.php` passed.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-exit-traversal.php --self-test` exited 0 and printed `@tokens wp-theme{.wp-block-card{width:1rem}}@alias wp-accent;`.
- `git diff --check -- lanes/lightningcss` passed.

Non-overlap and rework note:

- The stale custom-media rework note under `.tmux-team/tmp/handoff-candidates/port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md` targets old `CustomMediaTransformer.php` import-tail conflict handling and is unrelated to this custom at-rule visitor traversal slice.
- This patch does not change CSS Modules, bundle/import graph, source maps, target prefixing, media parser recovery, or custom-media substitution logic.

Dependency closure:

- No new support component is needed. The patch reuses the existing native PHP custom at-rule parser, visitor, declaration processor, rule-list parser, and minifier paths.
