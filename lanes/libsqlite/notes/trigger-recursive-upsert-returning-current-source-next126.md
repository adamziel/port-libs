# trigger-recursive-upsert-returning-current-source-next126

Adds `SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan`, a bounded
current-source/next-source plan for recursive UPSERT triggers with `RETURNING`.
The plan drains the current statement and recursive trigger `RETURNING` stream
before exposing the post-current rows as the next source, matching the SQLite
current-source handoff boundary used by recursive trigger statements.

Focused coverage:

- current source rows remain the pre-statement rows;
- next source rows are the post-current rows, including recursive trigger
  inserts/updates already yielded by `RETURNING`;
- statement and recursive `RETURNING` rows are separated by depth;
- skipped `DO NOTHING`/ignore conflicts produce yield diagnostics without
  visible returning rows;
- `recursive_triggers = false`, `RETURNING *`, bad source tokens, malformed row
  lists, missing conflict columns, and max-depth failures are covered.

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-trigger-recursive-upsert-returning-current-source-next126.php --self-test
```

Dependency closure: reuses existing recursive UPSERT trigger and RETURNING
helpers. No new support component is needed.

Non-overlap: this avoids accepted next118 recursive UPSERT current/next result
coverage and next124 recursive FK delete coverage by adding the narrower
current-source handoff/provenance stream that proves `RETURNING` is drained
before the next source starts.
