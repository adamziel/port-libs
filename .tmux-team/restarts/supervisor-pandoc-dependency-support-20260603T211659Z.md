# Supervisor Goal: Pandoc Dependency Support Rotation

## Outcome
- Keep the durable Pandoc refill path focused on support libraries that unlock
  richer file formats instead of generic Markdown-only slices.
- Ensure every new Pandoc worker either produces a bounded PHP implementation
  patch under `lanes/pandoc/**` or, for upstream-runner dependency slices only,
  leaves a source-truth audit note.

## Intensity
- Level: high
- Starting workers: continuous pool raised to 6 Pandoc workers
- Scaling rule: keep the markerPDF/Pandoc watchdog at an 8 markerPDF / 6
  Pandoc split while disk, RAM, and load remain healthy.

## Non-Goals
- Do not port Word, LibreOffice, TeX, Typst, browser layout engines,
  bibliography-manager applications, or whole parser ecosystems.
- Do not shell out to Pandoc, Office suites, TeX/PDF engines, zip/unzip,
  Haskell test binaries, or online services as progress.
- Do not edit dashboard/progress files from isolated workers.

## Ground Truth
- `dependency-backlog.json`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- Accepted Pandoc notes under `lanes/pandoc/notes/`

## Worker Topology
- `pandoc-shared-zip-package-core-*`: ZIP/OPC package primitives.
- `pandoc-opc-xml-relationships-core-*`: content types and relationships.
- `pandoc-xml-html5-dom-core-*`: safe XML/HTML parsing and serialization.
- `pandoc-docx-openxml-core-*`: DOCX body, properties, styles, numbering, and media.
- `pandoc-epub3-package-core-*`: EPUB container, OPF, spine, nav/NCX, XHTML assets.
- `pandoc-odf-open-document-core-*`: ODT manifest, content, styles, and meta XML.
- `pandoc-legacy-doc-cfb-core-*`: legacy DOC/Compound File Binary extraction.
- `pandoc-citation-csl-core-*` and `pandoc-bibtex-csl-core-*`: citation,
  CSL, BibTeX/BibLaTeX, and bibliography handoff.
- `pandoc-math-tex-conversion-core-*`: inline/display math, bounded TeX, MathML.
- `pandoc-syntax-highlighting-core-*`: code language alias/style/token handoff.
- `pandoc-charset-unicode-width-core-*`: byte decoding, Unicode repair, width.
- `pandoc-table-geometry-core-*`: table spans, alignment, AST/WordPress output.
- `pandoc-archive-compression-streams-*`: bounded archive/compression helpers.
- `pandoc-pdf-engine-handoff-core-*`: PDF-output planning and fake-runner diagnostics.
- `pandoc-upstream-runner-deps-*`: Cabal/upstream-runner dependency audit.

## Quality Gates
- Focused PHP tests for every implementation slice.
- Example smoke when the slice affects WordPress handoff behavior.
- `git diff --check -- lanes/pandoc` for each worker handoff.
- No external converter, Office, TeX/PDF, Haskell runner, or online-service
  execution unless a later supervisor decision explicitly changes the gate.

## Final Acceptance Criteria
- Pandoc workers continuously advance the format-enabling support rows needed
  to reduce the `1,569` unmapped upstream-artifact gap.
- Integrated batches update Pandoc manifests/status and regenerate
  `porting.html` from the accepted source snapshot.
