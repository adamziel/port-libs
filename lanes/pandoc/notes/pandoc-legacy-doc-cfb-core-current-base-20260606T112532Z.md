# Pandoc legacy DOC CFB current-base: PlcfHdd header/footer stories

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T112532Z`
Base: `76ca6aa79df8c9a30ea9feadacb3937c8d896eaa`

## Source truth

- Microsoft MS-DOC `Plcfhdd`: `https://learn.microsoft.com/en-us/openspecs/office_file_formats/MS-DOC/8f336b7e-66cb-4346-9fd4-88ede9a4a9db`
- Microsoft MS-DOC `Headers`: `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/8465bee7-6c79-45a9-812e-58b0c5fd6cdc`
- Microsoft MS-DOC `FibRgFcLcb97`: `https://learn.microsoft.com/fr-fr/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4`

The bounded source contract used here is: `Plcfhdd` is a CP-only PLC for the header document, the final CP is undefined and ignored, the second-to-last CP terminates the last story at `FibRgLw97.ccpHdd - 1`, and the first six story slots are footnote/endnote separators before six header/footer slots per main-document section.

## Implementation

- `LegacyDocReader` now reads `fcPlcfHdd` / `lcbPlcfHdd`, validates CP ordering and the `ccpHdd - 1` terminator, ignores the final undefined CP, labels separator/header/footer roles, and exposes non-empty story text as `headerFooterStories` metadata only.
- The WordPress legacy DOC handoff fixture now includes a bounded odd-page header story plus guard paragraph through `PlcfHdd`, proving header/footer story text remains out of rendered WordPress blocks.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed before implementation with `1 test files, 799 assertions, 1 failures`.
- Green: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 820 assertions, 0 failures`.
- Smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed.
- Added `+1` mapped legacy DOC/CFB support case and `+21` focused assertions.

## Dependency closure

No new support component was needed. This reused native PHP `LegacyDocReader`, `CompoundFileBinary` fixtures, `WordPressBlockWriter`, and the focused PHP test harness. No Pandoc, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Follow-up

Keep FFData table-property expansion, richer section-linked header/footer inheritance policy, image extraction policy, encrypted DOC password/decryption support, and full upstream Word binary runner parity as separate bounded slices.
