# pandoc-legacy-doc-cfb-core-current-base-20260608T101243Z

## Scope

Lane: pandoc
Slice: pandoc-legacy-doc-cfb-core-current-base-20260608T101243Z
Base: e4dbd59f2a9e41851b53f2c90b1fbf72301d4f95

Implemented one bounded legacy DOC/CFB support-library cluster: CFB version 3
directory entries with uninitialized high stream-size DWORDs. Native
`CompoundFileBinary` now reads stream sizes in 512-byte-sector CFB files from
the low DWORD, preserving any ignored high DWORD as review provenance. Version
4 CFB files still use the full 64-bit stream size.

`LegacyDocReader` now surfaces this provenance in `cfbDirectoryEntries`,
`cfbStreamDirectory`, and top-level metadata
`cfbIgnoredStreamSizeHighDwordEntryCount`, so WordPress legacy DOC review
packets can distinguish compatibility parsing from ordinary zero high DWORDs.

## Source Truth

- MS-CFB Compound File Directory Entry:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/60fe8611-66c3-496b-b70d-a504c94c9ace
- MS-CFB Other Directory Entries:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/b37413bb-f3ef-4adc-b18e-29bddd62c26e

The relevant format contract is that version 3 / 512-byte-sector stream sizes
are bounded by the low 32 bits, while current parsers must tolerate older files
whose high DWORD was not initialized. This slice ports that compatibility
behavior without shelling out to any office or conversion tool.

## Evidence

No `port-pandoc-*.needs-lane-rework.md` note existed before editing.

Baseline focused run:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 1272 assertions, 0 failures.

Red-first focused run after adding the v3 high stream-size DWORD expectation
and before implementation:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 1272 assertions, 1 failures. The failure was
`CFB sector chain is not terminated for WordDocument`, because the old parser
treated the v3 high DWORD as part of the stream size and routed a MiniFAT
stream through the regular FAT path.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 1283 assertions, 0 failures.

Syntax checks:

- `php -l lanes/pandoc/src/CompoundFileBinary.php` -> no syntax errors
- `php -l lanes/pandoc/src/LegacyDocReader.php` -> no syntax errors
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php` -> no syntax errors
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` -> no syntax errors

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: legacy doc handoff self-test ok.

JSON validation:

`php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`

Result: both lane JSON files decoded successfully.

Whitespace check:

`git diff --check -- lanes/pandoc`

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `phpPass`: 1611 -> 1612
- `benchmarkDenominator.mapped`: 2030 -> 2031
- `legacyDocCfbCoreCases`: 7 -> 8
- `mappedLegacyDocCfbCoreCases`: 7 -> 8
- `legacyDocCfbCoreAssertions`: 64 -> 75
- Focused assertions: +11 in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, `AstNode` metadata attributes,
`WordPressBlockWriter`, the existing focused CFB/DOC fixture builders, and the
existing WordPress legacy DOC handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Stack, Word,
LibreOffice, zip/unzip, external office tool, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This avoids accepted legacy DOC/CFB clusters for CFB header/FAT/DIFAT/MiniFAT
preflight, DIFAT surplus listing checks, directory start-sector mismatches,
directory timestamps/CLSID/state bits, FIB/CLX and piece-table text extraction,
FibRgLw97 subdocument ranges, DOP metadata, ObjectPool/OLE metadata,
macro-project policy, picture placeholders, PlcfldEdn, field result handoffs,
notes/comments/bookmarks, sections, styles, and lists. The only new behavior
is version 3 high stream-size DWORD compatibility and provenance.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC support surfaces
such as additional DOP policy structures, stylesheet/list metadata, FFData
form-option decoding, hyperlink object payload metadata, route-slip metadata,
or another safe CFB stream validation gap. Full upstream Pandoc runner parity
remains separate because external Pandoc/Haskell/office runners were not
authorized or needed for this bounded support-library case.
