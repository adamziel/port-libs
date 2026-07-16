# markerpdf annotations links duplicate Annots key boundary current base

Slice: `markerpdf-annotations-links-boundary-current-base-20260605T232837Z`

Base accepted HEAD: `463e23b58e232021a39d809484cef165659f969d`

## Scope

This patch stays in the native no-GPU markerPDF scope. It covers searchable-PDF
page annotation dictionaries where a page object contains duplicate top-level
`/Annots` keys. The selected page annotation array is the last top-level
dictionary entry, while nested/private `/Annots` keys under other page
dictionary values remain ignored.

## Source truth

Upstream markerPDF imports searchable PDF page annotations through parser-level
page dictionaries and supplied text geometry before WordPress link/review
handoff. PDF dictionary parsing is key-based and object-map driven; this PHP
port should not treat nested/private annotation-like keys as page annotations,
and current top-level page dictionary entries should supersede stale earlier
entries for the same key.

## Implementation

- `PdfAnnotationExtractor` now reads the last top-level page dictionary value
  for `/Annots` instead of returning the first match.
- `PdfLinkAnnotationExtractor` now resolves duplicate keys inside parsed
  annotation dictionaries by keeping the last top-level value.
- `PdfMarkupAnnotationExtractor` now keeps the last top-level page dictionary
  value and skips PDF comments while walking page dictionary entries.
- A focused fixture demonstrates stale first-entry `/Annots`, a nested private
  decoy `/Annots`, and the current final top-level `/Annots` array with Link,
  Highlight, and Text annotations.

## Evidence

Red-first focused check before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsDuplicateKeyLinkBoundaryCurrentBaseTest.php`

Result: failed with stale object `8` selected instead of current objects `7`,
`9`, and `10`; `1 test files / 2 assertions / 1 failures`.

Focused check after the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsDuplicateKeyLinkBoundaryCurrentBaseTest.php`

Result: `1 test files / 28 assertions / 0 failures`.

Adjacent annotation/link family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnots*.php lanes/markerpdf/tests/PdfAnnotation*Test.php lanes/markerpdf/tests/PdfLinkAnnotation*Test.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php`

Result: `42 test files / 1702 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-page-annots-duplicate-key-link-boundary-currentbase.php`

Result: emitted `annotation_objects=[7,9,10]`,
`promoted_link_objects=[7]`, stale/private exclusion flags `true`, and all
Python/model/external-tool execution flags `false`.

Syntax checks:

- `php -l lanes/markerpdf/src/PdfAnnotationExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfPageAnnotsDuplicateKeyLinkBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-annots-duplicate-key-link-boundary-currentbase.php` => no syntax errors.

Whitespace check:

`git diff --check -- lanes/markerpdf`

Result: no output / passed.

## Dependency closure

No new support component is needed. This reuses the existing native PHP object,
dictionary, annotation, link, markup, text, and Markdown post-processing
components. No OCR, model execution, PDF action execution, pypdfium, external
PDF tools, or online services are required.

## Non-overlap

This does not repeat the accepted escaped `/Annots` key, page annotation token
array, rotated/UserUnit link rectangle, AcroForm, outline metadata, or xref
repair slices. It is specifically the duplicate top-level page `/Annots`
selection boundary before WordPress link and markup promotion.

## Next task

Continue native markerPDF no-GPU coverage around annotations, forms, page
geometry, stream filters, font encodings/CMaps, xref repair, metadata, image
filter metadata, and supplied-boundary table/equation handoffs.
