# Independent Audit - 2026-05-25T04:54Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every sampled `lanes/*/lane-status.json`, and recent Git history through `4cbc7059033c`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-25T04:54Z
HEAD: 4cbc7059033c
recent history: 4cbc7059 Record deferred isolated marker rebase conflicts; c90a173e Record superseded isolated rework markers; 244c850c Integrate isolated rclone OneDrive Put rework note; 0d6c6b9a Integrate isolated readability Kinja annotation rework; 766b50e9 Integrate isolated syncthing watcher restart patch
status rows including untracked: 28887
tracked dirty rows: 364
dirty shortstat: 311 files changed, 194989 insertions(+), 66764 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files remain dirty/live handoff metadata
exact no-argument root process gate: empty at audit sample
root run by this audit: not started; the checkout is not a frozen acceptance snapshot
dependency backlog: 37 items; 25 candidate, 11 deferred, 1 blocked; 0 active support-library ports
porting.html source snapshot: main 6cb369fd15d0, generated 2026-05-24 22:29:19 UTC, dashboard average progress 93.3%
```

## Findings

1. **Critical - aggregate acceptance is still blocked by the moving dirty checkout.**
   - Paths: `progress.md`, `tools/run-tests.php`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: commit small, reviewable slices with passing tests and periodically run repo-wide tests from a stable accepted snapshot.
   - Evidence: current `HEAD` is `4cbc7059033c`, while the checkout has `28887` untracked-inclusive status rows, `364` tracked dirty rows, and `311 files changed`. All 12 lane manifests and all 12 lane-status files are dirty/live handoff metadata. No lane implementation output is accepted by this audit.

2. **Critical - root verification would be unattributable from the current tree.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen accepted batch.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` was empty at the audit sample, but the tree is a broad mixed handoff pile with hundreds of tracked dirty files. A no-argument root result from this state would not prove any single reviewable batch.

3. **Critical - dashboard/status alignment is not trustworthy.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `porting.html` must show denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit for a consistent accepted snapshot.
   - Evidence: `porting.html` claims source commit `6cb369fd15d0` and average progress `93.3%`, while current `HEAD` is `4cbc7059033c`. Rows include newer pending lane text and `pending`/`not committed` commit fields, so the page mixes accepted snapshot metadata with unaccepted handoffs.

4. **High - manifest/status schemas still prevent reliable progress math.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: every lane needs comparable upstream denominator, mapped tests, PHP pass/fail, blocker, and commit fields.
   - Evidence: manifests continue to store denominators and mapped counts in lane-specific nested structures such as `benchmarkDenominator`, while status files compress unrelated units into prose. `phpPass` still mixes assertions, behavior checks, selected test files, upstream PASS lines, and fixture counts depending on lane.

5. **High - support-library rows remain routing metadata, not first-class ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: `dependency-backlog.json` still has 37 rows and 0 active support ports. The rows define gates and prose test expectations but do not yet provide dependency-specific denominator/pass-fail ledgers or accepted native components.

6. **High - Pandoc rich-format coverage is visible but not satisfied.**
   - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`.
   - Goal requirement at risk: Pandoc must account for DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression behind real gates with upstream/spec evidence.
   - Evidence: those needs are routed through candidate/deferred rows, including `legacy-doc-cfb-core`, `docx-openxml-core`, `pandoc-pdf-engine-handoff-core`, `epub3-package-core`, `odf-open-document-core`, `citation-bibliography-csl-core`, `math-tex-conversion-core`, `table-geometry-core`, `pandoc-doctemplates-core`, `shared-zip-package-core`, `xml-html5-dom-core`, Unicode/charset, JSON/YAML, syntax highlighting, and archive rows. None has accepted dependency-suite attempts, malformed/corrupt ledgers, bounded install-attempt evidence, or PHP pass/fail counts.

7. **High - base lanes keep expanding into inactive reusable dependency territory.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency expansion must be bounded, gated, tested, shared where appropriate, and not counted as reusable support progress before activation.
   - Evidence: esbuild resolver work remains ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/archive-adjacent work remains ahead of inactive WebDAV/XML/archive rows; Dolt/libsqlite JSON and SQL expression work remain ahead of inactive JSON/SQL rows; Syncthing route/protocol work remains ahead of inactive protobuf/QR rows; Gitoxide URL/wire/hash/archive needs remain inactive rows.

8. **High - markerPDF still risks over-crediting low-level PDF plumbing as structured extraction.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline suitable for WordPress import and document conversion.
   - Evidence: current markerPDF progress is still dominated by narrow stream/filter/font/operator handoffs, including MacRoman/simple-font and content-stream decoding. Required support rows for PDF text dictionaries, page layout, OCR/layout result ingestion, table geometry, Unicode/charset, and archive/package handling remain inactive and lack dependency-specific pass/fail ledgers.

9. **Medium - broad dirty lane piles are still not reviewable units.**
   - Paths: `lanes/readability/*`, `lanes/quadrable/*`, `lanes/gitoxide/*`, `lanes/rclone/*`, `lanes/syncthing/*`, `lanes/dolt/*`, `lanes/libsqlite/*`.
   - Goal requirement at risk: prefer small correct slices over broad shallow ports, with committed passing tests.
   - Evidence: lane-status files advertise many interleaved uncommitted slices in the same tracked files. Readability reports eighteen interleaved slices; rclone, syncthing, gitoxide, quadrable, libsqlite, and dolt each describe broad pending stacks whose verification requires an integrator freeze, not audit-owned aggregate testing.

10. **Medium - missing-package/full-suite evidence remains too weak for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs were attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: the support tracker lacks install-attempt fields, upstream/spec-suite attempt fields, malformed/corrupt-case ledgers, and dependency-specific pass/fail counts. This is especially important for Pandoc package/document formats and markerPDF/rclone archive or XML-adjacent gates.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The required exact process gate was empty, but the checkout is not stable enough: it contains `28887` status rows, `364` tracked dirty rows, dirty manifests/status files for every lane, and no frozen owner-free acceptance batch.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; isolate exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units before updating dashboard math; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
