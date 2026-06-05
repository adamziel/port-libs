# markerPDF malformed named UseCMap CMap filter boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T204159Z`

Base: `45d7444636fadb02e0af6bd44f022ffb9b6ea6e5`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through
native PDF parsing before OCR/model stages. The no-GPU PHP lane owns font,
CMap, stream-filter, and fail-closed text extraction/review behavior.

The PDF CMap grammar allows a decoded CMap program to inherit from a named base
with `/<CMapName> usecmap`. This slice keeps that inheritance bounded to the
first decoded CMap program so literal-string decoys and post-`endcmap` names do
not become trusted base references.

## Behavior

`PdfTextExtractor` now records named `usecmap` program references in the CMap
stream filter/length/owner review:

- decoded CMap programs are scanned with the same bounded single-program parser
  used for text extraction;
- local CMap streams are indexed by decoded `/CMapName`, falling back to a
  dictionary `/CMapName` only when decoding fails;
- malformed filtered named base CMap streams remain undecoded and do not leak
  base text into WordPress paragraphs;
- review metadata counts the malformed base as a `use_cmap` stream with
  `reference_kind=named_usecmap`;
- existing literal-string `usecmap` decoys and post-`endcmap` CMapName decoys
  remain excluded.

## Red-First Evidence

After adding
`PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php` and before
the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php`

Result: `1 test files / 18 assertions / 1 failures`

Failure:

- `use_cmap_stream_count` stayed `0` for the malformed filtered base stream
  referenced by decoded `/<Name> usecmap`.

## Verification

Focused named UseCMap boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php`

Result: `1 test files / 64 assertions / 0 failures`

Adjacent CMap/filter/UseCMap family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php`

Result: `8 test files / 1642 assertions / 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-named-usecmap-filter-boundary-currentbase.php`

Result: passed. The smoke emits `use_cmap_stream_count=1`,
`decoded_cmap_count=1`, `dictionary_filter_operand_count=1`,
`base_reference_kind=named_usecmap`, `malformed_base_text_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-named-usecmap-filter-boundary-currentbase.php` passed.

Diff check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2203 -> 2204` from one new focused TestRunner PASS case.
- `wordpressScenarios`: `1898 -> 1899` from the new WordPress named UseCMap
  malformed-base smoke.

## Non-Overlap

This does not repeat direct object-reference `/UseCMap` handling, malformed
DecodeParms inheritance, post-`endcmap` CMapName exclusion, literal-string
`usecmap` decoys, object-valued UseCMap stale-name rejection, missing ASCIIHex
or ASCII85 EOD handling, all-null filter fallback exclusion, Crypt filter
identity/private behavior, CMap filter owner xref repair, xref repair, XMP
metadata, annotations, forms, image filters, OCR, or model execution.

The bounded behavior is only named CMap program `usecmap` review ownership for
local CMap stream objects, including malformed filtered named bases that must
stay fail-closed for text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
parser, stream filter decoder, bounded CMap program parser, CMap name/usecmap
scanner, text extractor, CMap review summary, and WordPress smoke pattern. Full
upstream OCR/model, PDFium, Surya, Texify, Torch, and external renderer parity
remains intentionally out of scope under the current no-GPU markerPDF
directive.
