# Legacy DOC/CFB Comment Owner Names

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T105113Z`
Base: `b1945db7ea4d891ede7958108123c589e828677e`

## Behavior

`LegacyDocReader` now reads the Word binary `GrpXstAtnOwners` table from the
selected `0Table`/`1Table` stream, parses bounded UTF-16LE XST author names,
and links `ATRDPre10.ibst` comment author indexes to resolved names. Comment
records now carry `authorName`, metadata exposes `commentAuthors` and
`commentAuthorCount`, and WordPress comment-reference spans include
`data-legacy-doc-comment-author-name` when a name is available.

Malformed owner tables fail closed before comment metadata is exposed:

- truncated XST headers;
- XST payloads that point outside the table slice;
- names over the 55-character XST limit;
- duplicate owner names;
- comment descriptors whose author index points outside the owner table.

Source truth:

- Microsoft MS-DOC `FibRgFcLcb97` lists `fcGrpXstAtnOwners` and
  `lcbGrpXstAtnOwners` as the annotation-owner name table offsets and length:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4
- Microsoft MS-DOC `Xst` defines each owner-name string as a length-prefixed
  UTF-16 character array:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/4acc83cc-44b3-4ef7-a2f7-d01d3aecb6a5
- Microsoft MS-DOC `ATRDPre10` defines `ibst` as the annotation author index:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/f2327847-8ba3-4b9c-b9a3-b0bdfac1206c

## Evidence

- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 789 assertions, 0 failures`.
- Red-first before source implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed with `1 test files, 765 assertions, 1 failures` because the fixture
  expected `commentAuthors` metadata that the reader did not yet populate.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 799 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/LegacyDocReader.php`,
  `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` reported no
  syntax errors.

Status delta:

- `lane-status.json` `phpPass`: `1304` -> `1305`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1718` -> `1719`.
- Legacy DOC/CFB mapped cases: `6` -> `7`.
- Legacy DOC/CFB focused assertions: `38` -> `48`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, `WordPressBlockWriter`, in-process CFB
fixtures, and UTF-16LE decoding already present in the legacy DOC support
lane. No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external office tool, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted CFB header/version/directory validation,
directory timestamp/CLSID/state-bit provenance, encrypted FIB rejection,
`fExtChar` Unicode text ranges, FibRgLw97 subdocument boundaries, CLX PCD flag
validation, associated strings, field tables, bookmarks, note/comment PLC
anchors, section/style/formatting/list metadata, ObjectPool references, macro
inventory, small-stream MiniFAT preflight, or WordPress block rendering policy.
The owned behavior is only the bounded MS-DOC annotation owner table and its
handoff to existing comment-reference metadata.

Follow-up should keep annotation bookmark owner ranges such as
`SttbfAtnBkmk`/`PlcfAtnBkf`/`PlcfAtnBkl`, FFData form option decoding, inline
picture extraction policy, password/decryption support, and broader Word layout
parity as separate bounded slices.
