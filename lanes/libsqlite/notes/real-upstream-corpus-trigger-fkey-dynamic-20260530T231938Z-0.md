# Real Upstream Corpus Trigger/FK Dynamic Schema Drop

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T231938Z-0`
Base accepted HEAD: `97bde16e3221376c9c3d6c7f9b2330b164322c56`

Added `SQLiteRealUpstreamTriggerFkeyDynamicSchemaDropTest.php`, a
source-neutral application-settings corpus batch covering upstream SQLite
foreign-key behavior around schema changes and DROP TABLE delete-action
semantics.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
  - `fkey2-14.1*`: `ALTER TABLE ADD COLUMN` with `REFERENCES`, `NULL`
    defaults, non-NULL defaults, and foreign-key pragma state.
  - `fkey2-14.2*`: parent table rename rewrites self and child foreign-key
    references across main, temp, and attached schema scopes.
  - `fkey2-14.3*`: `DROP TABLE` interaction with child rows, missing parent
    tables, deferred constraints, and parent-key mismatch/error surfaces.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSchemaDropTest.php`
  - `1 test files, 9985 assertions, 0 failures`
  - `9985` focused PASS lines.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSchemaDropTest.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed.

Dependency closure: no new support component is needed. The batch reuses
existing native PHP trigger/FK delete-action helpers and generic schema
rewrite assertions.

Non-overlap: this avoids the prior accepted trigger/FK dynamic yield batch
(`fkey2-2.*`, `fkey2-4.*`, `trigger2-4.*`, `trigger3-3.*`) and uses only
generic `app_settings`, `setting_id`, `key_name`, `key_value`, and
`load_policy` names.
