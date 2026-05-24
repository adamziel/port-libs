# Support Library Essential Dependency Routing - 2026-05-24T11:03Z

Scope read: `goal.md`, `progress.md`, `dependency-backlog.json`,
`audits/support-library-next-gate-watch-20260524T105424Z.md`, the current
`lanes/*/lane-status.json` files, the current
`lanes/*/UPSTREAM_TEST_MANIFEST.json` files, and the available
`.tmux-team/logs/port-dependency-audit-20260524T105746Z.log` evidence. The
requested `audits/essential-dependency-audit-20260524T105746Z.md` artifact was
not present at review time.

No lane source, lane status, lane manifest, dashboard, prompt/control file,
staging, commit, push, root harness, live-service provider test, credential
store, process environment, provider config, or secret-bearing input was
inspected or changed.

## Before / After

- Before review: 34 backlog rows; statuses were `blocked: 1`,
  `candidate: 22`, `deferred: 11`; priorities were `critical: 4`, `high: 24`,
  `medium: 6`; active support rows: 0.
- After review: 34 backlog rows; statuses remain `blocked: 1`,
  `candidate: 22`, `deferred: 11`; priorities remain `critical: 4`,
  `high: 24`, `medium: 6`; active support rows: 0.
- Rows added: none.
- Rows refined: none.
- Rows activated: none.

## Decision

No `dependency-backlog.json` change was needed. The latest directive asks for
important support libraries required by each tool's essential rich function,
especially Pandoc conversion dependencies, while avoiding whole applications
and converter shell-outs. The current backlog already represents those Pandoc
dependencies as bounded native support components with activation gates,
upstream/spec denominator expectations, PHP evidence expectations,
malformed/corrupt/error coverage, explicit non-goals, service/secret
exclusions, and reuse notes.

Current lane statuses still record `latestCommit` as pending/uncommitted or
root/integrator verification pending across the relevant lanes. Focused lane
tests and worker logs are useful lane-local evidence, but they do not satisfy
the activation rule. No row should become active until a base lane is accepted
green enough for the slice or accepted-blocked on that component from a frozen
snapshot.

## Intentionally Not Duplicated

Important Pandoc support rows already present at the right granularity:

- `shared-zip-package-core` covers ZIP/package containers for DOCX, EPUB, ODT,
  markerPDF benchmark archives, and rclone archive-provider reuse.
- `docx-openxml-core` covers DOCX/OpenXML conversion semantics above the shared
  package/XML layers.
- `legacy-doc-cfb-core` covers bounded legacy Word `.doc` / CFB / MS-DOC
  extraction without Word, LibreOffice/OpenOffice, antiword/wv, Pandoc, or
  converter subprocesses.
- `epub3-package-core` covers EPUB package/spine/nav/metadata/assets for
  Pandoc and Readability reuse.
- `odf-open-document-core` covers ODT/OpenDocument package/XML/text/list/table
  mapping without porting LibreOffice/OpenOffice.
- `pandoc-doctemplates-core` covers Pandoc doctemplates rendering as a bounded
  writer dependency.
- `citation-bibliography-csl-core` covers citations, CSL, bibliography data,
  and rendered reference-list behavior without bibliography-manager apps,
  network lookup, BibTeX/Biber subprocesses, or citeproc service wrappers.
- `math-tex-conversion-core` covers inline/display math, bounded TeX math,
  MathML/LaTeX preservation, and equation handoff without TeX engines,
  MathJax/KaTeX runtimes, model stacks, or equation-recognition engines.
- `pandoc-pdf-engine-handoff-core` covers Pandoc PDF output handoff planning
  and diagnostics without implementing or executing PDF engines.
- `pdf-text-dictionary-core` covers searchable PDF text/dictionary extraction
  for markerPDF now and Pandoc PDF input handoff later, excluding pdftext
  subprocesses, Poppler, PDFium, Ghostscript, pypdfium, OCR/model engines, and
  external converters.
- `pdf-page-render-plan-core` covers PDF page-tree/page-box/crop/render-plan
  contracts without porting PDFium, PIL, Ghostscript, Poppler, or raster
  engines.
- `table-geometry-core` covers table geometry and formatter handoff across
  markerPDF, Pandoc, and Readability.
- `xml-html5-dom-core` covers XML/HTML parsing/serialization for Pandoc rich
  package/XML payloads, HTML/DocBook, Readability, markerPDF, Difftastic, and
  WebDAV XML.
- `unicode-text-repair-width` covers Unicode repair, normalization, display
  width, and segmentation needed by Pandoc tables and other lanes.
- `charset-encoding-core` covers PDFDocEncoding, legacy DOC/HTML charset, and
  non-UTF-8 import boundaries.
- `json-json5-document-core` covers Pandoc metadata JSON reuse alongside
  libsqlite, esbuild, Readability, rclone, Syncthing, and Dolt JSON needs.
- `archive-compression-streams` covers lower-level gzip/deflate/tar/LZ4 stream
  helpers for package/archive dependencies while keeping ZIP container behavior
  in `shared-zip-package-core`.

No new row was added for Office/LibreOffice/Word, Ghostscript, PDFium, Poppler,
Tesseract/OCRMyPDF, model stacks, service wrappers, browser runtimes, external
converter shell-outs, live provider APIs, cloud remotes, OAuth/browser auth,
or credential-bearing configs. Those remain explicit non-goals or exclusions in
the relevant rows.

## Row Routing

- Pandoc DOC, DOCX/OpenXML, EPUB, ODT/OpenDocument, doctemplates, citations,
  math, rich tables, PDF input/output handoff, ZIP/package, XML/HTML,
  Unicode/charset, JSON metadata, and archive/compression needs are already
  represented. Keep the existing rows unchanged rather than cloning
  Pandoc-specific duplicates.
- markerPDF PDF text, page, layout/OCR-result, and table work remains
  candidate/deferred/blocked according to the existing row gates. Current
  markerPDF evidence is lane-local and root/integrator pending.
- esbuild source-map evidence remains lane-local; `source-map-v3-core` stays
  candidate and inactive.
- Readability URL/DOM/charset evidence remains lane-local; `url-percent-encoding-core`,
  `xml-html5-dom-core`, `charset-encoding-core`, and
  `unicode-text-repair-width` stay inactive.
- Dolt/libsqlite SQL and JSON work remains lane-local; `sql-expression-semantics-core`
  and `json-json5-document-core` stay inactive.
- Syncthing `/qr/` work remains lane-local with `latestCommit` pending, so
  `qr-code-matrix-core` stays blocked rather than active.
- rclone WebDAV/archive/accounting work remains lane-local; WebDAV, URL,
  provider-metadata, ZIP/package, and archive/compression rows stay inactive.

## Validation Run

- `jq empty dependency-backlog.json` passed.
- Scoped trailing-whitespace checks on `progress.md` and this audit artifact
  passed.
- `git diff --check -- progress.md audits/support-library-essential-dependency-routing-20260524T105940Z.md`
  passed.

## Remaining Activation Gates

- Freeze active writers/status publishers and take a stable source snapshot.
- Accept one coherent base-lane batch from that frozen snapshot, or mark the
  lane accepted-blocked on exactly one support component.
- For the matching support row, require a dependency-specific upstream/spec
  denominator, mapped fixtures, native PHP pass/fail counts, malformed/
  corrupt/error cases, full/upstream-suite expectation, explicit non-goals, and
  no credit for shell-outs, whole applications, live services, provider
  remotes, OAuth/browser auth state, credential stores, process environments,
  or secret-bearing inputs.
