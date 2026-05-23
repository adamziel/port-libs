You are one worker in a supervised team. You are not alone in the workspace.
Do not revert or overwrite work outside your assigned scope.
Produce inspectable output before declaring completion.

Objective context:
The portfolio is porting native PHP equivalents for the tools in `goal.md`.
The supervisor received a direction to include important supporting libraries
required for each tool's essential rich function. These are in scope only when
they unlock user-visible conversion/runtime capability. Do not expand into
whole applications or broad ecosystems.

Assigned task:
Audit `dependency-backlog.json` against all lanes and the user's latest
direction. Make sure the tracker covers important supporting libraries for each
tool at the right granularity:

- Pandoc: rich document import/export needs such as DOCX/OpenXML, legacy `.doc`
  CFB/MS-DOC, EPUB/ODT package handling, PDF-oriented text/page/table/OCR
  handoff, citations/bibliography/CSL only if needed for conversion behavior,
  math/TeX parsing only as a bounded conversion component, and shared ZIP,
  XML/HTML, Unicode, charset, and table foundations.
- markerPDF: PDF text dictionaries, page/crop planning, OCR/layout result
  ingestion, table geometry, image/metadata handoff, and malformed/corrupt PDF
  evidence without counting PDFium/Ghostscript/Tesseract/Surya/model wrappers.
- esbuild and LightningCSS: source maps, parser/tokenizer helpers, Unicode and
  charset handling, and only the bounded syntax/transformation support needed
  for current native PHP behavior.
- rclone and Syncthing: checksums, compression/archive streams, glob/path
  filters, protobuf/wire helpers, local protocol/data encoders, and local-only
  provider normalization; avoid live-service provider tests by default.
- Gitoxide, Dolt, Difftastic, and Quadrable: hashes/checksums, compression/pack
  helpers, tree-sitter or bounded grammar support, encoding/Unicode handling,
  SQL/storage codecs, and protocol helpers where these unlock native behavior.

Owned scope:
- `dependency-backlog.json`
- `audits/dependency-support-libs-auditor-20260523T2336Z.md`
- If and only if a JSON change requires dashboard regeneration for validation,
  you may run `php tools/generate-dashboard.php`, but do not publish, commit, or
  push. Avoid editing `progress.md` unless needed for a concise status note.

Completion criteria:
- Read `goal.md`, `dependency-backlog.json`, `progress.md`, and the lane
  manifests/status files before changing anything.
- Decide whether the current 18-item backlog is sufficient. If it is missing a
  clearly important bounded supporting library, add a new item with:
  `id`, `name`, `source`, `lanes`, `essentialCapability`, `scopeBoundary`,
  `priority`, `status`, `activationGate`, `testExpectation`, `reuseNotes`, and
  `blocker`.
- Keep priority honest. Do not mark every optional dependency as critical.
  Candidate work should be gated behind base-tool progress or a real blocker.
- Every `testExpectation` must require a dependency-specific upstream/spec
  denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases,
  and a no-shell-out/no-external-engine rule.
- Do not count wrappers around OpenOffice/LibreOffice, Pandoc, Tesseract,
  PDFium, Ghostscript, Poppler, cloud providers, external model stacks, or
  shell commands as native dependency progress.
- Prefer reuse across lanes when the same bounded library helps more than one
  tool.
- Do not touch `lanes/*/src`, `lanes/*/tests`, lane fixtures, secrets,
  provider credentials, or live-service remotes.
- Do not run root `php tools/run-tests.php`. Run only cheap validation:
  `jq empty dependency-backlog.json porting-summary.json` if present,
  `php -l tools/generate-dashboard.php`, `php tools/generate-dashboard.php`
  only if you changed the backlog, and `git diff --check`.

When done, report only:
- files changed or artifacts created;
- missing items added or explicit decision that none were needed;
- validation commands and exact results;
- unresolved blockers or next dependency work.
