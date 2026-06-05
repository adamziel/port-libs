# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T024931Z`
- Accepted base: `1eee6af798a6b3fb39aedd5a1a8249d05194afe5`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for linked and
protected sections:

- Preserves `text:section-source` href, section name, filter name, and xlink
  type/show/actuate metadata on `odf-linked-section` div nodes.
- Preserves `text:section` style name and protected state on review divs.
- Reports protection-key presence without exposing the key value, plus the
  digest algorithm when present.
- Adds ODT import-report content counters for all sections, linked sections,
  and protected sections.
- Updates the WordPress ODF handoff smoke so external appendix provenance and
  protected-section state survive as data attributes without dereferencing the
  linked section.

This is bounded to OpenDocument package/content XML mapping. It does not invoke
Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external template
engines, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 263 assertions, 0 failures`
- Red-first after adding the linked/protected section expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 267 assertions, 1 failures`
  - Expected failure: only the base `odf-section` class was present.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 301 assertions, 0 failures`
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`:
    no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `551 -> 552`.
- `benchmarkDenominator.mapped`: `1030 -> 1031`.
- Focused `OdfReaderTest.php`: `11 -> 12` cases, `263 -> 301`
  assertions.
- `odfOpenDocumentCoreCases`: `10 -> 11`.
- `mappedOdfOpenDocumentCoreCases`: `10 -> 11`.
- `odfOpenDocumentCoreAssertions`: `217 -> 255` in the current manifest
  counter after applying this slice's `+38` focused assertion delta.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local linked/protected section parsing is not blocked by that
runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/styles/meta/media/table/
list/annotation/text-box/image, footnote/endnote, bookmark-reference,
reference-mark/reference-ref, tracked-change, encrypted-manifest, MathML
object, and `text:sequence` clusters. It adds only bounded OpenDocument
linked/protected section metadata and related import-report counters.

Remaining ODT follow-up stays separate: forms, charts, page styles/master
pages, richer style cascades, embedded-object preview policy beyond MathML,
table continuation semantics, export-side ODT writing, and full Pandoc ODT
reader parity.
