# Custom At-Rules Container Visitor Parity - 2026-06-01

## Source truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/index.d.ts` models `Visitor.Rule` and `Visitor.RuleExit` as mapped rule visitor objects for every known `Rule['type']`, excluding only custom/unknown from the mapped object.
- `node/ast.d.ts` defines native `ContainerRule` as `type: "container"` with `name`, `condition`, and `rules`.
- `node/composeVisitors.js` dispatches object visitors by `item.type`, so `Rule.container` and `RuleExit.container` must be reachable like existing `media` and `supports` visitor entries.

## Implementation

- Added native `@container` parsing/traversal to `CustomAtRuleTransformer` instead of routing it through unknown-rule visitors.
- Added `Rule.container` and `RuleExit.container` configuration, composed visitor dispatch, returned-rule serialization, stylesheet serialization, and returned rule-list parsing.
- Added a small `container()` replacement helper for custom visitor returns.
- Kept container condition support bounded to upstream-compatible feature ASTs when parsable and raw condition preservation otherwise.

## Verification

- Baseline before edit: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 393 assertions, 0 failures`.
- After edit: `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` -> no syntax errors.
- After edit: `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> no syntax errors.
- After edit: `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-container-visitor.php` -> no syntax errors.
- After edit: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 399 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-custom-at-rule-container-visitor.php --self-test` -> `OK`.
- Diff check: `git diff --check -- lanes/lightningcss` -> no whitespace errors.

## Dependency closure

No new support component is needed. The slice reuses the existing PHP CSS minifier, returned rule-list parser, declaration/value visitors, and custom at-rule visitor composition machinery.

## Non-overlap

This does not repeat the accepted returned-media exit visitor, supports condition visitor, media query visitor, import media range tail, CSSOM view-transition custom-ident, CSS Modules, source-map, or target-prefixing clusters. Mapped manifest coverage remains conservative at `2374 / 3532`; this patch extends PHP visitor parity for a typed native rule surface.
