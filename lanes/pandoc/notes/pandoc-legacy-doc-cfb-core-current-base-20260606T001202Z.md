# Pandoc Legacy DOC/CFB Core - OLE Floating Property Scalars

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T001202Z`
Base: `94f038e3d5fdd3910f787431f17ec33eea55aed5`

## Change

- Extended `LegacyDocReader` OLE property-set decoding for bounded scalar
  variants used by legacy DOC custom document properties:
  - `VT_R4` single-precision floats.
  - `VT_R8` double-precision floats.
  - `VT_CY` fixed-point currency values, exposed as four-decimal strings to
    avoid PHP float rounding drift.
  - `VT_DATE` OLE Automation dates, exposed as UTC ISO-8601 timestamps.
- Added a focused legacy DOC/CFB fixture with user-defined custom properties
  for review weight, confidence score, invoice total, and review date.
- Updated the WordPress legacy DOC handoff smoke so import review packets keep
  those scalar custom properties visible without exposing raw OLE bytes.

## Source Truth

The bounded source truth is the OLE Property Set typed-value contract for
`VT_R4`, `VT_R8`, `VT_CY`, and `VT_DATE` values in property-set streams. This
slice ports the scalar decoding contract needed for richer legacy Word metadata
handoff, not a full office suite or external converter.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, online service, or live provider test was
executed.

## Red-First Evidence

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 623 assertions, 0 failures`

After adding the new fixture before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 625 assertions, 1 failures`
  - Failure: the new `customProperties` entries stayed `null` because the
    reader did not yet decode those OLE property types.

## Verification

- `php -l lanes/pandoc/src/LegacyDocReader.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php`
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
- `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both lane JSON files decoded successfully.
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 625 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+2` assertions.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1116 -> 1117`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped inventory:
  `1568 -> 1569`.
- Legacy DOC/CFB core mapped cases: `6 -> 7`.
- Legacy DOC/CFB focused assertions: `38 -> 40`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`CompoundFileBinary`, `LegacyDocReader`, OLE property-set parser, and
WordPress legacy DOC handoff smoke.

Full upstream Pandoc runner parity remains gated on a hydrated Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, Cabal project/package files,
and Haskell Tasty runner dependency closure.

## Non-Overlap

This does not repeat accepted CFB header/FAT/MiniFAT/directory safety,
directory timestamp/CLSID/state-bit provenance, encrypted FIB rejection,
`fExtChar` Unicode text, CLX piece-table and PCD flag handling, FibRgLw97
subdocument boundaries, bookmark/note/comment PLCs, fields, styles/lists/
sections, embedded object/macros reporting, OLE FILETIME/string/bool/int
scalars, unsigned/I8/UI8/CLSID custom properties, DOCX/ODF/EPUB/PDF, charset,
YAML, CSL/BibTeX, XML/HTML5 DOM, syntax highlighting, ZIP/OPC, or
upstream-runner dependency audit slices.

## Follow-Up

Keep OLE blob, clipboard, vector, and negative-currency edge properties;
timezone/local `VT_DATE` ambiguity review metadata; textbox/header/footer
subdocuments; fuller style and section application; embedded object export
policy; encrypted DOC password/decryption behavior; and full upstream Pandoc
runner parity as separate bounded slices.
