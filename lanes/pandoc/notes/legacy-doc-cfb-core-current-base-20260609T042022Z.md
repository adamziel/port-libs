# Legacy DOC/CFB Transaction Signature Preflight - 2026-06-09

## Source Truth

The CFB header carries a transaction signature number at offset 52. This lane only supports committed/static legacy DOC compound files; transactional header state is not safe to expose through FAT, directory, MiniFAT, or WordDocument stream traversal.

## Changes

- `CompoundFileBinary::fromBytes()` now rejects nonzero CFB transaction-signature headers before any stream lookup.
- `LegacyDocReaderTest.php` adds a valid baseline fixture plus corrupt-header checks through both `CompoundFileBinary` and `LegacyDocReader`.
- `wordpress-legacy-doc-handoff.php --self-test` includes the same corrupt transaction-signature header in its WordPress DOC import preflight loop.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record +1 mapped legacy DOC/CFB support case, +1 focused PHP PASS line, and +3 focused assertions.

## Verification

- Baseline before edit: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 2059 assertions, 0 failures`.
- Syntax: `php -l lanes/pandoc/src/CompoundFileBinary.php` -> no syntax errors.
- Syntax: `php -l lanes/pandoc/tests/LegacyDocReaderTest.php` -> no syntax errors.
- Syntax: `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` -> no syntax errors.
- JSON: `php -r 'json_decode(...)'` for lane status and manifest -> `pandoc JSON ok`.
- Focused final: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 2062 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` -> `legacy doc handoff self-test ok`.
- Diff whitespace: `git diff --check -- lanes/pandoc` -> passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP CFB parser, legacy DOC reader, focused legacy DOC tests, and WordPress handoff example. Full Pandoc upstream runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted legacy DOC/CFB work for FAT/DIFAT/MiniFAT chain validation, directory-tree invariants, FIB extraction, property sets, field metadata, encryption preflight, or inline object review spans. It adds one bounded CFB header invariant at the current-base parser boundary.

## Follow-Up

Possible next legacy DOC/CFB gaps are additional MS-CFB transaction/state invariants, legacy Word master-document review metadata, or bounded table/shape handoff. Do not use Word, LibreOffice, Pandoc, Haskell runners, external converters, TeX/PDF engines, browser renderers, or online services for those follow-ups.
