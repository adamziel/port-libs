# real-upstream-corpus-json1-jsonb-dynamic-20260530T210631Z-0

Base accepted HEAD: `140c9861a340b8e75fdc8ea93863883edb030323`.

## Upstream Sources

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
  - `json105-1.10` through `json105-1.110`: `json_extract()` with `[#]`, `[#-N]`, leading-zero reverse indexes, nested reverse indexes, and multi-path extraction.
  - `json105-2.10` through `json105-2.140`: `json_remove()` no-op append marker, reverse-index removals, nested reverse-index removals, and left-to-right multi-path removal.
  - `json105-3.10` through `json105-5.80`: `json_insert()`, `json_set()`, and `json_replace()` append and reverse-index behavior.
  - `json105-6.10` through `json105-6.50`: malformed `[#...]` JSON path rejection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
  - `jsonb01-1.2.1` through `jsonb01-1.2.18`: JSONB `jsonb_remove()` parity for object members, array indexes, append marker no-ops, and reverse indexes.

## Added Coverage

Added `SQLiteRealUpstreamJsonAppendReverseIndexCorpusTest.php`, a dynamic real-corpus PHP port of the upstream `[#]` and `[#-N]` behavior cluster.

The test expands the upstream examples across 24 nested application JSON documents and covers:

- text JSON and JSONB `json_extract()` parity for append markers and reverse indexes;
- text JSON and JSONB `json_remove()` parity, including ordered multi-path removal;
- `json_insert()`, `json_set()`, and `json_replace()` append-marker behavior;
- reverse-index `json_set()` and `json_replace()` behavior;
- malformed append/reverse-index path rejection.

Focused local result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonAppendReverseIndexCorpusTest.php
1 test files, 1709 assertions, 0 failures
```

Expected dashboard movement: `+1709` focused TestRunner PASS lines if accepted as non-overlapping corpus growth. Mapped denominator coverage stays `1589 / 1589`.

## Non-Overlap

This slice does not repeat the latest accepted JSON table/source/cursor/visible-constraint coverage or the earlier json101 constructor/mutation dynamic corpus. It specifically ports upstream `json105.test` append/reverse-index paths plus `jsonb01.test` JSONB remove parity.

## Dependency Closure

No new support component is needed. The test reuses existing native PHP `SQLiteJsonB`, `SQLiteJsonExtract`, `SQLiteJsonMutation`, and `SQLiteJsonRemove` behavior.
