# Consolidate Final Numbered Planner STAT4 Dynamic Planstat4

Consolidated the final prepared handoff cursor metadata for
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` range `990:1005` so
the primary production opcode/mode use the stable final-prepared-handoff names.
The previous duplicated `FinalPreparedHandoffHandoff` opcode and space-bearing
mode remain present as explicit legacy aliases for downstream consumers.

Direct final prepared handoff tests now assert both the canonical cursor labels
and the preserved legacy aliases. Observable status, dependency, receipt, and
fence keys such as `stat4Next958973PreparationFence` and `next958973Prepared`
are unchanged.

Dependency closure: no new support component needed; this reuses the existing
STAT4 expression partial prepared-handoff planner implementation.
