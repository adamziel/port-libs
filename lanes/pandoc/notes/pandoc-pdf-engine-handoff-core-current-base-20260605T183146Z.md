# Pandoc PDF Engine AcroForm Dictionary Handoff

Lane: `pandoc`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T183146Z`
Base: `581433eec2fd6efa9a749afbed5e3225ce12a185`

## Scope

This slice extends the native PHP fake-runner PDF-byte handoff only. It does
not execute Pandoc, TeX/PDF engines, Typst, browser renderers, roff, Haskell
runners, external PDF validators, online sanitizers, or online services.

## Implementation

`PdfEngineHandoff` now inspects the produced PDF catalog `/AcroForm`
dictionary and exposes bounded document-level form metadata:

- field object references and field count;
- `/NeedAppearances`;
- `/SigFlags` with bounded flag names;
- default resources and default appearance;
- quadding;
- calculation order references;
- XFA presence and packet names.

The metadata is returned from single fake runs as `pdfAcroFormMetadata`, from
fake-run sequences as `finalPdfAcroFormMetadata`, and is summarized through
`pdf-byte-acroform-*` diagnostics for importer triage.

## Evidence

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 485 assertions, 0 failures`.
- Focused run after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 500 assertions, 0 failures`.
- WordPress PDF engine example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.

## Non-Overlap

This does not repeat existing PDF sidecar/log, SyncTeX, recorder `.fls`,
transcript include-graph, page geometry, outline, annotation/link,
embedded-file, encryption, document-info, XMP/PDF-A, output-intent,
catalog-language, tagging, optional-content, collection, signature, active
action, or field-level widget handoff slices. The new behavior is scoped to
document-level `/AcroForm` dictionary metadata.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP `PdfEngineHandoff` PDF-byte parser and leaves full upstream Pandoc
runner parity gated on a hydrated local Pandoc checkout plus Haskell Tasty
runner builds for `test-pandoc` and `test-pandoc-lua-engine`.

## Next

Follow-up PDF slices should keep widget appearance streams, annotation/action
cross-links, XFA packet dereferencing/decompression, signature byte-range
validation, PDF/UA structure validation, real renderer execution, and external
PDF validator parity separate.
