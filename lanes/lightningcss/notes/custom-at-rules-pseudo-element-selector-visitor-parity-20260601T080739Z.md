# Custom At-Rule Pseudo-Element Selector Visitor Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T080739Z`

Base accepted HEAD: `0b8c08e6264b7332840b1960ce9f5a694bcdbc84`

## Source Truth

- Pinned upstream LightningCSS cache commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/index.d.ts` exposes `Selector?(selector: Selector): Selector | Selector[] | void`.
- `node/ast.d.ts` defines selector components with `type: "pseudo-element"` and builtin pseudo-element forms including `before`, `after`, `marker`, `part` with `names`, and `slotted` with `selector`.

## Behavior Ported

- Existing CSS selectors now expose `::before`, `::marker`, `::part(...)`, `::slotted(...)`, and cue function forms to `Selector` visitors as upstream-shaped `pseudo-element` components instead of a raw colon plus pseudo-class.
- Returned style-rule selector ASTs now serialize upstream-shaped pseudo-element components, including `marker` and `part` names.
- Selector component serialization also accepts upstream combinator values such as `child`, `next-sibling`, and `later-sibling`, while preserving the existing descendant/literal forms.
- ID selector components are now parsed for `Selector` visitors, matching the existing returned-selector parser behavior.

## Focused Evidence

- Before this slice, `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed `1 test files, 317 assertions, 0 failures`.
- After this slice, `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed `1 test files, 322 assertions, 0 failures`.
- Full lane check passed: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6880 assertions, 0 failures`.
- Changed example smoke passed: `php lanes/lightningcss/examples/wordpress-custom-at-rule-pseudo-element-selector-visitor.php --self-test` -> `OK`.

## Status Delta

- `lane-status.json` `phpPass` moves `6875 -> 6880`.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the already represented custom at-rule visitor/selector AST cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CustomAtRuleTransformer`, selector visitor normalization, declaration serializer, and focused lane test harness.

## Non-Overlap

This does not repeat accepted bundle/import graph, source-map, CSS Modules, media-query, property-value, target-prefix, token-list, transform/ratio/unit/image/url/env/var, style-rule, media/supports, StyleSheet, or RuleExit custom at-rule visitor clusters. The patch is scoped to pseudo-element selector AST parity and returned selector serialization.

## Next Task

Continue custom at-rule parity only on non-overlapping upstream visitor AST shapes or parser recovery gaps; otherwise prioritize current-base source-map, CSS Modules, bundle/import graph, media query, property value, CSSOM, and target-prefix slices.
