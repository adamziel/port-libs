# ATTACH TEMP WAL Schema Trigger Dependency Cookie

## Behavior

Adds `SQLiteAttachTempWalSchemaTriggerPlan::triggerDependencyCookiePlan()` for prepared
trigger invalidation when a TEMP trigger body has unqualified table references
that resolve to different schemas between the current and next schema source.
The slice covers WordPress import/audit triggers where an unqualified
`wp_audit` write resolves to `temp.wp_audit` while the current trigger keeps
running, then resolves to `main.wp_audit` after TEMP schema reload. It also
keeps qualified attached-schema body references in the dependency set so WAL
page-one schema-cookie changes for `site` expire the prepared trigger.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerDependencyCookieTest.php`
  - `1 test files, 58 assertions, 0 failures`
  - `58` PASS lines
- WordPress smoke:
  - `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-trigger-dependency-cookie.php --self-test`

## Non-Overlap

This does not repeat accepted trigger-cache coverage for plain
record changes, temp trigger shadowing, or WAL page-one schema cookies. The new
behavior is the resolved body-dependency schema move for TEMP trigger SQL
(`temp` to `main`) combined with attached-schema WAL cookie expiry.

## Dependency Closure

No new support component is needed. The implementation reuses the lane-local
attached schema catalog, schema records, and existing WAL schema-cookie state
arrays.
