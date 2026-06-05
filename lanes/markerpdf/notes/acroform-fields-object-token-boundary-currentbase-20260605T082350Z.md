# AcroForm Fields Object Token Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T082350Z`
Base accepted HEAD: `d36e1e98e24a92bc490dde83eb92cd3f4021c21c`

## Source Truth

- Upstream markerPDF consumes searchable PDF parser output and form review metadata before OCR/layout/model stages.
- PDF object terminators are lexical tokens. Literal strings, hex strings, arrays, dictionaries, comments, names, and stream payloads may contain the bytes `endobj` without ending the enclosing direct object.
- Current no-GPU markerPDF scope applies: no OCR, Surya, Texify, Torch, PDFium/pypdfium2 rendering, browser service, Streamlit/FastAPI model worker, or external PDF tool was run.

## Behavior

`PdfAcroFormExtractor` now collects direct objects with a token-aware object-body scanner instead of the previous non-greedy `obj ... endobj` regular expression. The scanner skips PDF literal strings, hex strings, arrays, dictionaries, comments, top-level names, and stream payloads before accepting `endobj` as an object terminator.

The focused fixture proves AcroForm fields continue to preserve review metadata when literal `endobj` appears inside:

- field `/T` names;
- `/TU` editor labels;
- `/TM` export mapping names;
- current `/V` values;
- default `/DV` values;
- choice `/Opt` labels;
- widget `/MK /CA` captions.

The same fixture proves the unlisted decoy field after the real object terminator is not promoted, and that AcroForm values/captions remain review metadata rather than visible WordPress paragraph text.

## Evidence

Red-first current-base probe before the implementation:

```text
PdfAcroFormExtractor returned field name "article" with no /V, /DV, /MaxLen, or full widget review after object 6 was truncated at literal "(article.endobj.title)".
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectTokenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps literal endobj tokens inside AcroForm field objects before WordPress review

1 test files, 41 assertions, 0 failures
```

Adjacent AcroForm/security-form family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm|PdfSecurityAcroForm|PdfSecurityPermissionByteRangeFieldMdp|PdfPageWidgetFieldActionLink' | sort)
Focused test run: 32 selected test files (root lock skipped)
32 test files, 3128 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-object-token-currentbase.php
```

The smoke exits 0 and emits `literal_endobj_field_name_preserved=true`, `literal_endobj_current_value_preserved=true`, `literal_endobj_default_value_preserved=true`, `literal_endobj_widget_caption_reviewed=true`, `decoy_after_object_token_excluded=true`, `form_values_visible_in_text=false`, and all Python/model/external-tool/action execution flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP pass count: `1615 -> 1616`.
- Added one mapped manifest behavior: `pdfAcroFormFieldsObjectTokenBoundaryCurrentBase`.
- Added one WordPress AcroForm field smoke scenario.
- No dashboard/root coordination files were edited.

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, direct Widget `/Fields` normalization, child branch/root normalization, token-aware array decoy exclusion, comment-split indirect references, generation-exact references, trailer-root ownership, scalar/numeric/type operand generation matching, comment-only Widget subtype exclusion, unowned widget-parent rejection, widget appearance/action/XFA/signature review, Type3 CharProc comment-reference handling, xref repair, stream-filter decoding, image handling, CMaps, outlines, attachments, runtime planners, or OCR/model behavior. The bounded behavior is only direct AcroForm object-body termination when `endobj` appears inside PDF token payloads.

## Dependency Closure

No new support component is needed. This reuses native PHP dictionary/array/literal/hex/name/comment token readers, generation-valid object selection, AcroForm field hierarchy review, page annotation widget mapping, text extraction visibility checks, and the WordPress smoke path. Full upstream live OCR/model/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
