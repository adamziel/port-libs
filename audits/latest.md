# Independent Audit - 2026-05-24T06:58Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and the required PHP harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T06:56:59Z and 2026-05-24T06:57:51Z
HEAD: ec658ab1030f
recent commits: ec658ab1 Record integration hold status; 64b188a9 Refresh independent audit status; 9dfbecf7 Record integration hold status; 11b576e2 Record integration hold status; 53a08b3d Refresh independent audit status
branch divergence: main...origin/main [ahead 745, behind 68]
default status rows including untracked: 14905 -> 14962
git diff --shortstat: 315 files changed, 189035 insertions(+), 28951 deletions(-) -> 315 files changed, 189096 insertions(+), 28951 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:56:59Z: no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:57:51Z:
2693094 php tools/run-tests.php

ps -o pid,user,ppid,etime,stat,command -p 2693094:
2693094 claude 2693051 00:57 R+ php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The first gate was clear, but the
tree was not stable: status rows changed by 57 and shortstat gained 61
insertions within the audit window. The second required gate then matched an
active no-argument root harness owned by `claude`, so a duplicate root run would
violate the audit instructions and would not produce acceptance evidence for a
frozen snapshot.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37` through `progress.md:40`, recent Git history,
     current `git status` samples.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verification before integration, and
     honest repo-wide checks.
   - Evidence: current `HEAD` is `ec658ab1030f` and the branch is
     `main...origin/main [ahead 745, behind 68]`. During this audit, status
     rows changed `14905 -> 14962` and shortstat changed from `315 files
     changed, 189035 insertions(+), 28951 deletions(-)` to `315 files
     changed, 189096 insertions(+), 28951 deletions(-)`. Every lane status
     still reports `pending`, `uncommitted`, or shared-dirty handoff ownership.

2. **Critical - no no-argument root-harness result can be accepted for this
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`, `audits/latest.md`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the first exact process gate had no rows, but the checkout was
     moving. The second exact gate matched active no-argument root PID
     `2693094` owned by `claude` (`php tools/run-tests.php`, parent `2693051`,
     state `R+`). This audit correctly did not start a duplicate root run.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`, `porting.html:75`,
     `porting-summary.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `ec658ab1030f`. The dashboard
     dependency section still shows 22 items, but `dependency-backlog.json` has
     23.

4. **High - dashboard, manifest, and lane-status counts disagree across the
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable denominators, mapped counts, PHP pass/fail counts,
     blockers, and current status.
   - Evidence: current checkout data versus dashboard includes Difftastic
     manifest/status `875 total / 566 mapped / 2876 pass` vs dashboard
     `735 / 374 / 374`; Dolt `399 pass` vs `356`; esbuild `372` vs `311`;
     Gitoxide `2877 mapped / 6443 pass` vs `2751 / 5634`; libsqlite
     `323 mapped in manifest / 322 pass in status` vs `286 / 286`;
     LightningCSS `2461 mapped / 3456 pass` vs `1732 / 2197`; markerPDF
     `363 / 314 / 452` vs `330 / 280 / 416`; Pandoc `1598 mapped / 326 pass`
     vs `1061 / 278`; Quadrable `213 pass` vs `190`; rclone `834` vs `698`;
     Readability `236 pass` vs `204`; and Syncthing `6700 pass` vs `4579`.

5. **High - manifest denominator schema remains non-normalized, and Dolt's
   canonical denominator field is overwritten by slice prose.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2458`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators and comparable dashboard
     fields.
   - Evidence: Difftastic and Pandoc still store prose strings in
     `benchmarkDenominator.total`. Dolt stores latest FIND_IN_SET slice
     narrative in `benchmarkDenominator.total`, while the lane status now says
     focused Dolt PHP passes `399` cases. The primary denominator field is not
     machine-safe.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:33` through `goal.md:40` require real
     denominator parity, fixture parity, edge/error behavior, and honest
     blockers.
   - Evidence: most lane statuses report `98-99%`, but the same status files
     still say root aggregate verification is pending, full upstream runners
     are unexecuted or bounded, and current work remains pending/uncommitted in
     the shared dirty tree. Passing focused lane tests are useful evidence, not
     accepted completion.

7. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json`, `porting.html:75`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` has 23 items, 13 candidate and 10
     deferred, with no active support-library port. Rich-function gaps remain
     for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB, EPUB, ODT,
     doctemplates, citations/CSL, math/TeX, PDF text, PDF render planning,
     OCR/layout, table geometry, Unicode, charset, source maps, protobuf,
     checksum/hash, storage codecs, archive streams, glob/pathspec, and
     provider metadata.

8. **High - rclone dependency expansion is lane-local and should not be
   credited as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8` through
     `lanes/rclone/lane-status.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone carries lane-local WebDAV XML/PROPFIND/PROPPATCH/LOCK,
     gzip, middleware, auth-proxy, directory templates, URL decoding, provider
     metadata, VFS, response, and reader helpers. These can be valid rclone
     slices, but they cannot count as support-library progress without a
     dependency-specific denominator, malformed/corrupt cases, activation gate,
     and reusable ownership.

9. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:473`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:502` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:504`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:616` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:617`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, or shell-outs to upstream binaries as native
     implementation progress.
   - Evidence: markerPDF has real native PDF stream/filter/text work, but the
     manifest still foregrounds pdftext/pypdfium/Surya model stacks,
     Tesseract/Ghostscript/Pandoc/XeLaTeX/Poetry/Streamlit/FastAPI/Uvicorn
     plans, chunk-convert shell lifecycle plans, server startup/lifespan model
     slots, and helper-script plans. Those are only preflight/oracle evidence
     unless bounded native components own the behavior directly.

10. **Medium - Readability's full-fixture claim still hides known parity gaps.**
    - Paths: `lanes/readability/lane-status.json:5`,
      `lanes/readability/lane-status.json:10`,
      `lanes/readability/lane-status.json:12`,
      `lanes/readability/UPSTREAM_TEST_MANIFEST.json:930`,
      `porting.html:66`.
    - Requirement at risk: `goal.md:35` and `goal.md:37` require meaningful
      fixture parity and upstream tests as source of truth.
    - Evidence: current lane status records three remaining copied-fixture
      normalized text mismatches: `firefox-nightly-blog`, `wikipedia-2`, and
      `wikipedia-3`. The dashboard still presents `1984 / 1984` mapped and a
      `99.0%` row without surfacing that parity gap.

11. **Medium - test-time shell-outs must remain oracle tooling, not native
    progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php`,
      `lanes/gitoxide/tests/GitUrlTest.php`,
      `tools/generate-dashboard.php`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: Gitoxide tests invoke `/usr/bin/git` through process helpers,
      and the dashboard generator uses Git shell metadata. That can be
      acceptable as explicit fixture/oracle or coordination tooling only; it
      must not become implementation progress.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, status row counts, shortstat, exact PHP runner state, capacity/disk
state, dashboard publication state, and relevant log mtimes; accept or reject
one lane-scoped batch; normalize schema/count fields for that batch; run
focused verification plus `git diff --check`; run exactly one serialized
no-argument `php tools/run-tests.php` from that same frozen snapshot if the
exact process gate remains empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
