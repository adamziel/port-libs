# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T001902Z`
- Accepted base: `4387eff3c6950226648700389da4d046c02a09df`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text mapping for ODT tracked changes:

- Inventories `text:tracked-changes/text:changed-region` entries for `text:insertion`, `text:deletion`, and `text:format-change`.
- Preserves `office:change-info` creator, date, and reviewer comment paragraphs in the import report.
- Maps `text:change-start` / `text:change-end` ranges into AST `span` nodes with `odf-change` plus type-specific classes and `data-odf-change-*` review metadata.
- Maps standalone `text:change` deletion marks into reviewable deletion spans using the deleted text stored in the matching changed region.
- Reports tracked-change counts in the ODT package import report and exercises Markdown plus WordPress block output.

This is bounded to OpenDocument Text XML mapping. It does not invoke Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external template engines, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: 1 test file, 165 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`: `odf open document handoff self-test ok`.
- `git diff --check -- lanes/pandoc`: no whitespace errors.

Root harness was not run; this is an isolated Pandoc micro-slice.

## Status Delta

- `phpPass`: 460 -> 461.
- `benchmarkDenominator.mapped`: 931 -> 932.
- Focused `OdfReaderTest.php`: 6 -> 7 cases, 139 -> 165 assertions.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- `odfOpenDocumentCoreAssertions`: 217 -> 243.

## Dependency Closure

No new support component is needed. This slice reuses the existing native `ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` components. Full upstream Pandoc runner parity remains blocked on hydrating/building the Haskell Pandoc checkout at the manifest commit, but ODT-local tracked-change parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/manifest/content/styles/meta/media/table/list/annotation/text-box/image, footnote/endnote, and bookmark-reference clusters. It adds only bounded OpenDocument tracked-change inventory and review-span mapping.

Remaining ODT follow-up stays separate: formulas, charts, linked sections, encrypted package preflight, forms, richer style cascades, embedded-object/page-style policy, table continuation semantics, export-side ODT writing, and full Pandoc ODT reader parity.
