# Dolt Check Constraint Information Schema Evidence 20260523T033824Z

- Date: 2026-05-23 UTC
- Session: `port-dolt`
- Scope: Dolt lane native PHP slice for bounded check-constraint validation plus `information_schema` constraint metadata exposure.
- Upstream source: `.upstream-cache/dolt` at `b2274926e0dcd84aab000ee242df5b5e75689eef`

## Upstream Evidence

- Reviewed `integration-tests/bats/sql-check-constraints.bats`.
  - `basic tests for check constraints` accepts valid rows, rejects `a > 3` and `b > a` violations, allows `NULL` comparison results, and stops enforcing a check after `ALTER TABLE ... DROP CONSTRAINT`.
  - `check constraints survive adding a new column` asserts `information_schema.CHECK_CONSTRAINTS` exposes `def,foo_chk_rvgogafi,(`c1` > 3)`.
- Reviewed `integration-tests/bats/sql-create-tables.bats`.
  - `tables should not reuse constraint names` asserts copied-table CHECK constraints have two distinct names in `information_schema.table_constraints`.

## Commands

```bash
env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 20m bats --filter 'sql-check-constraints: (basic tests for check constraints|check constraints survive adding a new column)' sql-check-constraints.bats
```

Result: exit `0`, plan `1..2`, `2` passed, `0` failed, `0` skipped.

```bash
env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'sql-create-tables: tables should not reuse constraint names' sql-create-tables.bats
```

Result: exit `0`, plan `1..1`, `1` passed, `0` failed, `0` skipped.

```bash
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $r=new TestRunner(); foreach (glob("lanes/dolt/tests/*Test.php") as $f) { $r->runTests(require $f, $f); } fwrite(STDOUT, "\nDolt: " . count(glob("lanes/dolt/tests/*Test.php")) . " test files, " . $r->assertions() . " assertions, " . $r->failures() . " failures\n"); exit($r->failures() === 0 ? 0 : 1);'
```

Result: exit `0`, `17` Dolt test files, `180` behavior tests, `901` assertions, `0` failures.

```bash
php tools/run-tests.php
```

Result: exit `0`, `180` test files, `17,431` assertions, `0` failures.

## Native Slice

- Added `PortLibs\Dolt\CheckConstraintValidator` for a bounded upstream-aligned expression subset: comparisons, `IN`, `IS NULL`, `IS NOT NULL`, `AND`, `OR`, SQL-style unknown `NULL` results, and skipped `NOT ENFORCED` checks.
- Added `PortLibs\Dolt\InformationSchema` projections for `CHECK_CONSTRAINTS` and `TABLE_CONSTRAINTS` rows.
- Added `wp_import_audit` fixture/example that exposes WordPress import audit CHECK metadata and invalid status rows before migration promotion.

## Exclusions

- No full `go test ./...`.
- No full BATS directory.
- No live service, SQL server, hosted database, network remote, cloud credential, Docker, Hadoop/parquet, or benchmark suite.
- No non-Dolt lane files were edited for this slice.
