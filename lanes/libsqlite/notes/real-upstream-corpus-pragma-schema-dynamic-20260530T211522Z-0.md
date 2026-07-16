# real-upstream-corpus-pragma-schema-dynamic-20260530T211522Z-0

Implemented a bounded dynamic PRAGMA/schema state helper for real upstream
SQLite PRAGMA behavior that was not covered by the existing focused helpers:

- `pragma.test`: `pragma-1.*` cache/default-cache behavior, `pragma-4.*`
  attached cache behavior, `pragma-8.*` schema/user version behavior, and
  `pragma-15.*` cache preservation across schema reloads.
- `pragma2.test`: `pragma2-1.*` main `freelist_count`, `pragma2-2.*`
  attached `freelist_count`, `pragma2-3.*` read-only no-op writes, and
  cache-size attached/temp isolation.
- `pragma4.test`: schema-version assignment syntax in the schema-PRAGMA
  corpus context.

Focused growth:

- New focused TestRunner PASS cases: 322.
- New focused behavior assertions: 2328.
- Handoff gate: satisfies the real-corpus 500+ behavior assertion floor, but
  does not claim the 1000 distinct PASS-case gate.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaDynamicSchemaState.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaDynamicSchemaState.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaStateDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaStateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaStateDynamicTest.php`
  - `1 test files, 2328 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses the
existing lane TestRunner and adds a small native PHP PRAGMA state helper under
`lanes/libsqlite/src`.

Non-overlap: this slice avoids the already accepted PRAGMA schema4/schema5/
schema6 rowid/introspection/table-valued batches, existing
`SQLitePragmaSchemaDataVersion`, and existing encoding/page/temp-store helper
coverage. It targets cache/default-cache/freelist/version dynamic schema state
from real upstream PRAGMA sections.
