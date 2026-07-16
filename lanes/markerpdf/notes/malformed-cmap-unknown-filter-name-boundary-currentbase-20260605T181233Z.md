# markerPDF malformed CMap unknown filter-name boundary current-base

Session: `port-dev-markerpdf-malformed-cmap-20260605T181234Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T181233Z`

Base accepted HEAD: `7d9c0d95369452edfa4d4e82fdeb155c66c43b63`

## Scope

This patch stays inside native no-GPU markerPDF searchable-PDF parsing. It does
not run OCR, Surya, Texify, Torch, PDFium, pypdfium, Python helpers, model
workers, external PDF tools, or live services.

The bounded behavior is a malformed ToUnicode CMap stream dictionary whose
scalar `/Filter /FlateDecode` operand is followed by an unknown unkeyed name
immediately before `/Length`:

```text
<< /Type /CMap /Filter /FlateDecode /UnknownFilterName /Length ... >>
```

Before this slice, the native parser treated `/Length` as the stream length and
decoded the leaking CMap. The fix fails closed before ToUnicode replacement, so
WordPress-visible text remains the safe Identity-H fallback text.

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through pdftext/PDFium font
and CMap extraction before Markdown generation. Under the current no-GPU lane
scope, the PHP parser owns the equivalent stream-filter safety boundary:
malformed CMap filter declarations must not decode replacement maps or expose
CMap program bytes as WordPress paragraphs.

## Behavior

`PdfTextExtractor::directScalarFilterExtraOperand()` now treats an unknown name
after a scalar filter as an extra malformed filter operand when that name is
immediately followed by `/Length`. Ordinary unknown keyed stream-dictionary
entries remain untouched.

The new review metadata reports:

- `decoded_cmap_count=0`
- `filter_operand_policy=reject_malformed_filter_operands`
- `filter_resolution_failed=true`
- `malformed_filter_operand_count=1`
- `extra_filter_operand_type=name`
- `extra_filter_name=UnknownFilterName`

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when a scalar CMap Filter is followed by an unknown unkeyed filter-name before Length (lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Unknown Filter Name Safe Import',
)
Actual: array (
  0 => 'Unknown Filter Name CMap Leaknknown Filter Name Safe Import',
)

1 test files, 1 assertions, 1 failures
```

## Verification

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when a scalar CMap Filter is followed by an unknown unkeyed filter-name before Length

1 test files, 52 assertions, 0 failures
```

Adjacent CMap/filter subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1701 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-unknown-filter-name-boundary-currentbase.php
```

The smoke emits `safe_text_imported=true`,
`cmap_payload_excluded=true`, `decoded_cmap_count=0`,
`filter_operand_policy=reject_malformed_filter_operands`,
`extra_filter_name=WPUnknownFilterName`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`, and renders only
`WP Unknown Filter Name Safe Import`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-unknown-filter-name-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All completed with exit code `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2134 -> 2135`
- `wordpressScenarios`: `1839 -> 1840`
- Focused PASS case delta: `+1`
- Focused assertion delta for the new test: red-first `1 assertion / 1 failure`
  to `52 assertions / 0 failures`

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary
scanner, stream-filter resolver, current CMap stream decoder boundary,
ToUnicode fallback path, CMap owner review metadata, and WordPress smoke
renderer.

Full upstream model parity remains intentionally out of scope under the
current no-GPU markerPDF directive and remains gated by pdftext,
pypdfium2/PDFium, Surya/Torch, Texify, Streamlit/FastAPI runtime paths,
benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted malformed CMap dictionary/literal/indirect filter
operands, direct scalar filter operands followed by known decoder names or
non-name operands, stale-generation filter owner selection, invalid/trailing/
null DecodeParms alignment, all-null filter stacks, escaped or unsupported
filter names, identity/private Crypt filter policy, explicit filter EOD
enforcement, unbounded explicit filter markers, predictor decode-error
metadata, post-`endcmap` cleanup, complete second-program exclusion,
literal-string CMapName/usecmap decoys, nested CMap target arrays, or Type3
CharProc graphics-state boundary work.

The bounded behavior is specifically an unknown unkeyed name after a scalar
CMap `/Filter` and before `/Length`.
