# Independent Audit - 2026-05-24T22:55Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `0578b4f42d41`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T22:55:21Z
HEAD: 0578b4f42d41
recent history: 0578b4f Refresh independent audit status; e77b7bc Teach integrators to consume isolated marker metadata; fa51cbf Refresh independent audit status; e1ec427 Add esbuild tsconfig runtime fixture; b38a88a Record Readability handoff rejection; cd2c083 Refresh independent audit status; e0062d7 Record markerPDF handoff rejection; 87171c0 Make watchdog lane restarts isolated; 8f44859 Record libsqlite handoff rejection; 28f088a Refresh independent audit status; 0fe2cd5 Refresh independent audit status; 69b8b23 Record Syncthing handoff rejection
main...origin/main: ahead 1081, behind 68
status rows including untracked: 25268
tracked dirty rows: 247
dirty shortstat: 247 files changed, 198377 insertions(+), 26348 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty or live handoff metadata
exact no-argument root process gate: empty at audit samples
root run by this audit: not started; the tree is not a frozen acceptance snapshot
dependency backlog: 37 rows; blocked 1, candidate 25, deferred 11; 0 active support ports; 37 null dependency-specific denominator fields; 37 null PHP pass/fail fields
porting.html source snapshot: `main 6cb369fd15d0`, generated `2026-05-24 22:29:19 UTC`
```

Live dirty lane status is not accepted progress except where `latestCommit` already names an integrated commit. Current sampled handoffs include Difftastic Erlang function work, broad Dolt query-diff scalar work, post-accepted esbuild package-tsconfig work, Gitoxide tree offset work, libsqlite scalar/JSON work, LightningCSS gradient formatting, markerPDF PDF stream/filter work, Pandoc standalone `ins` HTML-reader work, Quadrable docopt metadata work, rclone WebDAV PROPFIND ordering, a sixteen-slice Readability pile, and Syncthing route-registry work. Most are explicitly `pending`, `uncommitted`, `not committed`, or root/integrator pending.

## Findings

1. **Critical - the repository still lacks a stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` is `0578b4f42d41`, but the checkout has `25268` untracked-inclusive status rows, `247` tracked dirty rows, and `247 files changed` in the dirty shortstat. Every lane manifest/status file is dirty or live handoff metadata, so a root result would describe a mixed pile rather than a reviewable batch.

2. **Critical - root harness execution remains unattributable even when the process gate is empty.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen snapshot.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php$'` gate was empty at audit samples, but the tree is still moving handoff state. Starting `php tools/run-tests.php` now would not prove any single owner-free slice is safe, and recent history shows repeated root runs that could not be tied back to a frozen status snapshot.

3. **Critical - accepted commits are being overwritten in the status surface by newer unaccepted lane metadata.**
   - Paths: `lanes/esbuild/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/pandoc/lane-status.json`, `porting.html`.
   - Goal requirement at risk: accepted progress must be tied to the exact committed slice and must not launder later dirty handoffs into progress.
   - Evidence: `porting.html` names accepted source snapshot `6cb369fd15d0`, while live statuses advertise later package-tsconfig, `ins` HTML-reader, PDF stream/filter, gradient, tree-offset, and route-registry handoffs that are pending or uncommitted.

4. **High - `porting.html` is stale and internally mixed with pending state.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: the page says it is a verified snapshot of `6cb369fd15d0`, while rows include newer pending status text such as LightningCSS `2026-05-24T22:55Z`, markerPDF `2026-05-25 00:08 UTC`, `pending`, `not com`, `HEAD 9c`, and average progress `93.3%`. That is not an accepted snapshot.

5. **High - manifest/status schemas still make progress math unreliable.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
   - Evidence: lane statuses expose `phpPass` as behavior counts, assertion counts, or PASS-line counts depending on lane, while `upstreamMapped` and `upstreamDenominator` are null in the sampled status files. Manifests use nested denominator/prose fields rather than common top-level count fields. markerPDF still maps more rows than its static denominator (`176 / 78`), and Pandoc reports `2276 / 2276` mapped without upstream Haskell runner parity.

6. **High - support-library coverage is still routing, not first-class port progress.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: backlog has 37 rows, 0 active support ports, 37 null dependency-specific denominator fields, and 37 null PHP pass/fail fields. Candidate/deferred rows are useful routing, but they cannot count as implemented support-library progress.

7. **High - Pandoc rich-format requirements are visible but not satisfied.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must account for DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression with bounded rows and real upstream/spec evidence.
   - Evidence: those areas are present as candidate/deferred rows or reuse paths, but none is active with dependency-specific denominator, malformed/corrupt coverage, bounded `sudo -n` install-attempt evidence, or PHP pass/fail ledger. Current Pandoc status explicitly keeps DOCX/OpenXML ingestion, arbitrary HTML5 DOM parsing, PDF, citation/CSL, math, Unicode/charset, syntax highlighting, and package parsing behind future gates.

8. **High - base lanes keep crossing inactive support-library boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency expansion must be bounded, gated, tested, shared where appropriate, and not counted as reusable support progress before activation.
   - Evidence: esbuild resolver work is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work is ahead of inactive `webdav-protocol-core`, `xml-html5-dom-core`, and `archive-compression-streams`; Dolt/libsqlite JSON and SQL expression work are ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing protocol work remains ahead of inactive `protobuf-wire-core`; Gitoxide URL/wire/hash/archive needs remain inactive rows.

9. **High - markerPDF still risks counting dependency planning and narrow PDF stream work as structured-content extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied model callbacks, benchmark archive probing, and dependency inspection cannot count as port progress.
   - Evidence: live status is still narrow PDF stream/filter handling plus broader planning. Required support rows for PDF text dictionaries, page layout, OCR/layout result, tables, Unicode/charset, and archive/package handling remain inactive and have no dependency-specific pass/fail ledgers.

10. **Medium - Readability remains too broad for one reviewable acceptance.**
    - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, `lanes/readability/tests/ArticleExtractorTest.php`.
    - Goal requirement at risk: prefer small correct slices over broad shallow ports, with mapped upstream tests and reviewable commits.
    - Evidence: current status names sixteen interleaved fixture/import slices across shared files. Even with green focused Mozilla/PHP evidence, this needs integrator hunk splitting or an explicit preserved-work package before root acceptance.

11. **Medium - missing-package/full-suite evidence remains too weak for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: support rows do not yet record install attempts, spec-suite runner attempts, malformed/corrupt ledgers, or dependency-specific pass/fail counts.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The exact no-argument process gate was empty, but the checkout is not stable enough for a meaningful audit-owned aggregate run: all lane manifests/status files remain dirty or live handoff metadata, and `porting.html` is generated from an older accepted snapshot while including pending lane text.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; isolate exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units before updating dashboard math; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
