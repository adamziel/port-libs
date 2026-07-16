# AcroForm Signature Widget Lock Resource Current Base

Slice: `form-signature-widget-lock-resource-currentbase`

Source-truth evidence:

- Upstream Marker README, https://github.com/datalab-to/marker/blob/master/README.md, documents Marker as a PDF/document-to-Markdown/JSON pipeline that formats forms and supports extracting `BlockTypes.Form` blocks. It also notes form-value extraction can require LLM assistance, so the native PHP lane keeps PDF form execution and signature validation out of import-time behavior.
- pdftext dependency README, https://github.com/datalab-to/pdftext, documents structured PDF text extraction on pypdfium2 and a `--flatten_pdf` option that merges form fields into the PDF. This slice maps the equivalent safe native boundary by exposing signature widget appearance resources as review metadata while leaving flattening/rendering/execution disabled.
- PDF AcroForm source truth for this fixture is the catalog `/AcroForm` field tree with a signature `/FT /Sig` field, signed `/V` signature dictionary, `/Lock` SigFieldLock dictionary, and widget `/AP` normal/rollover/down appearance dictionaries with nested resource XObject `/A` and `/AA` actions.

Implementation:

- `PdfAcroFormExtractor` now attaches `signature_widget_lock_resource_review` to signature fields.
- The review correlates signed signature state, `/Lock` action/scope/permission metadata, page-referenced widget identity, selected normal/rollover/down appearance objects, appearance resource font names, resource XObject names, nested resource action counts/types/objects, and explicit non-execution flags.
- Existing `signature_widget_review` keeps the same action bundle and now nests the lock/resource review as `lock_resource_review`.

Focused evidence:

- Red before implementation:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureWidgetLockResourceCurrentBaseTest.php`
  - Failed on missing `signature_widget_lock_resource_review`; 1 file / 7 assertions / 1 failure.
- Passing after implementation:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureWidgetLockResourceCurrentBaseTest.php`
  - Passed 1 file / 64 assertions / 0 failures / 2 PASS cases.
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-acroform-signature-widget-lock-resource-currentbase.php`
  - Emitted `approval.locked_resource`, lock fields `article.title` and `article.section`, selected appearance objects `[50,52,54]`, resource fonts `Fsig`, `Froll`, `Fdown`, resource XObjects `Seal`, `Audit`, `PressedSeal`, resource action count `2`, and all action/render/signature execution flags false.

Dependency closure:

- No new support component is needed. The slice reuses existing native object parsing, AcroForm field traversal, widget appearance stream review, stream decoding, action-chain review, and signature lock metadata helpers.
- Full upstream runner parity remains blocked by the existing heavy Python/model/runtime dependencies; this slice does not require live pypdfium2, Python, OCR, LLMs, signing, or appearance rendering.

Non-overlap:

- Avoids the accepted seed-value `/SV` lock action review, submit/reset appearance lock review, generic widget appearance resource XObject action review, XFA signature widget bundle review, and security ByteRange/DSS signature slices.
