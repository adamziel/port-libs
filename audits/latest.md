# Independent Audit - 2026-05-24T23:18Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `80bd51d82b96`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T23:14:44Z-23:18Z
HEAD: 80bd51d82b96
recent history: 80bd51d Route isolated handoffs to clean integrator; 2c5f122 Refresh independent audit status; e96090b Improve isolated worker watchdog backpressure; 51a395f Refresh independent audit status; b6959f8 Correct isolated integration root counts; 4cb1c5 Integrate Syncthing folder scan route registry slice; b81017 Integrate Pandoc inline code attribute slice; 958011 Integrate markerPDF literal escape slice
status rows including untracked: 25477
tracked dirty rows: 252
dirty shortstat: 252 files changed, 194190 insertions(+), 27124 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty or live handoff metadata
exact no-argument root process gate: initial sample found PID 2549613 (`php tools/run-tests.php`); final pre-write recheck was empty
root run by this audit: not started; the tree is not a frozen acceptance snapshot, and an active root PID was observed during this audit window
dependency backlog: 37 rows; 0 active support ports; Pandoc/support rows still have blank dependency-specific denominator and PHP pass/fail fields in the tracker rows sampled
porting.html source snapshot: `main 6cb369fd15d0`, generated `2026-05-24 22:29:19 UTC`, average progress `93.3%`
```

Live dirty lane status is not accepted progress except where `latestCommit` already names an integrated commit. Current sampled handoffs include Difftastic Erlang work, broad Dolt query-diff scalar work, post-accepted esbuild package-tsconfig work, Gitoxide tree-offset work, libsqlite scalar/JSON work, LightningCSS formatter work, markerPDF indirect PDF stream handling, Pandoc standalone `ins` HTML-reader work, Quadrable docopt metadata work, rclone WebDAV PROPFIND ordering, a sixteen-slice Readability pile, and Syncthing expanded route-registry work. Most are explicitly `pending`, `uncommitted`, `not committed`, or root/integrator pending.

## Findings

1. **Critical - there is still no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` is `80bd51d82b96`, but the checkout has `25477` untracked-inclusive status rows, `252` tracked dirty rows, and `252 files changed` in the dirty shortstat. Every lane manifest/status file is dirty or live handoff metadata, so a root result would describe a mixed pile rather than one reviewable batch.

2. **Critical - root harness execution remains unattributable.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen snapshot.
   - Evidence: the required exact gate initially found active root PID `2549613` (`php tools/run-tests.php`), so this audit correctly did not start a duplicate. A later recheck was empty, but the tree remained a moving dirty aggregate with all lane status/manifests live. Starting a root run from this state would still not prove one accepted slice is safe.

3. **Critical - accepted dashboard state is mixed with newer unaccepted lane metadata.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: dashboard status must show accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit for the same snapshot.
   - Evidence: `porting.html` says it is a verified snapshot of source commit `6cb369fd15d0`, but rows include pending/newer text such as markerPDF `2026-05-25 00:08 UTC`, `pending`, `not com`, `HEAD 9c`, and average progress `93.3%`. This is neither a clean accepted snapshot nor a reliable live-work dashboard.

4. **High - manifest/status schemas still make progress math unreliable.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: each lane needs comparable upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit fields.
   - Evidence: all sampled lane-status files leave top-level `upstreamDenominator` and `upstreamMapped` null while `phpPass` means behavior counts, assertion counts, or PASS-line counts depending on lane. Manifest denominators live in nested lane-specific shapes. Dashboard ratios therefore overstate parity, for example markerPDF `176 / 78` mapped and Pandoc `2276 / 2276` mapped without upstream Haskell runner parity.

5. **High - support-library coverage is still routing, not first-class port progress.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has 37 rows and 0 active support ports. Sampled dependency rows expose empty denominator/pass-fail cells. Candidate/deferred rows are useful routing, but they cannot count as implemented support-library progress.

6. **High - Pandoc rich-format requirements are visible but not satisfied.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must account for DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression with bounded rows and real upstream/spec evidence.
   - Evidence: these areas are present as candidate/deferred rows or reuse paths, but none is active with dependency-specific denominator, malformed/corrupt coverage, bounded install-attempt evidence, or PHP pass/fail ledger. Current Pandoc status explicitly keeps DOCX/OpenXML ingestion, arbitrary HTML5 DOM parsing, PDF, citation/CSL, math, Unicode/charset, syntax highlighting, and package parsing behind future gates.

7. **High - base lanes keep crossing inactive support-library boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency expansion must be bounded, gated, tested, shared where appropriate, and not counted as reusable support progress before activation.
   - Evidence: esbuild resolver work is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work is ahead of inactive `webdav-protocol-core`, `xml-html5-dom-core`, and `archive-compression-streams`; Dolt/libsqlite JSON and SQL expression work are ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing route/protocol work remains ahead of inactive reusable protocol rows; Gitoxide URL/wire/hash/archive needs remain inactive rows.

8. **High - markerPDF still risks over-crediting PDF plumbing as structured-content extraction.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied model callbacks, benchmark archive probing, narrow stream handling, and dependency inspection cannot count as full extraction progress.
   - Evidence: live status is still narrow PDF stream/filter handling. Required support rows for PDF text dictionaries, page layout, OCR/layout result, tables, Unicode/charset, and archive/package handling remain inactive and have no dependency-specific pass/fail ledgers.

9. **Medium - Readability remains too broad for one reviewable acceptance.**
   - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, `lanes/readability/tests/ArticleExtractorTest.php`.
   - Goal requirement at risk: prefer small correct slices over broad shallow ports, with mapped upstream tests and reviewable commits.
   - Evidence: current status names sixteen interleaved fixture/import slices across shared files. Even with green focused Mozilla/PHP evidence, this needs integrator hunk splitting or an explicit preserved-work package before root acceptance.

10. **Medium - missing-package/full-suite evidence remains too weak for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: support rows do not yet record install attempts, spec-suite runner attempts, malformed/corrupt ledgers, or dependency-specific pass/fail counts.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The exact no-argument process gate initially found active root PID `2549613` (`php tools/run-tests.php`), and a later recheck was empty. No duplicate was started. The checkout is not stable enough for a meaningful audit-owned aggregate run because all lane manifests/status files remain dirty or live handoff metadata and `porting.html` is generated from an older accepted snapshot while including pending lane text.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; isolate exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units before updating dashboard math; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
