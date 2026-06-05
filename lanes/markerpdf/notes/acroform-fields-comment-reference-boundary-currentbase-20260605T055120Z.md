# AcroForm Fields Comment Reference Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T055120Z`
Base accepted HEAD: `9b68199f32b5197072808d2a0777836f3b906513`

## Source Truth

- Upstream markerPDF consumes searchable PDF structure and form review metadata through parser-backed PDF extraction before OCR/model stages.
- PDF comments are lexical whitespace. Indirect references written as `N % comment EOL G % comment EOL R` must resolve like `N G R`.
- Current no-GPU markerPDF scope applies: no OCR, Surya, Texify, Torch, PDFium/pypdfium2 rendering, browser service, Streamlit/FastAPI model worker, or external PDF tool was run.

## Behavior

`PdfAcroFormExtractor` now reads indirect references through a token helper that treats PDF comments as whitespace while preserving generation checks.

The focused fixture proves comment-split references resolve across:

- catalog `/AcroForm`;
- indirect `/Fields` arrays and field `/Kids` arrays;
- page `/Annots` Widget references;
- Widget `/Parent`;
- indirect scalar field operands `/FT`, `/T`, `/TU`, `/TM`, `/V`, and `/MaxLen`;
- Widget `/Rect` numeric operands and `/F` flags.

Literal text such as `(99 0 R stays literal)` remains a decoy and is not promoted into AcroForm field review. Field values remain review metadata and do not become visible WordPress paragraph text.

## Evidence

Red-first current-base command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsCommentReferenceBoundaryCurrentBaseTest.php
```

Failed before the implementation with no fields discovered:

```text
Expected: ['article.commentref', 'settings', 'inline.commentref']
Actual: []
```

Verification after the fix:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsCommentReferenceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-comment-reference-currentbase.php
```

All reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsCommentReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats PDF comments as whitespace inside AcroForm indirect references

1 test files, 33 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php lanes/markerpdf/tests/PdfSecurityAcroForm*.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php
Focused test run: 30 selected test files (root lock skipped)
30 test files, 2998 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-comment-reference-currentbase.php
```

The smoke exits 0 and emits `comment_split_acroform_resolved=true`, `comment_split_fields_array_resolved=true`, `comment_split_page_widget_parent_promoted=true`, `comment_split_inline_widget_promoted=true`, `comment_split_scalar_values_resolved=true`, `comment_split_widget_geometry_resolved=true`, `comment_split_widget_flags_resolved=true`, `literal_reference_decoy_excluded=true`, `form_values_visible_in_text=false`, and all Python/model/external-tool/action execution flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP pass count: `1490 -> 1491`.
- Added one WordPress AcroForm field smoke scenario.
- No dashboard/root coordination files were edited.

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, child root normalization, direct Widget `/Fields` normalization, token-aware array decoy exclusion, generation-exact references, trailer-root ownership, scalar/numeric/type operand generation matching, comment-only Widget subtype exclusion, widget appearance/action/XFA/signature review, Type3 CharProc comment-reference handling, xref repair, stream filters, image handling, CMaps, outlines, attachments, runtime planners, or OCR/model behavior. The bounded behavior is only PDF comment-as-whitespace parsing inside AcroForm indirect references and operands.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, dictionary/array tokenizer, PDF comment skipping, name/string decoders, generation-valid reference checks, field hierarchy builder, page annotation widget map, text extractor boundary, and WordPress smoke path. Full upstream live OCR/model/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
