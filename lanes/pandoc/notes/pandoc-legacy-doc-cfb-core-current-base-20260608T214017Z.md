# Pandoc Legacy DOC/CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T214017Z`

Accepted base: `27517e184202c06059ca99a4301cb076b15f94ae`

## Behavior

`CompoundFileBinary` now rejects active CFB directory-entry names that are not
valid UTF-16LE before stream lookup. The previous decoder used an ignoring
conversion path, which could turn a malformed `WordDocument` name containing an
invalid surrogate into a valid lookup key and expose legacy DOC text.

This keeps the legacy DOC handoff fail-closed at the package boundary: malformed
directory names cannot be normalized into real streams before `LegacyDocReader`
extracts `WordDocument`, metadata, macros, embedded-object records, or review
spans.

Source truth:

- Microsoft MS-CFB directory entries store the name as a Unicode string plus a
  byte count in the directory entry name field:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/60fe8611-66c3-496b-b70d-a504c94c9ace
- Microsoft MS-CFB requires stream and storage objects to be discovered through
  the directory-entry tree before stream lookup:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/b37413bb-f3ef-4adc-b18e-29bddd62c26e

## Evidence

Baseline focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1668 assertions, 0 failures
```

Red-first focused check after adding the malformed UTF-16LE directory-name
assertion:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1669 assertions, 1 failures
```

Failure reason: malformed active CFB directory-entry UTF-16LE was silently
accepted and normalized back into `WordDocument`.

Final focused check after the parser change:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1669 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Syntax checks:

```text
php -l lanes/pandoc/src/CompoundFileBinary.php
php -l lanes/pandoc/tests/LegacyDocReaderTest.php
php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
```

Diff hygiene:

```text
git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1876` -> `1877`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2301` -> `2302`.
- `legacyDocCfbCoreCases`: `7` -> `8`.
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`.
- `legacyDocCfbCoreAssertions`: `64` -> `65`.
- Focused `LegacyDocReaderTest.php`: `1668` -> `1669` assertions.

## Dependency Closure

No new native PHP support component is needed. This reuses `CompoundFileBinary`,
`LegacyDocReader`, the existing CFB fixture builders, focused
`LegacyDocReaderTest.php`, and the WordPress legacy DOC handoff smoke.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal solver/build/test command,
Haskell runner, external office tool, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This slice is limited to strict active directory-entry UTF-16LE name preflight.
It avoids accepted legacy DOC/CFB work around CFB header signature/version,
directory-sector counts, root identity/color, directory name length/terminator/
padding/embedded-null checks, directory reachability/sorting/red-black
validation, FAT/DIFAT/MiniFAT allocation, MiniFAT cutoff, stream-sector
overlap, directory start-sector mismatches, stream storage-only metadata, FIB
encryption and text-range flags, CLX piece tables, FibRgLw97 subdocument
boundaries, Plcfld field tables, bookmarks, notes/comments, sections, styles,
lists, fields, ObjectPool/OLE metadata, macros, route slips, and property sets.

## Next Task

Keep follow-up legacy DOC/CFB work bounded to non-overlapping native preflight
or metadata edges, such as unused directory-entry field policy, version-3 stream
size bounds, or master-document/subdocument metadata. Keep external office
tools and Pandoc/Haskell runners out of this lane unless explicitly authorized.
