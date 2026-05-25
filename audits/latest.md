# Independent Audit - 2026-05-25T00:13Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `7452fe33e4f2`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-25T00:13Z
HEAD: 7452fe33e4f2
recent history: 7452fe33 Record isolated queue cleanup; d35bf1db Record remaining isolated queue deferrals; 7b931c65 Record isolated patch queue deferrals; 66c8615c Integrate isolated syncthing watchdog-next-20260524T233852Z slice; 4a58c408 Integrate isolated readability watchdog-next-20260524T233749Z slice; d59ae99c Integrate isolated quadrable watchdog-next-20260524T233443Z slice; 4706a261 Integrate isolated pandoc watchdog-next-20260524T233545Z slice; 920af0c1 Integrate isolated lightningcss watchdog-next-20260524T233033Z slice
status rows including untracked: 26199
tracked dirty rows: 288
dirty shortstat: 257 files changed, 195703 insertions(+), 27456 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty or live handoff metadata
exact no-argument root process gate: empty at audit sample
root run by this audit: not started; the tree is not a frozen acceptance snapshot
dependency backlog: 37 rows; 0 active support ports; all 37 rows lack dependency-specific denominator/pass-fail fields in the tracker shape sampled
porting.html/summary source snapshot: main 6cb369fd15d0, generated 2026-05-24 22:29:19 UTC, dashboard average progress 93.3%
```

Live dirty lane status is not accepted progress except where `latestCommit` already names an integrated commit. Current sampled handoffs remain broad and mixed: Difftastic Perl/top-level constant work, Dolt scalar/query-diff work, Esbuild resolver follow-up, Gitoxide SHA-256 tree/object-format work, libsqlite scalar/JSON work, LightningCSS property formatter work, markerPDF MacRoman/simple-font PDF work, Pandoc HTML reader SVG work, Quadrable metadata work, rclone WebDAV condition/XML/gzip work, Readability multi-fixture work, and Syncthing route/protocol work. Most are explicitly `pending`, `uncommitted`, `not committed`, or root/integrator pending.

## Findings

1. **Critical - there is still no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` is `7452fe33e4f2`, but the checkout has `26199` untracked-inclusive status rows, `288` tracked dirty rows, and `257 files changed` in the dirty shortstat. Every lane manifest/status file is dirty or live handoff metadata, so a root result would describe a mixed pile rather than one reviewable batch.

2. **Critical - root harness execution would still be unattributable.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen snapshot.
   - Evidence: the required exact gate `pgrep -af '^php tools/run-tests\.php$'` was empty at this audit sample, but the tree is a moving dirty aggregate with all lane status/manifests live. Starting a no-argument root run from this state would not prove one accepted slice is safe.

3. **Critical - accepted dashboard state is mixed with newer unaccepted lane metadata.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: dashboard status must show accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit for the same snapshot.
   - Evidence: `porting.html` says it is a verified snapshot of source commit `6cb369fd15d0`, but rows include pending/newer text such as markerPDF `2026-05-25`, `pending`, `not com`, and `HEAD 920af0c...`, while `HEAD` is `7452fe33e4f2`. This is neither a clean accepted snapshot nor a reliable live-work dashboard.

4. **High - manifest/status schemas still make progress math unreliable.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: each lane needs comparable upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit fields.
   - Evidence: sampled lane-status files leave top-level denominator/mapped fields absent or null while `phpPass` means behavior counts, assertion counts, or PASS-line counts depending on lane. Manifest denominators live in nested lane-specific shapes (`benchmarkDenominator` arrays with per-lane fields). Dashboard ratios therefore overstate parity, for example markerPDF `176 / 78` mapped and Pandoc `2276 / 2276` mapped without full upstream runner parity.

5. **High - support-library coverage is still routing, not first-class port progress.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has 37 rows and 0 active support ports. All sampled rows lack dependency-specific denominator/pass-fail fields. Candidate/deferred rows are useful routing, but they cannot count as implemented support-library progress.

6. **High - Pandoc rich-format requirements are visible but not satisfied.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must account for DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression with bounded rows and real upstream/spec evidence.
   - Evidence: these areas are present as candidate/deferred rows or reuse paths, but none is active with dependency-specific denominator, malformed/corrupt coverage, bounded install-attempt evidence, or PHP pass/fail ledger. Current Pandoc status still keeps the rich formats behind future gates.

7. **High - base lanes keep crossing inactive support-library boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency expansion must be bounded, gated, tested, shared where appropriate, and not counted as reusable support progress before activation.
   - Evidence: esbuild resolver work is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work is ahead of inactive `webdav-protocol-core`, `xml-html5-dom-core`, and `archive-compression-streams`; Dolt/libsqlite JSON and SQL expression work are ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing route/protocol work remains ahead of inactive reusable protocol rows; Gitoxide URL/wire/hash/archive needs remain inactive rows.

8. **High - markerPDF still risks over-crediting PDF plumbing as structured-content extraction.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied model callbacks, benchmark archive probing, narrow stream handling, and dependency inspection cannot count as full extraction progress.
   - Evidence: live status is still narrow PDF stream/font/filter handling. Required support rows for PDF text dictionaries, page layout, OCR/layout result, tables, Unicode/charset, and archive/package handling remain inactive and have no dependency-specific pass/fail ledgers.

9. **Medium - Readability remains too broad for one reviewable acceptance.**
   - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, `lanes/readability/tests/ArticleExtractorTest.php`.
   - Goal requirement at risk: prefer small correct slices over broad shallow ports, with mapped upstream tests and reviewable commits.
   - Evidence: current status and worktree include a broad multi-fixture dirty pile across examples, fixtures, notes, source, and tests. Even with green focused Mozilla/PHP evidence, this needs integrator hunk splitting or an explicit preserved-work package before root acceptance.

10. **Medium - missing-package/full-suite evidence remains too weak for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: support rows do not yet record install attempts, spec-suite runner attempts, malformed/corrupt ledgers, or dependency-specific pass/fail counts.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The exact no-argument process gate was empty, but the checkout is not stable enough for a meaningful audit-owned aggregate run because all lane manifests/status files remain dirty or live handoff metadata and `porting.html` is generated from an older accepted snapshot while including pending lane text.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; isolate exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units before updating dashboard math; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
