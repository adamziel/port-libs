# real-upstream-corpus-json1-jsonb-dynamic-20260530T235934Z-0

Base accepted HEAD: `8c83cd38b21e6ef37afec24c7a1c1aa06c561658`.

Scope: real upstream SQLite JSON1/JSONB dynamic behavior from the hydrated upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
  - `json102-440` through `json102-500` removal order, missing index, huge index, object member, and root-removal behavior.
  - `json102-380` through `json102-400` string-vs-JSON-value mutation boundaries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
  - `json105-2.*` reverse-index remove semantics.
  - `json105-3.*`, `json105-4.*`, and `json105-5.*` append-slot and reverse-index insert/set/replace semantics.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`
  - `json109-1.*` `json_array_insert()` insert ordering and reverse-index behavior.
  - `json109-2.*` missing-path creation and scalar-member path rejection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
  - `jsonb01-1.2.*` JSONB remove parity for nested object and reverse array paths.

New focused PHP coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicReverseAppendCorpusTest.php`
- 5 distinct TestRunner PASS cases.
- 12,541 behavior assertions.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicReverseAppendCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicReverseAppendCorpusTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicReverseAppendCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS real upstream json102 json105 jsonb01 remove reverse index corpus text and jsonb parity
PASS real upstream json102 json105 append and reverse mutation corpus text and jsonb parity
PASS real upstream json109 array insert dynamic corpus text and jsonb parity
PASS real upstream json109 array insert rejects scalar member paths before mutation
PASS real upstream JSON1 JSONB reverse append corpus cites hydrated upstream sources

1 test files, 12541 assertions, 0 failures
```

Non-overlap: this batch does not add metadata-only admission rows and does not repeat the accepted JSON102 mutation matrix, JSON109 bulk insert, JSON visible/hidden constraint pushdown, JSON table cursor/source wiring, or JSON host-join clusters. It focuses on reverse-index removal, append-slot mutation, array-insert ordering, text/JSONB parity, and invalid path behavior.

Dependency closure: no new support component is required. The slice reuses existing native PHP JSONB, JSON mutation, JSON remove, array insert, canonicalization, and path parsing components.
