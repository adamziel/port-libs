# Legacy DOC/CFB Exact Stream-Chain Size Preflight - 2026-06-09

## Source Truth

MS-CFB directory stream entries carry the declared byte size for each stream. For safe legacy DOC import, the native parser should expose only the sectors required by that declared size. A FAT or MiniFAT chain that continues past the declared stream length can hide linked bytes outside the stream contract, so this slice rejects those overlong chains before WordDocument stream lookup.

## Changes

- `CompoundFileBinary::regularSectorChainIds()` now rejects regular FAT stream chains whose sector count exceeds `ceil(streamSize / sectorSize)`.
- `CompoundFileBinary::miniSectorChainIds()` now rejects MiniFAT stream chains whose mini-sector count exceeds `ceil(streamSize / miniSectorSize)`.
- `LegacyDocReaderTest.php` adds valid regular/mini baselines plus corrupt overlong FAT and MiniFAT stream-chain fixtures.
- `wordpress-legacy-doc-handoff.php --self-test` adds the same overlong regular and mini stream-chain corrupt CFB cases to the WordPress handoff preflight loop.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record +1 mapped legacy DOC/CFB support case, +1 focused PHP PASS line, and +4 focused assertions.

## Verification

- Baseline before edit: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 2066 assertions, 0 failures`.
- Red-first after adding fixtures only: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 2068 assertions, 1 failures`; the regular overlong stream chain was accepted.
- Focused final: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 2070 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` -> `legacy doc handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CompoundFileBinary` parser, `LegacyDocReader`, focused legacy DOC tests, and WordPress handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat the recent transaction-signature slice, MiniFAT chain-count validation, allocated MiniFAT/root mini-stream bounds, FAT/DIFAT marker validation, directory-tree invariants, FIB extraction, property sets, encryption preflight, or legacy DOC field metadata work. It adds one bounded parser invariant for per-stream declared byte-size exactness across regular and mini streams.

## Follow-Up

Possible next legacy DOC/CFB gaps are additional MS-CFB state invariants, master-document review metadata, or bounded table/shape handoff. Do not use Word, LibreOffice, Pandoc, Haskell runners, external converters, TeX/PDF engines, browser renderers, or online services for those follow-ups.
