# Independent Audit - 2026-05-24T11:08Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`72d0f496 Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T11:01Z through 2026-05-24T11:08Z
HEAD moved during audit validation: 70ede3bd3a23 -> 00ee8fcb51cc -> 72d0f496683f
recent history: 72d0f496 Record integration hold status; 00ee8fcb Record integration hold status; 70ede3bd Refresh independent audit status; 550ac606 Record integration hold status
tracked dirty rows moved: 329 -> 328 -> 330
default status rows including untracked moved: 16980 -> 16996
git diff --shortstat moved: 329 files changed, 221406 insertions(+), 28702 deletions(-) -> 328 files changed, 221346 insertions(+), 28702 deletions(-) -> 330 files changed, 222552 insertions(+), 28749 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 10:51:38 UTC with 34 rows (1 blocked, 22 candidate, 11 deferred); dashboard still shows 22 rows (12 candidate, 10 deferred)
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' sample at 2026-05-24T11:01:58Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' sample at 2026-05-24T11:02:09Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' later sample at 2026-05-24T11:05:24Z:
1683193 php tools/run-tests.php lanes/syncthing/tests

owner evidence for active focused harness:
PID 1683193 USER claude PPID 1510084 STAT Rs ETIMES 37 COMMAND php tools/run-tests.php lanes/syncthing/tests

pgrep -af '^php tools/run-tests\.php( |$)' final sample at 2026-05-24T11:06:47Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' later sample at 2026-05-24T11:08:01Z:
1721281 php tools/run-tests.php

owner evidence for active no-argument root harness:
PID 1721281 USER claude PPID 1721244 STAT R ETIMES 21 COMMAND php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact process gate was clear in
early samples, later matched a focused Syncthing harness, cleared again, and
then matched an externally started no-argument root harness. I did not start a
duplicate. The checkout failed the stability gate because `HEAD`, status counts,
and shortstat moved during validation. A root result from this moving,
unaccepted checkout would not prove the committed state. `jq empty` passed for
all 12 lane manifests, all 12 lane-status files, `porting-summary.json`, and
`dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1012 / 778                     3148 / 0          735 / 374 / 374
dolt          string total / 613             421 / 0           inventory / 613 / 356
esbuild       2567 / 413                     413 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7024 / 0          2877 / 2751 / 5634
libsqlite     1589 / 341                     341 / 0           1589 / 286 / 286
LightningCSS  3535 / 2743                    4018 / 0          3532 / 1732 / 2197
markerPDF     386 / 337                      474 / 0           330 / 280 / 416
pandoc        2276 / 1832                    352 / 0           2276 / 1061 / 278
quadrable     55 / 55                        227 / 0           55 / 55 / 190
rclone        1601 / 884                     884 / 0           1601 / 698 / 698
readability   1984 / 1984                    253 / 0           1984 / 1984 / 204
syncthing     658 / 658                      7614 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:45`, `progress.md:47`,
     `audits/integration-status.md:16`, `audits/integration-status.md:20`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires small, reviewable committed
     slices with passing tests, and requires finished agent work to be verified,
     committed, integrated, and cleaned up.
   - Evidence: during this audit `HEAD` moved from `70ede3bd3a23` through
     `00ee8fcb51cc` to `72d0f496683f`, tracked dirty rows moved
     `329 -> 328 -> 330`, default status rows moved `16980 -> 16996`, and
     shortstat moved from `329 files changed, 221406 insertions(+), 28702
     deletions(-)` to `330 files changed, 222552 insertions(+), 28749
     deletions(-)`. Lane statuses still describe pending, uncommitted, or
     supervisor-owned batches across all active lanes.

2. **Critical - there is still no acceptable no-argument repo-wide PHP result
   for the current snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:47`,
     `audits/integration-status.md:41`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required exact process gate returned no rows in early
     audit-owned samples, then matched focused Syncthing PID `1683193` owned by
     `claude` (`php tools/run-tests.php lanes/syncthing/tests`), and later
     matched externally started no-argument root PID `1721281` owned by
     `claude` (`php tools/run-tests.php`). The checkout also moved while being
     sampled. Focused lane evidence, active root execution, and worker-local
     reports are not a substitute for one completed serialized no-argument root
     result from a frozen accepted tree.

3. **Critical - `porting.html` and `porting-summary.json` are stale publication
   artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md` requires current coordination files and
     a dashboard showing denominator, mapped tests, PHP pass/fail, phase, audit,
     current work, blocker, and commit for each lane.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, and 22 dependency rows. Current `HEAD` is
     `72d0f496683f`, and `dependency-backlog.json` has 34 rows including one
     blocked support-library row.

4. **High - manifest, lane-status, and dashboard counts remain non-normalized
   and contradictory across active lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:59`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md` requires comparable upstream
     denominator, mapped tests, PHP pass/fail, blocker, and commit fields at a
     glance.
   - Evidence: every lane disagrees with at least one of manifest, status, or
     dashboard. Examples: Difftastic is manifest `1012/778`, status `3148/0`,
     dashboard `735/374/374`; LightningCSS is manifest `3535/2743`, status
     `4018/0`, dashboard `3532/1732/2197`; Syncthing is manifest `658/658`,
     status `7614/0`, dashboard `658/658/4579`.

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2508`,
     `lanes/dolt/lane-status.json:5`, `porting.html:57`,
     `porting-summary.json:32`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator to be mapped and visible in the dashboard.
   - Evidence: `benchmarkDenominator.total` is a prose string at line 2508
     instead of a numeric denominator, while `benchmarkDenominator.mapped` is
     numeric `613`. The dashboard falls back to `inventory`.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:4`,
     `lanes/esbuild/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md` says passing focused tests are not
     enough, upstream tests are the source of truth where possible, and hard
     gaps must be blockers or future slices.
   - Evidence: dashboard rows still show 92-99 percent while root verification
     is pending and several full upstream suites remain unexecuted or
     static-only: Difftastic full Cargo parity, esbuild `make test-all`,
     Gitoxide full workspace Cargo parity, SQLite `all`/`release`
     permutations, Pandoc full Haskell runner parity, rclone live
     provider/mount parity, markerPDF full model/runtime benchmark execution,
     and Syncthing full `go test ./...`.

7. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `progress.md:17`, `progress.md:30`,
     `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `porting.html:75`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much of the upstream/spec suite as can honestly run.
   - Evidence: all 34 support rows are still `blocked`, `candidate`, or
     `deferred`; none is an active support-library manifest with PHP pass/fail
     evidence. Rich-function gaps remain for ZIP/package containers, XML/HTML,
     WebDAV, URL percent encoding, Unicode/charset, JSON/JSON5, source maps,
     package resolution, tree-sitter grammar subsets, sequence diff/merge,
     protobuf, checksum/hash, SQL expression semantics, archive/compression, QR
     matrix generation, and MySQL wire protocol boundaries.

8. **High - dependency-adjacent behavior is accumulating inside lanes before
   shared support-library gates are opened.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/esbuild/lane-status.json:5`,
     `lanes/readability/lane-status.json:5`,
     `lanes/libsqlite/lane-status.json:5`,
     `lanes/syncthing/lane-status.json:5`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`.
   - Goal requirement at risk: optional dependency work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators before it can count as support-library progress.
   - Evidence: rclone is carrying WebDAV/XML/URL behavior, Readability is
     carrying URL cleanup, esbuild is carrying source-map/JSON behavior,
     libsqlite is carrying JSON/JSON5/operator semantics, and Syncthing has QR
     route work while `qr-code-matrix-core` remains blocked pending accepted
     integration. These may be valid lane slices, but they are not
     support-library progress until separate activation-gated manifests,
     upstream/spec denominators, malformed cases, and PHP pass/fail evidence
     exist.

9. **High - markerPDF still mixes native PDF slices with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:812`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: the lane has useful native PDF parsing/extraction slices, but
     its manifest/status also enumerate benchmark runners, Streamlit,
     FastAPI/Uvicorn, multiprocessing/model workers, OCRMyPDF/Tesseract/
     Ghostscript, Texify/Nougat, Poetry/publish tooling, shell lifecycle plans,
     and server/app route planning. These must stay labeled as preflight or
     oracle metadata unless a bounded native PHP component owns and tests the
     behavior.

10. **Medium - Syncthing status has a future audit timestamp.**
    - Paths: `lanes/syncthing/lane-status.json:10`.
    - Goal requirement at risk: `goal.md` requires honest current status,
      blockers, audit state, and latest commit evidence.
    - Evidence: the shell clock for this audit was `2026-05-24T11:08Z`, but
      Syncthing line 10 records `audited 2026-05-24 13:10 UTC`. That makes the
      status chronology unreliable until corrected or explicitly explained.

11. **Medium - shell-out and external-process boundaries need continued
    explicit labeling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `dependency-backlog.json:4`.
    - Goal requirement at risk: `goal.md` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide still leaves shell-backed filter/askpass child-process
      integration and real authenticated/channel adapters outside lane-local
      proof; rclone excludes live providers, FUSE, Docker, auth-proxy, and
      credential-bearing tests; markerPDF excludes external model/application
      execution. Keep this labeling strict so oracle tooling does not become
      hidden progress credit.

## Next Intervention

Hold implementation integration until there is a hard writer/runner/status
freeze, two stable polls of `HEAD`, tracked/default status counts, shortstat,
process gates, dependency/dashboard counts, and relevant log mtimes. Then accept
one coherent lane batch, normalize manifest/status/dashboard counts, run focused
verification plus `git diff --check`, create activation-gated support-library
manifests only where a concrete lane gate needs them, regenerate the dashboard
from the accepted commit, and run one serialized no-argument
`php tools/run-tests.php` only if the exact process gate stays empty on that
frozen snapshot.
