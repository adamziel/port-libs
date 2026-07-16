# Pandoc Legacy DOC/CFB Core Current-Base Slice

- Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T152801Z`
- Base accepted HEAD: `d0b4b38f59138165173e2184c28cc1c5296bac2f`
- Scope: native PHP CFB root-storage color preflight for legacy `.doc` imports.

## Behavior

`CompoundFileBinary` now rejects a CFB Root Entry directory record whose
`colorFlag` is red before directory-tree traversal or stream lookup. This
matches the bounded CFB directory model already enforced for sibling-tree roots:
directory-tree roots must be black, and the root storage entry is not a normal
red-black sibling node that can be red.

The focused test mutates byte 67 of directory entry 0 in a synthetic legacy Word
CFB package and asserts `LegacyDocReader` raises before exposing WordDocument
text. The WordPress legacy DOC handoff self-test includes the same corrupt
package case so red root-storage entries stay explicit import failures.

## Evidence

Red-first before the implementation guard:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1377 assertions, 1 failures
Failure: rejects red CFB root storage entries before stream lookup
Expected exception RuntimeException was not thrown
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1377 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Syntax checks:

```text
php -l lanes/pandoc/src/CompoundFileBinary.php
No syntax errors detected in lanes/pandoc/src/CompoundFileBinary.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1695` -> `1696`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2115` -> `2116`.
- `legacyDocCfbCoreCases`: `7` -> `8`.
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`.
- `legacyDocCfbCoreAssertions`: `64` -> `65`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`CompoundFileBinary`, `LegacyDocReader`, the existing synthetic CFB fixture
builder, and the WordPress legacy DOC handoff smoke. No Pandoc, Word,
LibreOffice, zip/unzip, Cabal solver/build/test command, Haskell runner,
external office tool, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This is limited to root storage color validation. It avoids accepted legacy
DOC/CFB work around header versioning, directory-sector counts, CLSID/state-bit
and timestamp provenance, MiniFAT cutoff handling, surplus DIFAT listings,
directory start-sector validation, FAT marker handling, storage-only stream
metadata, FibRgLw97 subdocuments, Plcfld note/endnote/comment field tables,
route slips, include/prompt/action/nested fields, ObjectPool/OLE metadata,
macros, bookmarks, notes, comments, formatting, list tables, and encryption
preflight.

## Next Task

Choose a non-overlapping native CFB or MS-DOC preflight gap, such as directory
ordering edge cases, malformed property-set sections, or piece-table boundary
diagnostics, while keeping the lane external-tool free.
