2026-05-29T20:05:57Z - consolidate-final-numbered-methods-json-table-ninety-fifth-pass

- Consolidated the JSON table generated path rowid range/offset private helper names in `SQLiteJsonTablePlan` by removing the worker-number suffixes from the canonical production method names.
- Preserved observable `next209`/`next210` array keys, dependency strings, reader policies, opcodes, cost classes, and replan reason labels because existing JSON table tests assert those values.
- No new dependency/support component is needed; this is a production helper-name cleanup inside the existing JSON table planner implementation.
- Non-overlap: avoided accepted JSON table hidden-rowid/generated-path handoffs by touching only the private range/offset helper symbols and keeping the JSON table domain behavior unchanged.
