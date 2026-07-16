# SQLite Record Serial-Type Corpus

Slice: `yield-sqlite-release-parity-blocker-burn-down-next`

Base accepted HEAD: `93e8c5b9276d3bf22c095dce1708834c88ddc0a4`

## Behavior

Adds focused native PHP coverage for SQLite record-format serial-type parity:

- integer serial-type boundaries for 0/1, 1/2/3/4/6/8-byte signed integers;
- float, text, BLOB, mixed-record, and large header/serial-varint records;
- UTF-16LE/UTF-16BE text serial byte counts and round trips;
- malformed record guards for outside header sizes, truncated fields, reserved
  serial types 10/11, trailing body bytes, and incomplete varints.

This is upstream file-format behavior coverage only. It does not claim a new
mapped upstream inventory row, does not touch the Application smoke set, and does
not overlap accepted UTF-16 malformed-text guards, B-tree overflow/freelist
work, VFS writer/sync/lock work, SELECT SQL text dispatch, JSON table cursor or
constraint work, or WAL savepoint/checkpoint slices.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecordSerialTypeCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 84 assertions, 0 failures
```

PASS-line delta: `+46`.

`lane-status.json` `phpPass`: `1336 -> 1382`.

`benchmarkDenominator.mapped`: unchanged; this slice adds PHP corpus coverage
without claiming a newly mapped upstream unit.

Dependency closure: no new support component is needed. The slice reuses the
existing native `SQLiteRecord`, `SQLiteVarint`, and UTF-16 fallback code.

Root harness: not run; isolated micro-slice.
