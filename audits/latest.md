# Independent Audit - 2026-05-24T12:24Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`a28486e1 Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T12:21:56Z, 2026-05-24T12:23:20Z, 2026-05-24T12:23:59Z
HEAD: 99868de1c21d -> a28486e19f38
recent history: a28486e1 Record integration hold status; 99868de1 Refresh independent audit status; e54da734 Record integration hold status; c9814fa4 Record integration hold status; a86081f9 Refresh independent audit status
branch sample: main...origin/main [ahead 863, behind 68]
tracked dirty rows: 329 -> 329 -> 329
default status rows including untracked: 17724 -> 17727 -> 17727
git diff --shortstat: 329 files changed, 232053 insertions(+), 29043 deletions(-) -> 329 files changed, 232082 insertions(+), 28996 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 11:39:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T12:21:56Z:
2517373 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php

owner evidence sampled immediately after:
PID 2517373 USER claude PPID 2517275 STAT R+ ETIMES 33 COMMAND php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T12:23:20Z:
2528374 php tools/run-tests.php lanes/readability/tests

owner evidence sampled immediately after:
PID 2528374 USER claude PPID 2470380 STAT Rs ETIMES 10 COMMAND php tools/run-tests.php lanes/readability/tests

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T12:23:59Z:
no rows
```

I did not start `php tools/run-tests.php`. The exact gate was occupied by
focused lane harnesses during audit sampling and later cleared, but the
checkout was still not a frozen acceptance snapshot: `HEAD` advanced during the
run, untracked-inclusive status rows moved, and the integration status file
continues to record no accepted lane output from the current moving tree.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1064 / 838                     3245 / 0          735 / 374 / 374
dolt          613 / 613                      425 / 0           inventory / 613 / 356
esbuild       2567 / 428                     428 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7152 / 0          2877 / 2751 / 5634
libsqlite     1589 / 348                     348 / 0           1589 / 286 / 286
LightningCSS  3548 / 2765                    4053 / 0          3532 / 1732 / 2197
markerPDF     395 / 346                      483 / 0           330 / 280 / 416
pandoc        2276 / 1891                    361 / 0           2276 / 1061 / 278
quadrable     55 / 55                        232 / 0           55 / 55 / 190
rclone        1601 / 906                     906 / 0           1601 / 698 / 698
readability   1984 / 1984                    3527 / 0          1984 / 1984 / 204
syncthing     658 / 658                      7882 / 0          658 / 658 / 4579
```

Note: Dolt's old prose/string denominator blocker is cleared in the current
manifest; `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2520` is now numeric
`total: 613`. The dashboard still renders Dolt's denominator as `inventory`
because it is stale.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:47`, `progress.md:140`, `progress.md:142`,
     `progress.md:146`, `progress.md:157`,
     `audits/integration-status.md:16`, `audits/integration-status.md:20`,
     `audits/integration-status.md:27`, `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: during this audit `HEAD` moved from `99868de1c21d` to
     `a28486e19f38`, default status rows moved `17724 -> 17727`, and the dirty
     tree stayed at `329` tracked rows spanning every priority lane. Current
     lane status files still report `pending`, `uncommitted`, or dirty-batch
     prose for every lane's `latestCommit`.

2. **Critical - there is no audit-acceptable root PHP result for this
   snapshot.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`,
     `audits/integration-status.md:46`, `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with failures recorded honestly.
   - Evidence: the required process gate matched focused Syncthing PID
     `2517373` and focused Readability PID `2528374` during audit sampling.
     The gate later cleared, but no root run was started because the checkout
     moved and no lane batch had been accepted. Lane blockers still assign
     aggregate root verification to the supervisor/integrator, so focused
     green lane runs are not current root evidence.

3. **Critical - `porting.html` and `porting-summary.json` are stale and still
   materially overstate status.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:75`, `porting-summary.json:1`,
     `audits/integration-status.md:55`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, and 22 dependency rows while current `HEAD` is
     `a28486e1` and `dependency-backlog.json` has 37 rows.

4. **High - manifest, lane-status, and dashboard counts contradict each other
   across every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:9`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:44` and `goal.md:45` require
     comparable denominator, mapped-test, PHP pass/fail, blocker, current-work,
     and commit fields.
   - Evidence: Difftastic is manifest `1064/838`, status `3245/0`,
     dashboard `735/374/374`; Gitoxide is manifest `2877/2877`, status
     `7152/0`, dashboard `2877/2751/5634`; markerPDF is manifest `395/346`,
     status `483/0`, dashboard `330/280/416`; rclone is manifest `1601/906`,
     status `906/0`, dashboard `1601/698/698`; Syncthing is manifest
     `658/658`, status `7882/0`, dashboard `658/658/4579`.

5. **High - support-library coverage is still backlog-only, with zero accepted
   bounded support ports.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:7`,
     `dependency-backlog.json:81`, `dependency-backlog.json:97`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:306`,
     `dependency-backlog.json:413`, `dependency-backlog.json:532`,
     `porting.html:75`.
   - Goal requirement at risk: the latest 2026-05-24 11:59 UTC
     support-library directive requires bounded native PHP components,
     activation gates, dependency-specific upstream/spec denominators, mapped
     fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant,
     and as much upstream/spec-suite evidence as can run.
   - Evidence: the 37-row backlog covers the important Pandoc document/PDF
     dependency areas and shared Git, Quadrable, WebDAV, URL, source-map,
     protobuf, checksum/hash, SQL, archive/compression, target-data, package
     resolution, and tree-sitter rows. However, all rows are `candidate`,
     `deferred`, or `blocked`; there are `0` active support ports, and the repo
     scan found no dependency-specific support-library manifests beyond the 12
     lane manifests. Lane-local dependency-adjacent work must not count as
     shared support progress.

6. **High - markerPDF still mixes native PDF evidence with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:259`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:572`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:573`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1072`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1082`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1093`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid wrappers
     around JS/Rust/Go/C binaries or shell-outs from counting as the main
     deliverable; bridge/oracle tooling may only be temporary.
   - Evidence: markerPDF has useful native PDF parsing slices, but its mapped
     status still includes benchmark runner CLI/file planning, Pandoc/XeLaTeX
     proof-PDF shell plans, chunk-convert shell lifecycle planning,
     OCRMyPDF/Tesseract/Ghostscript readiness/install plans, Streamlit/FastAPI
     server/app planning, Poetry/model-runtime dependency graph planning, and
     model loader/Texify/Nougat orchestration. These are blockers,
     caller-supplied boundaries, or oracle metadata, not accepted native port
     progress.

7. **Medium - `progress.md` is stale against current lane handoffs and now
   carries a resolved blocker as current text.**
   - Paths: `progress.md:47`, `progress.md:146` through `progress.md:157`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2520`,
     `lanes/*/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, blockers, and percentage estimates in `progress.md`.
   - Evidence: `progress.md:47` still says Dolt has a string denominator even
     though the current Dolt manifest is numeric `613/613`; the Active Lanes
     table still lists older handoffs such as Gitoxide SSH config options,
     LightningCSS trig/math, markerPDF benchmark file inventory, and
     Syncthing system log routing while lane-status files now describe later
     gix-index split rewrite, CSS Modules nested exports, PDF numeric
     destination metadata, GUI static asset validators, and other newer work.

8. **Medium - near-complete percentages continue to overstate accepted
   upstream and root parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
     tests are not enough, upstream tests are the source of truth where
     possible, and hard gaps must be recorded as blockers or future slices.
   - Evidence: most lane statuses and the stale dashboard show `98%` to `99%`
     progress while root aggregate verification remains pending, full upstream
     runners remain unrun or bounded for multiple lanes, and every current
     handoff remains pending/uncommitted in the shared dirty tree.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, capacity executors, Dolt, and the Dolt runner.
Require two stable polls of `HEAD`, tracked rows, untracked-inclusive rows,
shortstat, exact process gates, dependency/dashboard counts, and relevant log
mtimes. Then accept exactly one coherent lane batch with manifest/status schema
normalization, run focused lane verification plus `git diff --check`, activate
only support-library rows whose base-lane gate is accepted or truly blocked,
add dependency-specific support manifests before counting support progress,
regenerate `porting.html` and `porting-summary.json` from the accepted commit,
and run one serialized no-argument `php tools/run-tests.php` only if the exact
process gate remains empty on that frozen snapshot.
