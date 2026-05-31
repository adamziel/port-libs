# real-upstream-corpus-pragma-schema-dynamic-20260531T064233Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T064233Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
- Ported sections: `pragma2-4.1` through `pragma2-4.8` and `pragma2-5.1` through `pragma2-5.2`.

Behavior added:

- `SQLitePragmaDynamicSchemaState` now accepts upstream boolean `YES` and `NO` spellings for dynamic `cache_spill` PRAGMA assignments.
- Added focused real-upstream PHP coverage for unqualified broadcast writes and schema-qualified isolated writes across `main`, `temp`, `aux`, and `archive` schemas.

Focused coverage:

- New test file: `SQLiteRealUpstreamPragmaSchemaDynamicBooleanCorpusTest.php`
- Focused TestRunner cases: 1001
- Behavior assertions: 7758

Non-overlap:

- This does not repeat the accepted pager-state byte-threshold corpus. It fixes the separate dynamic schema PRAGMA state parser and verifies upstream `YES`/`NO` boolean spellings in broadcast and schema-qualified paths.
- This does not add domain-specific source/API names or numeric production suffixes.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded dynamic PRAGMA state helper.
