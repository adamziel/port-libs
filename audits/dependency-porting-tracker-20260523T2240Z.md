# Dependency Porting Tracker - 2026-05-23 22:40 UTC

## Inputs Read

- `goal.md`
- `progress.md`
- `porting-summary.json`
- `tools/generate-dashboard.php`
- all 12 `lanes/*/lane-status.json` files
- all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files
- dependency-relevant `lanes/*/notes/*.md` evidence, with focused reads/searches across markerPDF, Pandoc, Difftastic, esbuild, LightningCSS, rclone, Syncthing, Readability, Gitoxide, Quadrable, Dolt, and libsqlite notes
- current `git status --short`

## Evidence Snapshot

- `goal.md` keeps the main invariant: native standard-PHP ports are the deliverable; wrappers around JS/Rust/Go/C binaries may only be temporary fixture/oracle tooling and must not count as implementation progress.
- The workspace is heavily dirty before this tracker update. `git status --short` shows many pre-existing tracked and untracked lane implementation, test, fixture, example, prompt, script, dashboard, and summary changes. This tracker update avoids those files.
- `progress.md` still has an old Active Lanes table near the top, while later audit entries record a moving dirty tree, active root harnesses, and dashboard/manifest mismatches. Dependency work therefore needs gates; starting every optional dependency now would add noise behind base-tool instability.
- `porting-summary.json` was generated at `2026-05-23 04:57:16 UTC` from source commit `bda83c6b93d4`, while `HEAD` moved during this tracker turn from sampled commit `1c5a4a1f` to `74a2d0b0`. The dashboard summary is stale relative to current lane metadata.
- Current lane status files report high focused PHP progress and zero lane-local PHP failures: Difftastic 363/0, Dolt 346/0, esbuild 305/0, Gitoxide 5557/0, libsqlite 281/0, LightningCSS 2175/0, markerPDF 410/0, Pandoc 271/0, Quadrable 186/0, rclone 681/0, Readability 199/0, and Syncthing 4284/0. These are lane-local counts, not a fresh accepted root baseline.
- markerPDF has the clearest optional-dependency pressure. Its manifest/status evidence names pdftext, pypdfium2, Surya OCR/layout, tabled-pdf, tabulate, Texify, Nougat, OCRMyPDF/Tesseract/Ghostscript, Pandoc/XeLaTeX helper tooling, PIL, Streamlit, FastAPI/Uvicorn, Poetry, Torch/model downloads, and workflow/publishing tools. The native lane already maps many supplied-data/planning boundaries without executing those tools.
- Pandoc is currently strong in Markdown/HTML/PlainText/LaTeX/WordPress handoff slices, but rich document formats remain the obvious next dependency frontier. The manifest records a static Haskell upstream inventory and an unexecuted full upstream runner due checkout and dependency-graph cost.
- Difftastic notes and manifests show Tree-sitter grammar/query/highlight evidence, UTF-16 and mostly-valid UTF-8 decoding, Unicode display width, binary signature handling, YAML/HTML/XML/JS/CSS sublanguage needs, and source-review display semantics.
- esbuild and LightningCSS have native lexer/transformer slices but source-map output is a shared rich-output dependency that should not be buried inside one lane.
- rclone has broad native filter/hash/check/copy/VFS coverage and live-provider/FUSE exclusions. Shared hashing, filtering/pathspecs, archive/compression, and charset-safe manifest reading are reusable dependency candidates.
- Syncthing has strong native BEP/session/config/status coverage. A bounded protobuf wire core remains useful only when protocol work needs deeper generic message fidelity; it should not preempt base-lane work.
- Gitoxide and Quadrable already carry hash-heavy native slices. A shared checksum/hash suite can reduce duplication, but it must preserve compatibility quirks rather than flattening every hash into a generic API.

## Decisions

- Created `dependency-backlog.json` with 17 gated items. This is a tracker artifact only; no dependency implementation work was started.
- Marked no item `active`. Critical/high candidates are available when a base lane reaches the matching activation gate; medium items are mostly deferred.
- Excluded whole applications and heavy model stacks as direct ports. The backlog does not add OpenOffice/LibreOffice, Tesseract, OCRMyPDF, Ghostscript, PDFium/PIL, Streamlit, FastAPI/Uvicorn, Poetry/publishing tooling, Torch, Surya, Texify, or Nougat as direct port targets.
- Preferred bounded native equivalents for runtime/conversion cores: package containers, parsers/serializers, PDF text dictionaries, PDF crop plans, OCR result ingestion, table geometry, Unicode repair/width, source maps, protobuf wire encoding, hashing, compression, and path filtering.
- Required every item to carry an activation gate, explicit scope boundary, reuse notes, and upstream/static/full-suite evidence expectations. This keeps upstream evidence honest for optional dependency libraries too.

## Backlog Topology

- Critical candidates: `shared-zip-package-core`, `xml-html5-dom-core`, `pdf-text-dictionary-core`, `layout-ocr-result-core`.
- High candidates: `docx-openxml-core`, `epub3-package-core`, `pdf-page-render-plan-core`, `table-geometry-core`, `unicode-text-repair-width`, `source-map-v3-core`, `checksum-hash-suite`.
- Deferred medium items: `odf-open-document-core`, `charset-encoding-core`, `tree-sitter-grammar-subset`, `protobuf-wire-core`, `archive-compression-streams`, `glob-filter-pathspec-core`.

## Root Harness Decision

Root harness execution is optional for this tracking-only change and should not run if another root harness is active. This update did not start the root PHP harness; the post-write process sample found active `php tools/run-tests.php` PID `3121853` plus focused Quadrable lane PID `3166198`. Verification for this task is limited to JSON parsing and diff hygiene.
