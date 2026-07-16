# Legacy DOC CFB Core Current Base: Pms Mail-Merge Settings

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T020812Z`
Base accepted HEAD: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`

## Source Truth

- Microsoft MS-DOC Pms structure: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/9f353df1-43a4-42c7-8d76-3e545d75401a
- The slice ports the bounded format contract for `FibRgFcLcb97.fcPms/lcbPms`: `WPMS`, `ipmfMF`, `ipmfFetch`, `iRecCur`, two PMFS source records, `Rfs`, optional Unicode SQL query, and optional `SttbfRfs` filter strings.

## Implementation

- Added native Pms parsing in `LegacyDocReader` without invoking Word, LibreOffice, Pandoc, zip/unzip, online services, or external template/rendering tools.
- Links PMFS external filename references back to the existing `SttbFnm` metadata records by FNPI, carrying path, basename, document index, filesystem flags, and the metadata-only byte exposure policy.
- Exposes mail-merge document type, destination, selected source indexes, source flags, separator token names, SQL query text, and filter strings on the return payload, document attrs, and metadata.
- Rejects malformed Pms packets before exposing partial state: bad source indexes, reserved current-record sentinels, odd/truncated/non-terminated SQL query byte counts, active empty source records, unknown source flag bits, and linked source records without a matching `SttbFnm` entry.
- Updated `wordpress-legacy-doc-handoff.php --self-test` to include a Pms packet and assert that SQL/filter/source metadata remains out of visible WordPress blocks.

## Verification

Red-first:

```bash
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
```

Result: `1 test files, 1932 assertions, 1 failures`; failure was the new Pms test observing missing `mailMergeSettings`.

Focused green:

```bash
php -l lanes/pandoc/src/LegacyDocReader.php
php -l lanes/pandoc/tests/LegacyDocReaderTest.php
php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
python -m json.tool lanes/pandoc/lane-status.json
python -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
git diff --check -- lanes/pandoc
```

Result: syntax OK for changed PHP files; both lane JSON files validate; focused test passed with `1 test files, 1972 assertions, 0 failures`; WordPress example smoke printed `legacy doc handoff self-test ok`; diff hygiene produced no output.

## Dependency Closure

No new support component is needed. This reuses the existing CFB reader, FIB table-stream slicing, UTF-16LE/STTB parsing helpers, and `SttbFnm` external filename metadata. Full upstream Pandoc runner parity remains out of scope for this implementation slice.

## Non-Overlap

This does not touch recent accepted legacy-DOC work for master subdocument references, unallocated CFB directory entry hygiene, root storage timestamps, `DATA` mail-merge redirect fields, `MERGEFIELD` source handoff, `SttbFnm`, `SttbfAssoc`, route slips, field spans, or ObjectPool/media metadata. The new behavior is limited to document-level Pms mail-merge settings.
