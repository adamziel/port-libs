# SQLite planner STAT4 expression partial current source next990-1005

- Extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` without adding a numbered duplicate class.
- Carries the prepared `next974-989` current-source STAT4 handoff into `next990-1005` only when the projected current row image still matches the prior handoff window.
- Keeps the slice-specific dependency marker, cursor opcode, and handoff signature isolated to `next990-1005`.
