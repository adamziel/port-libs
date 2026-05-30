# real-upstream-corpus-json1-jsonb-dynamic-20260530T171513Z-0

Accepted base: `6a6cf1aff10d18a35ed78eace2a787cb40f2b02d`.

## Scope

Extended `SQLiteRealUpstreamJsonDynamicCorpusTest.php` with non-overlapping real upstream JSON corpus scenarios:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
  - `json501-5.1` through `json501-5.5`: JSON5 line-continuation strings.
  - `json501-6.1` through `json501-6.8`: JSON5 string escapes, including `\xNN` escape preservation.
  - `json501-7.2`, `7.3`, `7.5`, `7.6`: signed hexadecimal numbers.
  - `json501-8.2`, `8.3`, `8.4`, `8.5`, `8.9`, `8.10`, `8.11`: signed decimal and exponent forms.
  - `json501-9.2`, `9.3`, `10.1`, `11.1`, `12.1` through `12.4`, `13.1`, and `14.1` through `14.31`: infinities, plus signs, comments, extended whitespace, single-quoted strings, and control-character JSON5 strings.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`
  - `json502-3.1`, `3.2`, `3.4`, `5.1`, `5.2`, `5.3`: escaped labels, quoted paths, patch/extract behavior, and select-expression dispatch.

## Evidence

- Patched focused command:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicCorpusTest.php`
  - Result: `1 test files, 972 assertions, 0 failures`, `13` PASS lines.
- Accepted-base comparison:
  - `git show HEAD:lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicCorpusTest.php > /tmp/SQLiteRealUpstreamJsonDynamicCorpusTest.base.php`
  - temporary repo-relative copy run with `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicCorpusBaseTmpTest.php`
  - Result: `1 test files, 745 assertions, 0 failures`, `10` PASS lines.
- Focused delta: `+227` assertions and `+3` PASS lines.
- Mapped denominator rows: unchanged. This is behavior/assertion growth over existing real upstream JSON corpus mapping.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP JSON5, JSONB, JSON canonicalization, JSON mutation, JSON patch, JSON extract, JSON validity, and select-expression dispatch components.

## Non-Overlap

This slice avoids accepted JSON merge-patch, JSONB remove, JSON visible/hidden constraint pushdown, JSON table cursor/source wiring, and JSON table generated path/rowid cost surfaces. It adds lexical/path/control-character JSON5 and escaped-label behavior from upstream `json501.test` and `json502.test`.
