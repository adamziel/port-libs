2026-05-29 consolidate-final-numbered-methods-attach-schema-sixty-second-pass

Scope:
- Renamed schema reparse production classes/files that still carried the generated
  CurrentSourceNextPlan suffix into stable canonical Plan names.
- Renamed the direct attach temp WAL schema-cache indexed-by expiry and DDL
  dedup tests/examples away from current-source-next116/current-source-next117
  filenames and local numbered helper variable names.

Evidence:
- Focused tests and example self-tests are run in the worker handoff output.
- No new support component is needed; this is a production/test/example naming
  consolidation over existing libsqlite attach/schema behavior.

Non-overlap:
- This slice does not add new functional coverage and does not duplicate recent
  WAL, B-tree, JSON, planner, or VFS behavior work. It is limited to the direct
  attach/schema consolidation cleanup target.
