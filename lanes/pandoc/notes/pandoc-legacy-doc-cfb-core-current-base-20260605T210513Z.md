# Legacy DOC/CFB black-height validation

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T210513Z`
Base: `7c29ef5120ff9b34ad99f493fba6349d008f8e1e`

## Behavior

Compound File Binary directory sibling trees are red-black trees. The existing
native CFB parser already rejected invalid directory object types, duplicate or
out-of-order sibling names, sibling cycles, invalid color flags, a red tree
root, and consecutive red nodes. This slice adds the remaining bounded
structural invariant needed before legacy DOC stream lookup: every sibling
subtree must have equal black height.

`CompoundFileBinary::collectDirectoryTree()` now returns each subtree black
height, treats free sibling references as black leaves, and rejects unequal
left/right black-height pairs with a deterministic CFB preflight error. The
focused legacy DOC CFB fixture builders now emit valid red/black colors for
multi-entry sibling trees, so the new parser check protects malformed inputs
without weakening existing valid .doc fixtures.

## Evidence

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 564 assertions, 0 failures`.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 565 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed with `legacy doc handoff self-test ok`.

Status delta:

- `lane-status.json` `phpPass`: `1075` -> `1076`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1527` -> `1528`.
- Legacy DOC/CFB mapped cases: `6` -> `7`.
- Legacy DOC/CFB assertions: `38` -> `39`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`CompoundFileBinary`, `LegacyDocReader`, and WordPress legacy DOC handoff
support rows. No Pandoc, Word, LibreOffice, zip/unzip, external office tool,
online service, Cabal solver/build/test command, or Haskell runner was
executed.

## Non-Overlap

This does not repeat accepted legacy DOC coverage for CFB header version,
version-3 directory-sector count, directory timestamp/CLSID/state-bit
provenance, encrypted FIB rejection, fExtChar Unicode text ranges,
FibRgLw97 subdocument boundaries, or CLX PCD flag validation. Remaining
legacy DOC follow-ups include DIFAT overflow chains, FAT/miniFAT stream-sector
loop hardening, SummaryInformation codepage expansion, and richer Word table
or paragraph property extraction.
