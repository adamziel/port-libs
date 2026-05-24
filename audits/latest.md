# Independent Audit - 2026-05-24T06:33Z

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
UTC samples: 2026-05-24T06:31:26Z through 2026-05-24T06:36:21Z
HEAD movement during audit: 5228b7e232f0 -> e9b7bf58beff
recent commits: e9b7bf58 Record integration hold status; 5228b7e2 Refresh independent audit status; 8cd70f0c Record integration hold status
branch divergence: main...origin/main [ahead 737, behind 68] -> [ahead 738, behind 68]
tracked dirty rows: 314 -> 315 -> 314 -> 316
default status rows including untracked: 14493 -> 14550 -> 14616
git diff --shortstat: 314 files changed, 185169 insertions(+), 27825 deletions(-) -> 316 files changed, 186268 insertions(+), 28529 deletions(-)
manifest JSON validation: jq empty passed for all 12 root lane manifests
status JSON validation: jq empty passed for all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items, grouped as candidate 13 / deferred 10 and critical 4 / high 8 / medium 11; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at the initial audit gate:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:32Z:
2216163 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
2216393 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
2216396 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests

ps -o pid,user,ppid,etime,stat,command -p 2216163,2216393,2216396:
processes had already exited before ps could sample owner/elapsed state

later pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:32:55Z:
no rows

final pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:36:21Z:
2301252 php tools/run-tests.php
2302312 php tools/run-tests.php lanes/syncthing/tests/PullFinisherTest.php ... lanes/syncthing/tests/ServiceMapTest.php
2302913 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
2302954 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
2303014 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests
2304585 php tools/run-tests.php lanes/quadrable/tests

ps -o pid,user,ppid,etime,stat,command -p 2301252,2302312,2302913,2302954,2303014,2304585:
2301252 claude 2301111 00:22 R+ php tools/run-tests.php
2302312 claude 2302185 00:19 R+ php tools/run-tests.php lanes/syncthing/tests/PullFinisherTest.php ... lanes/syncthing/tests/ServiceMapTest.php
2302954 claude 2302554 00:18 R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The exact gate was transiently
occupied by focused root-harness shards, then cleared after the source snapshot
had already moved, and the final gate matched an active no-argument root
harness owned by `claude`. The checkout was not stable enough for an
audit-owned no-argument run: `HEAD`, tracked rows, total status rows, and
shortstat changed during the audit window.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`, current Git status and recent
     Git history.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verification before integration, and
     honest repo-wide checks.
   - Evidence: `HEAD` moved from `5228b7e232f0` to `e9b7bf58beff` during this
     audit. The dirty tree still spans 316 tracked rows and about 14,616
     status rows including untracked files. Shortstat moved from `314 files
     changed, 185169 insertions(+), 27825 deletions(-)` to `316 files
     changed, 186268 insertions(+), 28529 deletions(-)`. This is not a frozen
     source snapshot that can be accepted.

2. **Critical - no coherent root-harness result can be accepted for the current
   source snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required process gate briefly matched focused harness shards
     for markerPDF/Pandoc/Readability, rclone/Syncthing, and
     libsqlite/LightningCSS/Quadrable/Difftastic/esbuild. Those PIDs exited
     before owner sampling, and a later gate cleared only after `HEAD` and the
     dirty tree had moved. Starting a no-argument root run from this moving
     aggregate would produce diagnostic noise, not acceptance evidence. The
     final gate also matched active no-argument root PID `2301252` owned by
     `claude`, so this audit correctly started no duplicate.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with per-lane benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `e9b7bf58beff`. It also publishes
     the dependency backlog as 22 items, while `dependency-backlog.json` has 23.

4. **High - manifest denominator schema is still not mechanically reliable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2456`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is still prose in four manifests.
     Dolt's field is not a denominator at all; it contains the latest
     FIND_IN_SET slice narrative. Numeric `totalCount`/`mapped` fields exist in
     some manifests, but the canonical `total` field remains unsafe for durable
     percentage/count generation.

5. **High - dashboard, manifest, and lane-status counts disagree across most
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:9`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable upstream denominators, mapped test counts, PHP pass/fail
     counts, blockers, and current status.
   - Evidence: current manifests/statuses versus dashboard include Difftastic
     `875 total / 540 mapped / 2845 PHP pass` vs dashboard `735 / 374 / 374`;
     Dolt `613 mapped / 396 pass` vs `613 / 356`; esbuild `368 / 368` vs
     `311 / 311`; Gitoxide `2877 / 6398` vs `2751 / 5634`; libsqlite
     `320` vs `286`; LightningCSS `2399 / 3394` vs `1732 / 2197`; markerPDF
     `361 / 312 / 449` vs `330 / 280 / 416`; Pandoc `1555 / 323` vs
     `1061 / 278`; Quadrable `210 pass` vs `190`; rclone `825` vs `698`;
     Readability `235` vs `204`; and Syncthing `6636 assertions` vs `4579`.

6. **High - near-complete percentages still overstate accepted upstream
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`, runner/status fields in
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:33` through `goal.md:40` require real
     denominator parity, fixture parity, edge/error behavior, and honest
     blockers.
   - Evidence: many lanes still display `98-99%` while full Cargo/Go/BATS,
     Haskell, release-extra, live-provider, model/runtime, or full upstream
     parity is explicitly unexecuted or only bounded. No no-argument PHP root
     result has been accepted from a frozen snapshot.

7. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:468`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:469`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:750`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:756`,
     `lanes/markerpdf/lane-status.json`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, or shell-outs to upstream binaries as native
     implementation progress.
   - Evidence: markerPDF has real native PDF decoding/extraction work, but the
     manifest still foregrounds plan-only Pandoc/XeLaTeX helper workflows,
     shell lifecycle planning, Streamlit/FastAPI/Uvicorn runtime planning,
     OCR/Tesseract/Ghostscript install planning, Poetry/package metadata, and
     model-runtime graphs. Those may be preflight or oracle evidence, but they
     are not native port progress.

8. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:4`, `dependency-backlog.json:5`,
     `dependency-backlog.json:111`, `porting.html:75`,
     `porting-summary.json:299`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: there are 23 candidate/deferred backlog items and zero active
     support-library ports. `pandoc-doctemplates-core` exists in the backlog
     but is absent from the dashboard's 22-row dependency view. ZIP, OpenXML,
     legacy DOC/CFB, ODT, EPUB, doctemplates, PDF text/OCR/layout, XML/HTML5,
     WebDAV, Unicode, charset, source maps, protobuf, hashes, SQL/storage
     codecs, archive streams, glob/pathspec, and provider metadata remain
     backlog rows, not accepted components.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/lane-status.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1233`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1325`,
     `dependency-backlog.json:4`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone now carries lane-local WebDAV, gzip, auth-proxy,
     directory-template, OneDrive, provider metadata, VFS, response, and
     reader helpers. These may be valid rclone slices, but they cannot count as
     shared support-library progress without dependency-specific denominators,
     malformed/corrupt cases, activation gates, and reusable ownership.

10. **Medium - test-time shell-outs must remain oracle tooling, not native
    progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:16`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:69`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:102`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `tools/generate-dashboard.php:197`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: Gitoxide tests invoke `/usr/bin/git` through `proc_open`, and
      the dashboard generator uses `shell_exec` for Git metadata. That can be
      acceptable as explicit fixture/oracle or coordination tooling only; it
      must not become implementation progress.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop active
writers/status publishers and PHP shards; take two stable polls of `HEAD`,
tracked status count, total status count, shortstat, exact PHP runner state,
capacity/disk state, and relevant log mtimes; accept or reject one lane-scoped
batch; normalize schema/count fields for that batch; run focused verification
plus `git diff --check`; run exactly one serialized no-argument
`php tools/run-tests.php` from that same frozen snapshot if the exact process
gate remains empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
