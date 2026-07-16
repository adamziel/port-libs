# Real Upstream Corpus Window Functions Dynamic 20260530T174452Z-0

Status: added a focused real-upstream window value-function batch for dynamic `nth_value()`, `first_value()`, and `last_value()` over full `BETWEEN` frame boundaries.

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`: sections `10.0`, `10.1`, `10.2`, and `11.3`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`: generated value-function sections `1.20.9`, `1.20.10`, and `1.20.11`.

Implemented behavior:
- `SQLiteWindowFunction::valueFrameBetweenValues()` applies `ROWS`, `RANGE`, and `GROUPS` `BETWEEN` boundaries to `first_value`, `last_value`, and `nth_value`.
- It supports scalar or per-row dynamic `nth_value()` indexes, `EXCLUDE` modes, and optional `FILTER` rows while preserving existing window frame validation.

Expected movement: focused PASS-line growth only. This claims no new mapped denominator row and uses generic application data, not domain-specific API names.

Dependency closure: no new support component is needed; the slice reuses the existing native PHP window frame boundary, peer-group, exclude, and filter machinery.

Non-overlap: this avoids the accepted `window1`/`window2` aggregate frame batches, existing `window4` ranking/lead/lag dynamic corpus, row-value RETURNING window handoffs, compound recursive window admission helpers, JSON aggregate windows, and suite-evidence metadata. The new surface is value window functions over parsed frame boundaries with dynamic row-specific `nth_value()` indexes.
