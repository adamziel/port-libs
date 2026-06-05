# Pandoc PDF Engine Handoff Digital Signatures 2026-06-05

## Scope

Micro-slice:
`pandoc-pdf-engine-handoff-core-current-base-20260605T151955Z`.

Accepted base:
`73c0bbac79227ee2db977ad15039a8acb1dad8b8`.

This slice stays inside the bounded native PDF-output handoff support library.
It does not implement or invoke Pandoc, TeX/PDF engines, Typst, browser
renderers, roff, JavaScript, external PDF validators, XMLDSig/CMS validators,
online services, or Haskell runners.

## Implemented Behavior

`PdfEngineHandoff` now inspects fake-runner produced PDF bytes for bounded
digital-signature metadata:

- AcroForm `/Fields` recursion for `/FT /Sig` widget fields.
- Field-bound and standalone `/Type /Sig` signature dictionaries.
- Signature field names, field object references, and signature object
  references.
- `/Filter`, `/SubFilter`, signer name, reason, location, contact info, and
  signing time values.
- `/ByteRange` segment counts plus covered-byte totals.
- Bounded `/Contents` byte counts and SHA-256 hashes without validating the
  CMS/PKCS#7 payload.
- `/Reference` transform summaries for DocMDP and FieldMDP transform params,
  including permissions, actions, and field lists.
- Fake-runner diagnostics for signature counts, byte ranges, contents,
  subfilters, and reference transforms.
- Multipass fake-runner sequence handoff through `finalPdfSignatures` and
  `finalPdfSignatureSubFilters`.

The WordPress PDF handoff example now includes a fake approval signature field
and self-test needles for first-run and final-run signature metadata.

## Source-Truth Boundary

The local upstream cache does not contain a hydrated Pandoc checkout for this
worktree, so no upstream Haskell runner or golden PDF fixture was executed.
This maps the produced-PDF signature metadata contract that Pandoc PDF
engines can hand back through fake-produced bytes, not engine-specific
signature generation or cryptographic validation parity.

## Non-Overlap

This patch does not repeat the existing PDF-engine support rows for sidecars,
logs, SyncTeX, FLS dependency graphs, TeX transcript include graphs, output
metrics, trailer/xref/object streams, page tree geometry, page labels/timings,
outlines, document info, language, XMP/PDF-A, output intents, catalog
presentation/viewer preferences, named destinations, tagging/structure trees,
annotations, links, embedded files, AcroForm field summaries, active
actions/JavaScript, encryption preflight, or optional content groups/config.

Follow-up PDF slices should keep CMS/PKCS#7 validation, byte-range digest
verification, DSS/LTV and DocTimeStamp dictionaries, field-lock propagation,
signature appearance streams, and engine-specific signature generation parity
separate.

## Dependency Closure

No new support component is needed. This reuses the existing native
`PdfEngineHandoff` fake-runner/PDF-byte inspection support component and
extends its bounded object, dictionary, array, string, and reference parsers.
Full upstream runner closure remains gated on hydrating Pandoc at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and recording a non-mutating Cabal
plan for the Haskell Tasty runners.

## Verification

- Rework note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - no matching rework note
- Baseline focused test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 446 assertions, 0 failures`
- Red-first focused test after adding the digital-signature case:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 448 assertions, 1 failures`
  - failure: missing `pdfSignatures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 456 assertions, 0 failures`
  - PASS cases: 42
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - `pdf engine handoff self-test ok`
- PHP lint:
  `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  - `No syntax errors detected in lanes/pandoc/src/PdfEngineHandoff.php`
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/PdfEngineHandoffTest.php`
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`
- Diff whitespace:
  `git diff --check -- lanes/pandoc`
  - no output
- Root harness: not run - isolated micro-slice.
