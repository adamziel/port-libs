# ATTACH WAL Temp Schema View Cache Reprepare

Status: ready isolated handoff.

This slice adds `SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan()` for
prepared view-cache reset behavior across temp, main, and attached schemas.
It composes the existing view dependency cache with per-prepared-view source
schemas and active-reader state:

- active prepared views whose source dependencies changed keep returning
  `SQLITE_OK` until reset, then need `SQLITE_SCHEMA`;
- inactive stale views report `SQLITE_SCHEMA` on the next step;
- qualified main views stay reusable when temp schema records change;
- unrelated attached schema replacement and WAL schema-cookie-only changes are
  recorded without expiring unrelated prepared view dependencies;
- committed page-one WAL schema cookies are tracked, while uncommitted or
  non-page-one frames do not advance schema cookies.

Application relevance: copied `wp_options` import flows often use temp staging
views while main and attached site databases remain open in WAL mode. The
example shows active temp view readers finishing their current source, a stale
site view requiring reprepare on next step, and a qualified main view remaining
stable across temp writes.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempViewCachePlan.php
php -l lanes/libsqlite/tests/SQLiteAttachWalTempSchemaViewCacheReprepareTest.php
php -l lanes/libsqlite/examples/application-attach-wal-temp-schema-view-cache-reprepare.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempSchemaViewCacheReprepareTest.php
php lanes/libsqlite/examples/application-attach-wal-temp-schema-view-cache-reprepare.php --self-test
git diff --check -- lanes/libsqlite
```

Focused output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 63 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `31014 -> 31077` from 63 newly verified focused PASS lines.
- `phpFail`: unchanged at `0`.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior over
  an already mapped ATTACH/schema-cache family.

Non-overlap: this avoids accepted ATTACH WAL/temp rollback routing,
schema-cache current-next, temp/main WAL view-cache, and the
batch76/77/79 attach overlap. The new behavior is limited to prepared-view
current-source reset and next-step `SQLITE_SCHEMA` decisions.

Dependency closure: no new support component is needed. The slice reuses the
existing attached schema catalog, schema records, view dependency cache, and
WAL schema-cookie planning.
