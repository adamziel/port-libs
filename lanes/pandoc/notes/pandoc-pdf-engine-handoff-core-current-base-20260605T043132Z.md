# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T043132Z`

Base accepted HEAD: `b36dbe88ba80463d50bb6c0be8e8621b7076aace`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded detection of fake-produced PDF `/Encrypt` dictionaries.
- Fake-runner results now expose `pdfEncrypted`, `pdfEncryptionFilter`, `pdfEncryptionVersion`, `pdfEncryptionRevision`, `pdfEncryptionLength`, `pdfPermissionInteger`, `pdfPermissionFlags`, and `pdfEncryptMetadata`.
- Standard security-handler permission integers are decoded into reviewer-facing booleans for low-quality print, modification, copy/extract, annotation, form fill, accessibility extraction, assembly, and high-quality print permissions.
- Encrypted PDF outputs are marked failed with `pdf-output-encrypted` so WordPress review queues do not treat protected renderer output as import-ready.
- `PdfEngineHandoff::fakeRunSequence()` carries the final encrypted-output fields through multipass fake-runner summaries.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress smoke exposes protected-PDF triage metadata alongside existing resource, sidecar, log, bibliography, SyncTeX, output, annotation, and attachment diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: keep encrypted/protected output visible to WordPress review queues without executing Pandoc, TeX, Typst, browser, roff, or PDF engines.
- It does not implement password/key handling, full PDF parsing, object-stream or xref recovery, stream decryption, annotation appearance parsing, embedded-file byte extraction, remote link fetching, or renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 261 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 262 assertions, 1 failure. The new encrypted-output test failed because `fakeRun()` still returned `ok=true` for a PDF trailer containing `/Encrypt`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 278 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`, `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/resource source handoff, source-artifact validation, resource-file validation, expected TeX sidecar inventory, engine warning/error log extraction, missing renderer executable triage, bibliography sidecar classification, generated PDF output byte/path/page metrics, produced PDF page-tree/outline inspection, produced PDF annotation/link/embedded-file inspection, SyncTeX/source-map extraction, TeX recorder `.fls` dependency parsing, TeX transcript include-graph parsing, or multipass rerun-state aggregation.

The new surface is produced-PDF encrypted-output and permission-dictionary preflight from fake-produced bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT, EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion, charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner file-map/result contract. Real PDF rendering, full cross-reference/object-stream parsing, stream decryption, password/key handling, annotation appearance parsing, embedded-file byte extraction, real executable discovery, real `.fls` generation, real SyncTeX generation, real bibliography execution, and remote resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated Pandoc checkout and Haskell Cabal dependency closure already recorded in lane status.
