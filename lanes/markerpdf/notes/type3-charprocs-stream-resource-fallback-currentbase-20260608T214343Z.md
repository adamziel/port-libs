# Type3 CharProcs Stream Resource Fallback Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T214343Z`
Base accepted HEAD: `a0d85bbfea71fbea16acdfcda87bce21bb3681b0`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/text layers before OCR/model fallback. In the native no-GPU PHP lane, Type3 `/CharProcs` are font glyph programs, not standalone document paragraphs. A malformed PDF can point `/CharProcs` at a stream object instead of the required glyph dictionary; that stream and anything reachable only through its private resources must remain excluded from stream-only fallback text.

## Change

- `PdfTextExtractor::type3CharProcResourceObjectGenerationSet()` now also walks Type3 `/CharProcs` dictionary references themselves when collecting Type3-private metadata/resource streams.
- This preserves existing width behavior: malformed `/CharProcs` stream objects are still rejected as glyph maps.
- The new focused fixture covers a malformed indirect `/CharProcs` stream object whose stream dictionary has private `/Resources /XObject` Form streams with nested resources. Visible fallback content stays visible while CharProcs payload, glyph payload, direct resource payload, nested resource payload, resource names, and font program names stay out of WordPress paragraphs.

## Verification

Red-first after adding the focused test and before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsStreamResourceFallbackBoundaryCurrentBaseTest.php
FAIL excludes malformed Type3 CharProcs stream resources from fallback WordPress text on current base
1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsStreamResourceFallbackBoundaryCurrentBaseTest.php
PASS excludes malformed Type3 CharProcs stream resources from fallback WordPress text on current base
1 test files, 11 assertions, 0 failures
```

Adjacent focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsStreamResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateResourceFallbackBoundaryCurrentBaseTest.php
5 test files, 50 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-stream-resource-fallback-currentbase.php
```

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, exact object lookup, Type3 CharProcs fallback-exclusion sets, resource dictionary walker, stream decoder, focused PHP runner, and WordPress smoke path. Python, OCR, Surya/Texify/Torch, PDFium/PIL raster execution, model workers, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-overlap

This does not repeat accepted direct Type3 CharProc payload exclusion, exact-generation CharProcs, direct or indirect CharProcs dictionary tail rejection, invalid CharProcs stream payload exclusion, glyph references in invalid CharProcs stream dictionaries, top-level font/CharProc resource fallback, XObject/Form private-resource exclusion, Pattern/ColorSpace/Shading/Properties/Font/ExtGState fallback, Type3 image review, FontMatrix/width metrics, CMaps, xref repair, metadata, annotations, forms, image filters, security preflight, OCR/model work, or supplied-boundary table/equation handoffs. The bounded behavior is only resource streams reachable from a malformed indirect `/CharProcs` stream object before stream-only fallback extraction.
