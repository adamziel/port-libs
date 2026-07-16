You are one scout in the supervised native PHP porting team at `/home/claude/port-libs`.

This session is audit-only. Do not edit implementation files, lane files, `dependency-backlog.json`, `progress.md`, `porting.html`, or `porting-summary.json`. Do not stage, commit, push, reset, or revert. Your owned output is:

- `audits/doc-format-dependency-scout-20260524T085334Z.md`

Objective context:

The supervisor received a directional nudge: for every tool, make sure important optional upstream libraries needed for essential rich function are represented and eventually ported as bounded native PHP components. For Pandoc specifically, rich conversion needs data/document formats and helpers such as DOC/DOCX, PDF output handoff and PDF text input handoff, EPUB, ODT/OpenDocument, citations/CSL, math/TeX, tables, ZIP/package containers, XML/HTML, Unicode/charset, and compression/archive pieces. Do not propose porting whole applications such as OpenOffice/LibreOffice, Word, Ghostscript/PDFium/Poppler, Tesseract/OCRMyPDF, model stacks, service wrappers, or converter shell-outs as progress.

Assigned task:

Audit the document/PDF conversion side of the tracker and lane evidence. Focus on Pandoc, markerPDF, and Readability overlap. Determine whether `dependency-backlog.json` currently has enough bounded support-library rows for essential rich conversion. Identify missing rows, rows that should be reprioritized, and rows whose activation gates should be sharpened.

Ground truth to inspect:

- `goal.md`
- `progress.md`, especially `Auxiliary Dependency Backlog`
- `dependency-backlog.json`
- `audits/support-library-progress-tracker-20260524T083724Z.md`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/notes/upstream-inventory.md`
- `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`
- `lanes/markerpdf/lane-status.json`
- `lanes/markerpdf/notes/upstream-test-inventory.md`
- `lanes/readability/UPSTREAM_TEST_MANIFEST.json`
- `lanes/readability/lane-status.json`

Rules:

- Do not read, print, copy, or dump secret values. Do not inspect process environments, credential stores, provider config files, OAuth/browser auth state, cloud remotes, or secret-bearing inputs.
- Use bounded local reads and `jq`; do not run full root tests or dashboard generation.
- If you need current upstream dependency facts and local manifests are insufficient, use targeted web or repository lookups only for public upstream documentation/source metadata. Keep quotes short and cite URLs in the audit if used.
- Treat optional upstream libraries as in scope only when they unlock essential conversion behavior. Keep every proposed project at the smallest useful native component boundary.
- Do not propose activating all dependency projects at once. Recommend priority order and the concrete base-lane gate that should open each one.
- Every proposed new or changed tracker row must include: id, neededBy lanes, essential capability, scope boundary, activation gate, upstream/spec denominator, expected PHP evidence, malformed/corrupt cases, reuse notes, and explicit no-shell-out/no-whole-application exclusions.
- Record when existing rows are sufficient and should not be duplicated.

Completion criteria:

1. Write `audits/doc-format-dependency-scout-20260524T085334Z.md` with:
   - current tracker coverage summary;
   - recommended additions, if any;
   - recommended priority/gate changes, if any;
   - explicit rejects for whole applications/external engines;
   - the exact local files inspected;
   - checks run.
2. Run `jq empty dependency-backlog.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json lanes/readability/UPSTREAM_TEST_MANIFEST.json lanes/readability/lane-status.json`.
3. Run `git diff --check -- audits/doc-format-dependency-scout-20260524T085334Z.md`.

When done, report only:

- artifact path;
- key recommended tracker changes;
- checks run;
- unresolved blockers.
