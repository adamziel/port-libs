# Independent Audit - 2026-05-24T07:11Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and the required PHP harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, shell-outs, whole applications,
external converter wrappers, and hidden process launchers are treated as
non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T07:08:xxZ through 2026-05-24T07:10:47Z
HEAD moved during audit: caa795d044e5 -> 8ac9abeaf53b
recent commits: 8ac9abea Record integration hold status; caa795d0 Refresh independent audit status; 0f492b27 Record integration hold status; a0e3544e Record integration hold status; a019abce Refresh independent audit status
branch divergence: main...origin/main [ahead 751, behind 68]
default status rows including untracked: 15097 -> 15155
git diff --shortstat: 315 files changed, 190523 insertions(+), 28690 deletions(-) -> 315 files changed, 190558 insertions(+), 28690 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
2026-05-24T07:08Z pgrep -af '^php tools/run-tests\.php( |$)':
2880340 php tools/run-tests.php lanes/syncthing/tests
owner evidence: PID 2880340 claude, started Sun May 24 07:07:50 2026

2026-05-24T07:10:47Z pgrep -af '^php tools/run-tests\.php( |$)':
2923142 php tools/run-tests.php
2925869 php tools/run-tests.php lanes/syncthing/tests/PullDbUpdaterTest.php ... lanes/syncthing/tests/ServiceLanguageTest.php
owner evidence: PID 2923142 claude, started Sun May 24 07:10:00 2026; PID 2925869 claude, started Sun May 24 07:10:06 2026
```

I did not start `php tools/run-tests.php`. The exact process gate matched an
active focused harness first, then an active no-argument root harness. The
checkout also moved during the audit, so a fresh audit-owned root run would not
produce acceptance evidence for a frozen snapshot.

## Findings

1. **Critical - the checkout is not an acceptance checkpoint.**
   - Paths: `progress.md:37` through `progress.md:40`,
     `lanes/*/lane-status.json`, recent Git history.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small committed slices, verification before integration, and
     honest repo-wide checks.
   - Evidence: `HEAD` moved during this audit from `caa795d044e5` to
     `8ac9abeaf53b`; branch divergence is now `[ahead 751, behind 68]`;
     status rows moved `15097 -> 15155`; shortstat changed while sampling.
     Every current lane status still reports pending or uncommitted handoff
     ownership rather than an accepted committed batch.

2. **Critical - no audit-owned root-harness result can be accepted for this
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required process gate found active PHP harnesses, including
     no-argument root PID `2923142` owned by `claude`. Earlier in the same run
     it found focused Syncthing PID `2880340`. Starting another root run would
     violate the duplicate-run guard and would race a moving worktree.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with denominator, mapped tests, PHP pass/fail, scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current observed `HEAD` is `8ac9abeaf53b`.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:200`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable denominators, mapped counts, PHP pass/fail counts,
     blockers, and current status.
   - Evidence: current manifest/status values versus dashboard include
     Difftastic `883 total / 589 mapped / 2908 pass` vs dashboard
     `735 / 374 / 374`; Dolt `613 mapped / 400 pass` vs `613 / 356`;
     esbuild `2567 / 376 / 376` vs `2567 / 311 / 311`; Gitoxide
     `2877 / 2877 / 6454` vs `2877 / 2751 / 5634`; libsqlite
     `1589 / 324 mapped / 323 pass` vs `1589 / 286 / 286`; LightningCSS
     `3532 / 2505 / 3502` vs `3532 / 1732 / 2197`; markerPDF
     `364 / 315 / 452` vs `330 / 280 / 416`; Pandoc
     `2276 prose total / 1604 / 327` vs `2276 / 1061 / 278`;
     Quadrable `55 / 55 / 214` vs `55 / 55 / 190`; rclone
     `1601 / 834 / 834` vs `1601 / 698 / 698`; Readability
     `1984 / 1984 / 236` vs `1984 / 1984 / 204`; and Syncthing
     `658 / 658 / 6860` vs `658 / 658 / 4579`.

5. **High - manifest denominator schema remains non-normalized.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:29`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2461`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`.
   - Requirement at risk: `goal.md:25`, `goal.md:38`, and `goal.md:45`
     require real upstream denominators and comparable dashboard fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is still stale
     06:05 FIND_IN_SET prose while `latestSlice` and lane status report the
     newer 06:58 MAKE_SET slice. Pandoc still stores prose in
     `benchmarkDenominator.total` and a separate numeric `totalCount`, forcing
     consumers to special-case the schema.

6. **High - `progress.md` active-lane handoff labels are stale relative to
   current lane statuses.**
   - Paths: `progress.md:97` through `progress.md:110`,
     `lanes/difftastic/lane-status.json:5` through
     `lanes/difftastic/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:5` through
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:13`.
   - Requirement at risk: `goal.md:44` requires `progress.md` to include
     current lane owners, next tasks, blockers, and percentage estimates.
   - Evidence: `progress.md` still names older handoffs such as Gitoxide SSH
     config-options, LightningCSS trig/math, markerPDF benchmark
     file-inventory, and Syncthing system-log work. Current lane statuses now
     report Gitoxide split-index link writing, LightningCSS color-mix target
     fallback, markerPDF ToUnicode CMap decoding, and Syncthing debug-file
     route work.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `lanes/*/lane-status.json`, `porting.html:56` through
     `porting.html:67`.
   - Requirement at risk: `goal.md:35` through `goal.md:40` require real
     denominator parity, fixture parity, edge/error behavior, and honest
     blockers.
   - Evidence: most lane statuses report `98-99%`, but the same files state
     latest commits are pending/uncommitted, root aggregate verification is
     pending, and full upstream runners remain unexecuted, bounded, or
     static-only for major lanes. Focused lane tests are useful evidence; they
     are not accepted completion.

8. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:124`,
     `dependency-backlog.json:169` through `dependency-backlog.json:216`,
     `dependency-backlog.json:382` through `dependency-backlog.json:440`,
     `porting.html:72` through `porting.html:78`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` has 23 items, 13 candidate and 10
     deferred, with no active support-library port. The dashboard still shows
     22 dependency rows and omits `pandoc-doctemplates-core`. Rich-function
     gaps remain for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB,
     EPUB, ODT, doctemplates, citations/CSL, math/TeX, PDF text, PDF render
     planning, OCR/layout, table geometry, Unicode, charset, source maps,
     protobuf, checksum/hash, storage codecs, archive streams, glob/pathspec,
     and provider metadata.

9. **High - rclone dependency expansion is lane-local and too broad to count
   as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8` through
     `lanes/rclone/lane-status.json:12`,
     `dependency-backlog.json:25` through `dependency-backlog.json:42`,
     `dependency-backlog.json:382` through `dependency-backlog.json:440`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone carries lane-local WebDAV XML/PROPFIND/PROPPATCH/LOCK,
     gzip, middleware, auth-proxy, directory template, URL decoding, provider
     metadata, VFS, response, and reader helper work. These may be valid
     rclone slices, but they are not a shared support-library port without a
     dependency-specific denominator, malformed/corrupt cases, activation
     gate, and reusable ownership.

10. **High - markerPDF still mixes native progress with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/lane-status.json:5`,
      `lanes/markerpdf/lane-status.json:9` through
      `lanes/markerpdf/lane-status.json:12`,
      `dependency-backlog.json:169` through `dependency-backlog.json:216`.
    - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
      wrappers, bridge calls, or shell-outs to upstream binaries as native
      implementation progress.
    - Evidence: markerPDF has real native PDF text/filter/CMap work, but the
      phase/blocker text still mixes in benchmark CLI plans, marker_server /
      marker_app runtime plans, Tesseract/Ghostscript/Pandoc/XeLaTeX/Poetry/
      Streamlit/FastAPI/Uvicorn/Torch/Surya/Texify/Nougat boundaries, and
      `chunk_convert.sh` / `convert.py` shell lifecycle plans. Those are
      preflight or oracle evidence unless a bounded native component owns the
      behavior directly.

11. **Medium - Readability's full-fixture mapping claim still hides known
    parity gaps.**
    - Paths: `lanes/readability/lane-status.json:5`,
      `lanes/readability/lane-status.json:10` through
      `lanes/readability/lane-status.json:12`, `porting.html:66`.
    - Requirement at risk: `goal.md:35` and `goal.md:37` require meaningful
      fixture parity and upstream tests as the source of truth.
    - Evidence: current lane status reports two remaining copied-fixture
      normalized full-text mismatches: `firefox-nightly-blog` and
      `wikipedia-2`. The dashboard still presents `1984 / 1984` mapped and
      `99.0%` without surfacing the remaining parity gap.

12. **Medium - test-time shell-outs must remain explicit oracle tooling, not
    native progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:15` through
      `lanes/gitoxide/tests/FetchResponseTest.php:29`,
      `lanes/gitoxide/tests/GitUrlTest.php:68` through
      `lanes/gitoxide/tests/GitUrlTest.php:76`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `tools/generate-dashboard.php:194` through
      `tools/generate-dashboard.php:204`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: Gitoxide tests invoke Git through `proc_open`, and the
      dashboard generator shells out for Git metadata. That is acceptable only
      as explicit fixture/oracle or coordination tooling; it must not be
      credited as implementation progress.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, status row counts, shortstat, exact PHP runner state, capacity/disk
state, dashboard publication state, and relevant log mtimes; accept or reject
one lane-scoped batch; normalize schema/count fields for that batch; run
focused verification plus `git diff --check`; run exactly one serialized
no-argument `php tools/run-tests.php` from that same frozen snapshot only if the
exact process gate is empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
