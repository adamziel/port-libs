## Malformed CMap Length Generation Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T174025Z`

Base accepted HEAD: `a6a647ebd353274fa49c3f82015976e88c6af903`

## Source Truth

Pinned upstream markerPDF (`sddai/markerPDF` at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) routes searchable-PDF text
through parser-backed PDF object and stream decoding before any OCR/model
fallback. Under the current no-GPU markerPDF scope, the PHP port owns native
PDF xref/object selection, stream filters, stream length boundaries, and
ToUnicode CMap parsing without running PDFium, OCR, Surya/Torch, Texify, or
external PDF tools.

PDF indirect references include an object generation. When the current xref
row selects generation `8 1`, a CMap stream dictionary operand `/Length 8 0 R`
is a stale helper and must not bound or enable decoding of the current
filtered CMap stream.

## Behavior

`PdfTextExtractor` now rejects filtered CMap stream decoding when `/Length`
contains an indirect reference that cannot resolve through the current xref
owner. The review path also reports the declared length as unavailable for
that CMap instead of surfacing the stale generation-zero helper value as
usable.

The focused fixture uses:

```text
6 0 obj
<< /Type /CMap /Filter /FlateDecode /Length 8 0 R >>
stream
...
endstream
endobj
8 0 obj
<correct compressed CMap byte length>
endobj
8 1 obj
/UnselectedLengthHelper
endobj
xref row for object 8 -> generation 1
```

Before the fix, a red-first run decoded the stale generation-zero length
helper, applied the CMap, and leaked `Length Gen CMap Leak` into extracted
text.

After the fix, extraction preserves the fallback searchable text
`Length Gen Safe Import`, reports `decoded_cmap_count=0`,
`unresolved_operand_count=1`, `declared_length=null`, and keeps the length
operand review-visible with `owner_policy=xref_entry_points_elsewhere` and
`selected_generation=1`.

## Files

- `lanes/markerpdf/src/PdfTextExtractor.php`
- `lanes/markerpdf/tests/PdfParserMalformedCMapLengthGenerationBoundaryCurrentBaseTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-length-generation-boundary-currentbase.php`
- `lanes/markerpdf/notes/markerpdf-malformed-cmap-filter-boundary-current-base-20260608T174025Z.md`
- `lanes/markerpdf/lane-status.json`

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects stale-generation CMap Length helpers before ToUnicode decoding
Values are not identical
Expected: array (
  0 => 'Length Gen Safe Import',
)
Actual: array (
  0 => 'Length Gen CMap Leakength Gen Safe Import',
)

1 test files, 1 assertions, 1 failures
```

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects stale-generation CMap Length helpers before ToUnicode decoding

1 test files, 60 assertions, 0 failures
```

Adjacent CMap stream length/filter owner regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDecodeParmsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
...
6 test files, 2210 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-length-generation-boundary-currentbase.php
```

The smoke exits `0`, emits one paragraph with
`WP Length Gen Safe Import`, and reports `safe_text_preserved=true`,
`payload_excluded=true`, `decoded_cmap_count=0`, `unresolved_operand_count=1`,
`declared_length=null`,
`length_owner_policy=xref_entry_points_elsewhere`,
`length_selected_generation=1`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapLengthGenerationBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-length-generation-boundary-currentbase.php
```

All three report no syntax errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap work for direct `/Length` tails,
positive current-generation `/Length` and `/Filter` owners, stale or free
`/Filter` owners, stale `/DecodeParms` owners, scalar/array/literal/dictionary
`/Filter` operands, duplicate `/Filter` or `/DecodeParms`, escaped or
unsupported filter names, Crypt filters, null-filter DecodeParms alignment,
explicit filter EOD enforcement, post-`endcmap` payload exclusion,
literal/array/procedure operator decoys, bfchar/bfrange row-shape boundaries,
malformed scalar or array CMap Unicode targets, UseCMap inheritance, Type0
Encoding CMaps, CIDFont widths, xref repair, image/filter metadata,
annotations/forms/security, OCR/model handoffs, or supplied-boundary table/
equation work.

The bounded behavior is specifically stale-generation `/Length` helper
rejection before filtered ToUnicode CMap stream decoding.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, xref owner selection, stream length parser, stream filter resolver,
Flate decoder, ToUnicode CMap parser, focused test harness, and WordPress
smoke renderer. Full upstream model parity remains dependency-gated by
`pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/
FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering
helpers; none were executed.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines,
annotations, forms, security preflight, page geometry, image/filter metadata,
and supplied-boundary table/equation handoffs.
