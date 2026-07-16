## Legacy DOC/CFB Bookmark Handoff

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T045145Z`
Base: `91eb954aad0c0c2adbdab2c5485221f409cc00e7`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

### Behavior

This slice adds bounded native parsing for standard legacy Word bookmark tables
in `.doc` CFB files. `LegacyDocReader` now reads bookmark names from
`SttbfBkmk`, start records from `PlcfBkf`, and end CPs from `PlcfBkl` in the
selected table stream. It validates parallel counts, unique `FBKF.ibkl` end
indexes, valid CP ordering, duplicate bookmark names, and the bounded
`SttbfBkmk` string shape before exposing bookmark metadata.

Clean main-text bookmark ranges are handed to the AST as span nodes with stable
anchor metadata, so Markdown and WordPress block output preserve internal
legacy Word link targets. Hidden bookmarks remain marked in metadata and span
classes for reviewer audit.

### Source Truth

The implementation follows the Microsoft MS-DOC bookmark structures:

- `SttbfBkmk` holds unique bookmark names, marks leading-underscore names as
  hidden bookmarks, uses extended strings, and has no extra string data.
- `FBKF.ibkl` indexes the corresponding end record in `Plcfbkl`.
- `Plcfbkl` CPs identify the first character after each bookmark range.

No Pandoc, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, Haskell runner, or online service was used.

### Verification

Baseline before behavior:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 168 assertions, 0 failures
```

Red check after adding the bookmark expectations and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 168 assertions, 1 failures
Failure: undefined/missing returned bookmarks
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 195 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Syntax and JSON checks:

```text
php -l lanes/pandoc/src/LegacyDocReader.php
No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok
```

Whitespace check:

```text
git diff --check -- lanes/pandoc
```

No diff whitespace errors were reported.

Root harness: not run - isolated micro-slice.

### Dependency Closure

No new support component is needed. The slice reuses the existing native CFB
reader, legacy DOC FIB/table-stream parsing path, AST nodes, Markdown writer,
and WordPress block writer.

### Non-Overlap

This does not repeat the recent legacy DOC/CFB work for CFB header preflight,
directory-sector checks, encrypted FIB rejection, fExtChar text decoding, CLX
piece-table decoding, PCD flag validation, field-code hyperlink output, OLE
property metadata, embedded object reporting, macro preflight, or mini-stream
handling. It only owns standard bookmark table parsing and handoff.

### Follow-Up

Potential follow-up is support for nested/overlapping bookmark rendering,
bookmark ranges crossing paragraph boundaries, and bookmark tables for
specialized document parts once those parts are imported into the AST.
