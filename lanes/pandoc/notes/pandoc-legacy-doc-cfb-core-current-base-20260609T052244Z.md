# Legacy DOC/CFB Directory Start-Sector Sentinels - 2026-06-09

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T052244Z`
Base accepted HEAD: `aeac7627505caef0c7f45b74c533b70ec36e1807`

## Behavior

`CompoundFileBinary` now rejects malformed CFB directory start-sector sentinel
values before `LegacyDocReader` exposes WordDocument text or metadata:

- storage directory entries must use `ENDOFCHAIN` as their start sector;
- zero-length stream entries must use `ENDOFCHAIN` as their start sector;
- the root storage must use `ENDOFCHAIN` when the root mini stream is empty.

The focused tests cover `FREESECT`, `FATSECT`, and `DIFSECT` in each of those
directory-entry positions.

## Source Truth

MS-CFB directory entries use the start-sector field for stream data chains and
root mini-stream data. Storage objects and absent/empty streams are represented
with the `ENDOFCHAIN` sentinel rather than other reserved sector markers.

## Verification

Baseline before adding the focused assertions:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2126 assertions, 0 failures
```

Red-first after adding the focused assertions and before the source guard:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2127 assertions, 1 failures
Expected exception RuntimeException was not thrown
```

Final focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2135 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2368` -> `2369`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2762` -> `2763`.
- `legacyDocCfbCoreCases`: `7` -> `8`.
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`.
- `legacyDocCfbCoreAssertions`: `64` -> `73`.
- Focused `LegacyDocReaderTest.php`: `2126` -> `2135` assertions.

## Dependency Closure

No new native PHP support component is needed. This reuses the existing
`CompoundFileBinary` parser, `LegacyDocReader` fixture coverage, focused PHP
test runner, and lane-local WordPress legacy DOC handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, TeX/PDF engine, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This is directory-entry start-sector sentinel validation only. It does not
repeat the accepted MiniFAT/DIFAT header-start sentinel slice, root mini stream
without MiniFAT metadata guard, small-stream MiniFAT cutoff guard, FAT/DIFAT
allocation ownership checks, CFB directory tree hygiene, OLE property-set
directory guards, encrypted DOC/FIB rejection, field-code provenance, or inline
picture metadata slices.

Good follow-up legacy DOC/CFB slices: Word table/list expansion, additional
property-set vector edge cases, or another CFB allocation invariant not covered
by directory start-sector sentinels.
