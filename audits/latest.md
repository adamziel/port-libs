# Independent Audit - 2026-05-24T15:44Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `3a76b4ab Refresh independent audit status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 15:42:03, 15:43:35, 15:44:12
observed HEAD: 3a76b4ab45b0
recent history: 3a76b4ab Refresh independent audit status; 06a6a69e Refresh independent audit status; c959e048 Refresh independent audit status; 508e35d0 Refresh independent audit status
tracked dirty files: 330
default status rows including untracked: 19383 -> 19383 -> 19385
git diff --shortstat: 330 files changed, 258011 insertions(+), 32310 deletions(-) -> 330 files changed, 258174 insertions(+), 32309 deletions(-) -> 330 files changed, 258229 insertions(+), 32307 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
15:42Z pgrep -af '^php tools/run-tests\.php$': no rows
15:43Z pgrep -af '^php tools/run-tests\.php$': no rows
15:44Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument gate was
empty in my samples, but the checkout kept moving and Dolt currently records
focused PHP failures. A root result from this source would not be an
attributable acceptance checkpoint.

Current manifest/status sample versus the published dashboard:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 990/1203, status 3480 pass     3245 pass, 851/1077
dolt          status 432 pass / 2 fail, manifest php 430 425 pass / 0 fail
esbuild       manifest 453/2567, status 452 pass      429 pass, 429/2567
gitoxide      status 7363 pass, 2877/2877 mapped      7152 pass, 2877/2877
libsqlite     status 366 pass, manifest 365/1589      348 pass, 349/1589
LightningCSS  status 4188 pass, manifest 2870/3548    4065 pass, 2765/3548
markerPDF     503 pass, 366/415 mapped                484 pass, 347/396
pandoc        status 380 pass, manifest 2065/2276     362 pass, 1891/2276
quadrable     243 pass, 55/55 mapped                  232 pass, 55/55
rclone        953 pass, 953/1601 mapped               906 pass, 906/1601
readability   3707 assertions, 276 behavior entries   3545 pass, 1984/1984
syncthing     8384 pass, 658/658 mapped               7902 pass, 658/658
```

## Findings

1. **Critical - Dolt has a real focused PHP regression and must not be accepted.**
   - Paths: `lanes/dolt/lane-status.json:5-13`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2570-2577`,
     `porting.html:57`, `progress.md:50`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48-49`, and
     `goal.md:52` require small reviewable commits with passing tests,
     verified handoffs, and a baseline where every lane has passing PHP tests.
   - Evidence: Dolt status now records `phpPass: 432` and `phpFail: 2`;
     the blocker names the failing tests as `dolt query-diff command applies
     upstream char select order where and literal expressions` and
     `wordpress query-diff char fixture renders import marker review`.
     The manifest still says `phpBehaviorTests: 430`, and the dashboard still
     hides the failure as `425 pass / 0 fail`. This is the first intervention:
     fix, revert, or explicitly reject the Dolt runner metadata/update before
     root acceptance.

2. **Critical - the repo remains a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:50`, `lanes/*/lane-status.json`,
     `porting.html:35-38`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:44`, and
     `goal.md:48-49` require reviewable commits, current coordination, and
     verified integration.
   - Evidence: tracked dirty files stayed at `330`, default status rows moved
     `19383 -> 19385`, and shortstat moved from `258011` to `258229`
     insertions during this audit. All sampled lane statuses still report
     `pending`, `uncommitted`, or supervisor/integrator-owned commits. Recent
     history is audit/status churn, not an accepted lane batch.

3. **High - there is still no valid root acceptance checkpoint.**
   - Paths: `tools/run-tests.php`, `lanes/dolt/lane-status.json:12`,
     `progress.md:50`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the user also forbids duplicate root
     harnesses.
   - Evidence: the exact gate `pgrep -af '^php tools/run-tests\.php$'` returned
     no rows in my samples, but root was not run because the tree moved during
     review and Dolt's focused PHP evidence is already red. Running root now
     would test a non-frozen source with a known lane failure.

4. **High - `porting.html` and `porting-summary.json` are stale against every lane and now hide a red lane.**
   - Paths: `porting.html:32-67`, `porting-summary.json`,
     `lanes/dolt/lane-status.json:5-13`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and
     commit in the dashboard.
   - Evidence: the dashboard still publishes `main 89260857cc71` generated
     `2026-05-24 12:29:46 UTC`, while `HEAD` is `3a76b4ab45b0`. It reports
     Dolt `425 pass / 0 fail` while lane status reports `432 pass / 2 fail`;
     markerPDF `484` pass while status reports `503`; LightningCSS `4065`
     pass while status reports `4188`; Syncthing `7902` pass while status
     reports `8384`; Readability `3545` pass while status reports `3707`
     assertions.

5. **High - manifest/status ledgers are not normalized enough for audit-grade progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2577`,
     `lanes/dolt/lane-status.json:5-7`,
     `lanes/pandoc/lane-status.json:5-12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:339-341`,
     `lanes/readability/lane-status.json:5-7`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:1056-1062`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:24-25`, and
     `goal.md:44-45` require machine-checkable denominators, mapped tests, PHP
     passing/failing counts, blockers, and latest commit.
   - Evidence: Dolt status is `432/2` but the manifest still records
     `phpBehaviorTests: 430`. Pandoc status says `2,072` focused checks while
     its manifest mapped count is `2,065`. Readability status uses `phpPass:
     3707` for assertions while the manifest separates `phpBehaviorTests: 276`
     and `phpAssertions: 3707`. These schemas make percent and pass/fail claims
     hard to compare across lanes.

6. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:1-4`, `dependency-backlog.json:7-22`,
     `dependency-backlog.json:81-94`, `dependency-backlog.json:129-142`,
     `dependency-backlog.json:145-176`, `dependency-backlog.json:179-192`,
     `dependency-backlog.json:214-230`, `dependency-backlog.json:233-268`,
     `dependency-backlog.json:272-286`, `dependency-backlog.json:322-337`,
     `dependency-backlog.json:340-362`, `dependency-backlog.json:391-410`,
     `dependency-backlog.json:629-642`, `porting.html:72-78`.
   - Goal requirement at risk: the latest support-library directives require a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, bounded install attempts where
     relevant, and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. Pandoc's
     DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     templates, citations, math, tables, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression categories are visible as gated rows, but none has a
     support-library manifest, PHP ledger, malformed/corrupt evidence,
     accepted activation record, or bounded install-attempt/ruled-out note.

7. **High - Pandoc's original conversion-kernel goal remains unproven despite 99% status.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:339-343`,
     `lanes/pandoc/tests/MarkdownReaderTest.php:2157`,
     `dependency-backlog.json:81-94`, `dependency-backlog.json:129-176`,
     `dependency-backlog.json:214-268`, `dependency-backlog.json:413-420`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel with
     shared AST plus readers/writers for Markdown, HTML, WXR, EPUB/PDF-oriented
     intermediate forms, and WordPress block-oriented output.
   - Evidence: current Pandoc work is an HTML writer `removeLinks` slice; full
     upstream Haskell runner parity is unexecuted; TeX RawInline math/ref,
     PlainMath, and MathML are explicitly unclaimed; package/PDF support rows
     remain inactive. `rg -n 'WXR|Wxr|wxr' lanes/pandoc` finds only test prose
     in `MarkdownReaderTest.php:2157`, not a visible WXR reader/writer
     capability.

8. **High - rich dependency-adjacent slices are lane-local and cannot count as support-library progress.**
   - Paths: `lanes/markerpdf/lane-status.json:10-14`,
     `lanes/rclone/lane-status.json:10-14`,
     `lanes/pandoc/lane-status.json:10-14`,
     `dependency-backlog.json:272-286`, `dependency-backlog.json:322-337`,
     `dependency-backlog.json:50-56`.
   - Goal requirement at risk: support-library expansion must be bounded,
     gated, tested, reusable across lanes, and not an external converter,
     whole app, or hidden shell-out.
   - Evidence: markerPDF continues adding PDF text-extraction behavior while
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core` remain inactive. Rclone continues local WebDAV
     mutation behavior while `webdav-protocol-core` remains candidate-only.
     Pandoc's lane status explicitly says the latest slice does not activate
     ZIP/OpenXML/OpenDocument, PDF, CSL, math, XML/HTML DOM, Unicode/charset,
     or syntax-highlighting support rows.

9. **Medium - near-complete percentages overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` says passing tests are not
     enough and hard features must not be silently skipped.
   - Evidence: the dashboard reports `98.3%` average and most lanes at
     `98-99%`, while every lane remains unaccepted in a dirty moving worktree,
     root verification is pending/non-attributable, Dolt is currently red,
     several full upstream runners are static/bounded/unexecuted, and zero
     support-library rows are active.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. First fix,
revert, or reject the Dolt `QueryDiffCommand` CHAR regression and normalize its
manifest/status counts. Then require two stable polls of `HEAD`, tracked/default
status counts, shortstat, exact root gate `pgrep -af '^php tools/run-tests\.php$'`,
dashboard/dependency counts, lane status timestamps, and relevant log mtimes.
Accept exactly one owner-free lane batch at a time, with schema/count/commit
normalization, focused verification plus `git diff --check`, support-library
manifests only behind accepted gates, dashboard regeneration from the accepted
commit, and one serialized no-argument root result only on that frozen snapshot.
