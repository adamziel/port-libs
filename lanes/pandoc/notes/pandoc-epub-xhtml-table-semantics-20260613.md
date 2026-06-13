# EPUB XHTML Table Semantics Slice (2026-06-13)

Slice: bounded EPUB3 spine XHTML table structure review metadata on accepted base `437a269855`.

## Summary

`EpubReader` now reports table semantics discovered in XHTML content documents without converting the whole XHTML tree to Pandoc AST nodes. The bounded scanner records:

- table caption text, ids, attributes, and summary attributes;
- direct `caption`/`colgroup`/`thead`/`tbody`/`tfoot`/implicit-body section order;
- row counts by table section;
- header/data cell counts;
- `th` `scope`, `td`/`th` `headers`, `abbr`, row spans, and column spans;
- nested-table counts and diagnostics for ambiguous captions or repeated head/foot sections.

The table packet is propagated through each content resource report, aggregate `xhtmlResourceReport`, import reports, and spine `raw_html` document node attributes.

## Denominator And Numerator

The accepted EPUB upstream denominator remains 9 static upstream inventory rows. Local EPUB mapped evidence moves from 61 to 62 cases, or 688.9% of the coarse upstream denominator. `phpPass` moves from 3330 to 3331 while `phpFail` remains 0.

New counters:

- `mappedEpubXhtmlTableSemanticsCases`: 1
- `epubXhtmlTableSemanticsAssertions`: 52

## Non-Overlap

This does not repeat prior EPUB package work for OCF/container parsing, OPF metadata/manifest/spine parsing, nav XHTML parsing, NCX metadata, NCX label audio provenance, guide/collection/rendition/binding/media-overlay handling, remote-resource reconciliation, encryption review, CFI fragment propagation, or XHTML definition-list handoff. The new surface is only bounded XHTML table-section, caption, and header-cell semantics in spine content review metadata.

## Remaining Gaps

EPUB remains partial. Direct EPUB package reader parity still needs broader structural/content coverage beyond table semantics, including full XHTML-to-AST conversion policy, section/header mapping, nav/NCX edge provenance, OPF metadata propagation, package structural diagnostics, and media/resource handling. No upstream Pandoc runner, EPUBCheck, browser renderer, or external validator was executed.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> 1 file, 4328 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 45 files, 74818 assertions, 0 failures

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.
