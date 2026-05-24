# Independent Audit - 2026-05-24T22:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `8f44859ec40c`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T22:45:26Z
HEAD: 8f44859ec40c
recent history: 8f44859e Refresh independent audit status; 28f088aa Refresh independent audit status; 0fe2cd56 Refresh independent audit status; 69b8b238 Record Syncthing handoff rejection; 2e8fae5c Refresh independent audit status; fdae6bf7 Refresh capacity queue source freshness; 83dde5f9 Add lane group integrator workflow; bf77aeb5 Add isolated lane worker workflow; f292e50e Refresh independent audit status; 2392e5c5 Refresh independent audit status; cf61fae5 Refresh esbuild integration status; 6cb369fd Integrate esbuild resolver slice
status rows including untracked: 25175
tracked dirty rows: 251
dirty shortstat: 251 files changed, 198513 insertions(+), 26361 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty or live handoff metadata
exact no-argument root process gate at initial audit samples: empty
final exact no-argument root process recheck: active PIDs 2460970 and 2461658, both owned by claude, both `php tools/run-tests.php`
root run by this audit: not started; the tree is not a frozen acceptance snapshot, and the final recheck found active duplicate root harnesses
dependency backlog: 37 rows; blocked 1, candidate 25, deferred 11; 0 active support ports
Pandoc-related support rows sampled: DOC/DOCX/OpenXML/PDF/EPUB/ODT/templates/citations/math/tables/package/XML/HTML/Unicode/charset/JSON/YAML/archive rows are visible but all sampled dependency-specific upstream denominator and PHP pass/fail fields are NULL
porting.html source snapshot: `main 6cb369fd15d0`, generated `2026-05-24 22:29:19 UTC`
```

Live dirty lane status is not accepted progress except where `latestCommit` already names an integrated commit. Current sampled handoffs include Fortran Difftastic, broad Dolt query-diff scalar work, a post-accepted esbuild package-tsconfig slice, Gitoxide tree path lookup, libsqlite numeric/string/JSON scalar work, LightningCSS gradient formatting, markerPDF PDF stream/filter work, Pandoc standalone `noscript`, Quadrable docopt metadata, rclone WebDAV gzip/property work, a fifteen-slice Readability pile, and Syncthing route-registry work. Most are explicitly `pending`, `uncommitted`, or root/integrator pending.

## Findings

1. **Critical - the repository still lacks a stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: during this audit `HEAD` moved past the prior audit commits to `8f44859ec40c`, status grew to `25175` untracked-inclusive rows, tracked dirty rows grew to `251`, and the dirty shortstat is now `251 files changed`. Every lane manifest/status file is dirty or live handoff metadata.

2. **Critical - root harness execution is no longer serialized.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen snapshot.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` was empty at initial sampled gates, but the final exact recheck found two active no-argument roots: PID `2460970` owned by `claude` for `00:20` and PID `2461658` owned by `claude` for `00:16`, both running `php tools/run-tests.php`. Neither should count as acceptance unless tied to a frozen snapshot; their concurrent presence violates the no-duplicate root-harness rule.

3. **Critical - accepted commits are being overwritten in the status surface by newer unaccepted lane metadata.**
   - Paths: `lanes/esbuild/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/pandoc/lane-status.json`, `porting.html`.
   - Goal requirement at risk: accepted progress must be tied to the exact committed slice and must not launder later dirty handoffs into progress.
   - Evidence: recent history contains accepted slices such as `6cb369fd` esbuild, `cc740727` Pandoc, `5fa9dbe6` markerPDF, `c65d5e26` LightningCSS, and `59f84374` Gitoxide, but live status now advertises later package-tsconfig, noscript, stream/filter, gradient, tree lookup, and other focused-only or pending handoffs.

4. **High - `porting.html` is stale and internally mixed with pending state.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: the page says it is a verified snapshot of `6cb369fd15d0`, while rows include newer pending status text such as LightningCSS `2026-05-24T22:55Z`, markerPDF `2026-05-25 00:08 UTC`, and multiple `pending`/`not com` commits. Average progress remains `93.3%`, overstating accepted parity.

5. **High - manifest/status schemas still make progress math unreliable.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
   - Evidence: common top-level fields such as `upstreamDenominator`, `mappedTests`, `phpPass`, and `phpFail` are absent or null in the sampled manifest query for nearly every lane, while status files use `phpPass` as assertion counts, PASS-line counts, or behavior counts depending on lane. markerPDF still reports more mapped rows than its static denominator in the dashboard (`176 / 78`), and Pandoc reports full mapping (`2276 / 2276`) without upstream Haskell runner parity.

6. **High - support-library coverage is still routing, not first-class port progress.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: backlog has 37 rows, 0 active support ports, and sampled rows have null dependency-specific denominator and PHP pass/fail fields. Candidate/deferred rows are useful gates but cannot count as implemented support-library progress.

7. **High - Pandoc rich-format requirements are visible but not satisfied.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must account for DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression with bounded rows and real upstream/spec evidence.
   - Evidence: those areas are present as candidate/deferred rows or reuse paths, but none is active with dependency-specific denominator, malformed/corrupt coverage, install-attempt evidence, or PHP pass/fail ledger. Current Pandoc status explicitly keeps DOCX/OpenXML, arbitrary HTML5 DOM parsing, PDF, citations/CSL, math, Unicode/charset, syntax highlighting, and package parsing behind future gates.

8. **High - base lanes keep crossing inactive support-library boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency expansion must be bounded, gated, tested, shared where appropriate, and not counted as reusable support progress before activation.
   - Evidence: esbuild resolver work is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work is ahead of inactive `webdav-protocol-core`, `xml-html5-dom-core`, and `archive-compression-streams`; Dolt/libsqlite JSON and SQL expression work are ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing protocol work remains ahead of inactive `protobuf-wire-core`; Gitoxide URL/wire/hash/archive needs remain inactive rows.

9. **High - markerPDF still risks counting dependency planning and narrow PDF stream work as structured-content extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied model callbacks, benchmark archive probing, and dependency inspection cannot count as port progress.
   - Evidence: live status is still narrow PDF stream/filter handling plus broader planning. Required support rows for PDF text dictionaries, page layout, OCR/layout result, tables, Unicode/charset, and archive/package handling remain inactive and have no dependency-specific pass/fail ledgers.

10. **Medium - Readability is too broad for one reviewable acceptance.**
    - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, `lanes/readability/tests/ArticleExtractorTest.php`.
    - Goal requirement at risk: prefer small correct slices over broad shallow ports, with mapped upstream tests and reviewable commits.
    - Evidence: current status names fifteen interleaved fixture/import slices across shared files. Even with green focused Mozilla/PHP evidence, this needs integrator hunk splitting or an explicit preserved-work package before root acceptance.

11. **Medium - missing-package/full-suite evidence remains too weak for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: support rows do not yet record install attempts, spec-suite runner attempts, malformed/corrupt ledgers, or dependency-specific pass/fail counts.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The exact no-argument process gate was empty in the initial audit samples, but the checkout was not stable enough for a meaningful audit-owned aggregate run: `HEAD` and status moved during the audit cycle, all lane manifests/status files are dirty or live handoff metadata, and `porting.html` is generated from an older accepted snapshot while including pending lane text. A final exact recheck found active no-argument root PIDs `2460970` and `2461658`, both owned by `claude`, so starting another root run would also be forbidden.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; stop duplicate no-argument root scheduling and record results for PIDs `2460970` and `2461658` without counting them unless a frozen snapshot can be proven; isolate exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units before updating dashboard math; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
