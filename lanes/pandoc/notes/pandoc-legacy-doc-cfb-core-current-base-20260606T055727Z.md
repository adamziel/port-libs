# Legacy DOC/CFB Orphaned Directory Entry Slice

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T055727Z`
Base: `cf7ad8dedfdead64d21e5ec92010b21088cacf79`

## Source Truth

- MS-CFB directory entries are exposed through the Root Entry and storage child/left/right sibling trees.
- A non-empty directory entry that is not reachable from those trees is not a safe stream source for a legacy Word import handoff.
- For the WordPress/Pandoc-like conversion contract, CFB preflight should fail before `WordDocument`, OLE metadata, embedded-object, or macro streams are exposed from a structurally inconsistent directory.

## Patch

- `CompoundFileBinary` now rejects any active raw directory entry that remains unvisited after traversing the Root Entry/storage directory trees.
- `LegacyDocReaderTest` mutates a valid CFB fixture so `Review/Notes` remains active in the directory sector but becomes unreachable from the Root Entry tree, then asserts the package is rejected before stream lookup.
- The WordPress legacy DOC handoff example self-test now includes a corrupt orphaned active directory-entry fixture in the existing CFB rejection loop.

## Verification

- Red-first focused check: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed with `1 test files, 747 assertions, 1 failures` because the orphaned active entry was ignored.
- Focused tests after patch: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 747 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed with `legacy doc handoff self-test ok`.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is required. This reused native PHP `CompoundFileBinary`, `LegacyDocReader`, the in-process CFB fixture builder, and the existing WordPress legacy DOC handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, or live provider test was executed.

## Non-Overlap

This slice owns only CFB orphaned active directory-entry rejection. It avoids accepted legacy DOC/CFB work for CFB header version and directory-count preflight, MiniFAT/FAT/DIFAT sector identity checks, sector overlap checks, directory sibling color/order/black-height validation, directory timestamps/CLSID/state-bit provenance, OLE property metadata, FIB encryption and `fExtChar` preflight, FibRgLw97 subdocument trimming, CLX PCD flag validation, Symbol field decoding, ObjectPool metadata/reference reporting, SttbfAssoc, Plcfld field ranges, styles, sections, notes, bookmarks, lists, and formatting runs.

Follow-up should keep directory-tree salvage/repair policy, encrypted/decryption support, FFData form option decoding, header/footer/textbox field tables, inline picture extraction, and any full ObjectPool byte export policy as separate bounded slices.
