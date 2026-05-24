# Independent Audit - 2026-05-24T12:18Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`, latest support
dependency scout/review artifacts, `audits/integration-status.md`, and recent
Git history through `e54da734 Record integration hold status`. I did not edit
lane implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T12:12:34Z, 2026-05-24T12:13:25Z, 2026-05-24T12:18:01Z
HEAD: a86081f98f36 -> c9814fa447e4 -> e54da7347dea
recent history: e54da734 Record integration hold status; c9814fa4 Record integration hold status; a86081f9 Refresh independent audit status; edf5d019 Record integration hold status; 7174a5dd Record integration hold status
tracked dirty rows: 330 -> 329 -> 331
default status rows including untracked: 17515 -> 17514 -> 17578
git diff --shortstat: 330 files changed, 231160 insertions(+), 28971 deletions(-) -> 329 files changed, 231095 insertions(+), 28971 deletions(-) -> 331 files changed, 231768 insertions(+), 29113 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 11:39:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T12:12:34Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T12:13:25Z:
no rows

handoff validation pgrep at 2026-05-24T12:16:50Z:
2429334 php tools/run-tests.php

owner evidence sampled immediately after handoff validation pgrep:
PID 2429334 USER claude PPID 2429254 STAT R+ ETIMES 114 COMMAND php tools/run-tests.php

latest pgrep at 2026-05-24T12:18:01Z:
no rows
```

I did not start `php tools/run-tests.php`. The exact gate was clear during the
audit samples, but the checkout moved from `a86081f9` through `c9814fa4` to
`e54da734` and the dirty tracked/default status counts changed, so this was not
a frozen acceptance snapshot. Handoff validation matched active no-argument
root PID `2429334` owned by `claude`; a later pgrep cleared, but I still did
not start root because the stability gate failed.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1064 / 838                     3245 / 0          735 / 374 / 374
dolt          string total / 613             425 / 0           inventory / 613 / 356
esbuild       2567 / 428                     428 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7152 / 0          2877 / 2751 / 5634
libsqlite     1589 / 347                     347 / 0           1589 / 286 / 286
LightningCSS  3548 / 2764                    4053 / 0          3532 / 1732 / 2197
markerPDF     394 / 345                      482 / 0           330 / 280 / 416
pandoc        2276 / 1891                    360 / 0           2276 / 1061 / 278
quadrable     55 / 55                        232 / 0           55 / 55 / 190
rclone        1601 / 901                     904 / 0           1601 / 698 / 698
readability   1984 / 1984                    3527 / 0          1984 / 1984 / 204
syncthing     658 / 658                      7850 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:47`, `audits/integration-status.md:9`,
     `audits/integration-status.md:15`, `audits/integration-status.md:25`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: during this audit `HEAD` moved from `a86081f9` through
     `c9814fa4` to `e54da734`, tracked dirty rows moved `330 -> 329 -> 331`,
     default status rows moved `17515 -> 17514 -> 17578`, and shortstat moved
     from `231160/28971` to `231768/29113`. Current lane status files still
     describe pending or uncommitted handoffs across every lane.

2. **Critical - there is no audit-acceptable root PHP result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:47`,
     `audits/integration-status.md:30`, `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with failures recorded honestly.
   - Evidence: the required exact process gate returned no rows in both audit
     samples, but the tree moved during the same audit window. Handoff
     validation then matched active no-argument root PID `2429334` owned by
     `claude`; a later pgrep cleared, but I still did not start root because
     the checkout was not a frozen snapshot. Lane statuses repeatedly say root
     aggregate verification is pending for the supervisor/integrator, so the
     focused green lane evidence is not a current accepted root result.

3. **Critical - `porting.html` and `porting-summary.json` are stale and still
   materially overstate status.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:1`,
     `porting-summary.json:2`, `porting-summary.json:7`,
     `porting-summary.json:216`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, and 22 dependency rows. Current `HEAD` is `e54da734`,
     and `dependency-backlog.json` has 37 rows.

4. **High - manifest, lane-status, and dashboard counts contradict each other
   across every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:9`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:44` and `goal.md:45` require
     comparable denominator, mapped-test, PHP pass/fail, blocker, current-work,
     and commit fields.
   - Evidence: Difftastic is manifest `1064/838`, status `3245/0`,
     dashboard `735/374/374`; Gitoxide is manifest `2877/2877`, status
     `7152/0`, dashboard `2877/2751/5634`; markerPDF is manifest `394/345`,
     status `482/0`, dashboard `330/280/416`; Readability is manifest
     `1984/1984`, status `3511/0`, dashboard `1984/1984/204`; Syncthing is
     manifest `658/658`, status `7850/0`, dashboard `658/658/4579`.

5. **High - Dolt still has a prose/string upstream denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2520`, `porting.html:57`,
     `porting-summary.json:28`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     benchmark denominator, and `goal.md:45` requires that denominator to be
     visible in the dashboard.
   - Evidence: `benchmarkDenominator.total` is still a long prose string
     while `benchmarkDenominator.mapped` is numeric `613`. The dashboard falls
     back to `inventory`, so Dolt coverage arithmetic cannot be audited.

6. **High - support-library coverage is accounted for on paper but still has
   zero accepted bounded ports.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:81`,
     `dependency-backlog.json:97`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:179`,
     `dependency-backlog.json:195`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:306`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:413`,
     `dependency-backlog.json:429`, `dependency-backlog.json:532`,
     `dependency-backlog.json:629`, `porting.html:75`.
   - Goal requirement at risk: the latest 2026-05-24 11:59 UTC
     support-library directive requires bounded native PHP components,
     activation gates, dependency-specific upstream/spec denominators, mapped
     fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant,
     and as much upstream/spec-suite evidence as can run.
   - Evidence: the 37-row backlog now covers the important Pandoc and PDF
     dependencies, plus Git wire, Quadrable proof transport, WebDAV, QR,
     source maps, protobuf, checksums, SQL, archive/compression, target data,
     package resolution, and tree-sitter subsets. However, all rows remain
     `candidate`, `deferred`, or `blocked`; there are `0` active support ports,
     and no dependency-specific support-library manifest files were found in
     the repo scan. Lane-local helper work must not count as shared support
     progress.

7. **High - markerPDF still mixes native PDF evidence with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:534`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:535`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:570`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:572`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:664`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:684`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:770`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:780`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:835`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:837`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid wrappers
     around JS/Rust/Go/C binaries or shell-outs from counting as the main
     deliverable; bridge/oracle tooling may only be temporary.
   - Evidence: the lane includes useful native PDF parsing and metadata
     slices, but the manifest still carries Pandoc/XeLaTeX shell plans,
     Streamlit/FastAPI/Uvicorn startup plans, chunk-convert shell lifecycle,
     OCRMyPDF/Tesseract/Ghostscript install/readiness plans, model loader
     plans, and Poetry/model-runtime dependency planning. Those are blockers,
     caller-supplied boundaries, or oracle metadata, not native port progress.

8. **Medium - progress and status surfaces lag current lane handoffs.**
   - Paths: `progress.md:139`, `progress.md:143` through `progress.md:156`,
     `lanes/*/lane-status.json:10`, `lanes/*/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, blockers, and percentage estimates in `progress.md`.
   - Evidence: `progress.md` still lists older handoff labels such as
     Gitoxide SSH config options, markerPDF benchmark file-inventory planning,
     and Syncthing system log routing, while current lane statuses describe
     newer Git index rewrites, PDF outline/metadata work, GUI static path
     sanitization, and similar later slices.

9. **Medium - support-library backlog wording still has one known weak row.**
   - Paths: `dependency-backlog.json:306`, `dependency-backlog.json:313`,
     `dependency-backlog.json:315`, `audits/pdf-marker-support-dependency-scout-20260524T120230Z.md:23`.
   - Goal requirement at risk: the support-library directive requires every
     important dependency for essential rich function to be audited at bounded
     native-component granularity.
   - Evidence: the PDF/markerPDF support scout says `layout-ocr-result-core`
     should explicitly name the supplied reading-order contract from
     `marker/layout/order.py`; the current row implies layout/OCR results but
     does not name that contract in the row source or expectation. This is not
     a missing row, but it is a precision gap before activation.

10. **Medium - near-complete percentages continue to overstate accepted
    upstream and root parity.**
    - Paths: `porting.html:56` through `porting.html:67`,
      `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
      tests are not enough, upstream tests are the source of truth where
      possible, and hard gaps must be recorded as blockers or future slices.
    - Evidence: lane statuses and the stale dashboard show `95%` to `99%`
      progress while root aggregate verification is pending across lane
      handoffs and full upstream runners remain unrun, static-only, or bounded
      for Difftastic, Gitoxide, markerPDF, Pandoc, Syncthing, Dolt, rclone,
      esbuild release-extra coverage, and SQLite all/release permutations.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, capacity executors, Dolt, and the Dolt runner.
Require two stable polls of `HEAD`, tracked rows, untracked-inclusive rows,
shortstat, exact process gates, dependency/dashboard counts, and relevant log
mtimes. Then accept exactly one coherent lane batch with manifest/status schema
normalization, run focused lane verification plus `git diff --check`, activate
only the support-library rows whose base-lane gate is accepted or truly
blocked, add dependency-specific support manifests before counting support
progress, regenerate `porting.html` and `porting-summary.json` from the
accepted commit, and run one serialized no-argument `php tools/run-tests.php`
only if the exact process gate remains empty on that frozen snapshot.
