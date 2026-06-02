## AcroForm Resource Action FileSpec Current Base

Micro-slice: `acroform-resource-action-filespec-currentbase`
Session: `port-dev-markerpdf-form43-20260602T2008Z`
Base accepted HEAD: `9efdfcaaff05b4be1ca34b399840525efdf84f93`

### Source Truth

- Upstream marker converts PDFs into structured Markdown/JSON blocks, documents a form-extraction path, and keeps the expensive Python/model runtime separate from static document conversion setup: https://github.com/datalab-to/marker
- PDF 32000-1 source truth for this slice is the interactive-action/FileSpec boundary: file specification dictionaries carry `/Type /Filespec`, `/FS`, `/UF`, `/F`, `/EF`, and `/RF` metadata, while Launch, SubmitForm, ImportData, GoToR, and GoToE actions use FileSpec operands for targets. Source: https://opensource.adobe.com/dc-acrobat-sdk-docs/standards/pdfstandards/pdf/PDF32000_2008.pdf

### Behavior

- `PdfAcroFormExtractor` keeps existing action `target` fields stable, then attaches `file_spec` review metadata to AcroForm SubmitForm, ImportData, Launch, GoToR, and GoToE rows.
- FileSpec review now records `/UF` over `/F` filename precedence, `/FS`, platform filenames, descriptions, AFRelationship, `/EF` embedded stream object ids, filters, decoded lengths, SHA-256 hashes, Params `/Size` `/CheckSum` `/CreationDate` `/ModDate`, and `/RF` related file pairs.
- Launch platform dictionaries also expose `platform_file_spec` so `/Win << /F ... >>` targets remain reviewable even when root `/F` is present.
- Embedded payload bytes stay hashed/review-only and are not returned as visible WordPress text, form submissions, imports, launches, or executed actions.

### Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormResourceActionFileSpecCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps AcroForm resource action FileSpec dictionaries review only at current base
Expected: 'acroform_action_filespec_review_boundary'
Actual: NULL
1 test files, 12 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormResourceActionFileSpecCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps AcroForm resource action FileSpec dictionaries review only at current base
1 test files, 63 assertions, 0 failures
```

Family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 1860 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-resource-action-filespec-currentbase.php
passed; emitted markerpdf:pdf-acroform-resource-action-filespec-currentbase with SubmitForm, ImportData, Launch, platform FileSpec, embedded-file hash, related-file count, and visible-text exclusion flags.
```

### Non-Overlap

This does not repeat accepted AcroForm calculation/signature/XFA value-state review, SubmitForm/ResetForm field-value selection, rich-text/default-resource review, widget appearance/action cycles, generic platform Launch/GoToE target rows, EmbeddedFiles catalog extraction, associated Filespec PieceInfo checksum metadata, or DSS/security action permission review. This slice is limited to FileSpec dictionary metadata attached to AcroForm action rows.

### Dependency Closure

No new support component is needed. The native PHP parser reuses existing bounded PDF dictionary, string, stream-filter, and default-resource helpers. It does not execute Python, marker models, pdftext, pypdfium, PIL, external PDF tools, PDF actions, Launch targets, form submission, ImportData, or embedded payload extraction.
