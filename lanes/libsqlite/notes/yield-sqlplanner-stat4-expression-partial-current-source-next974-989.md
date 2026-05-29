# SQLite planner STAT4 expression partial current source next974-989

- Extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` without adding a numbered duplicate class.
- Carries the prepared `next958-973` current-source STAT4 handoff into `next974-989` only when the projected current row image still matches the prior handoff window.
- Keeps the slice-specific dependency marker, cursor opcode, and handoff signature isolated to `next974-989`.
