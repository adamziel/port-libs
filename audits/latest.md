# Independent Audit - 2026-05-24T10:38Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `0184c8c8 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T10:31Z through 2026-05-24T10:38Z
HEAD moved during audit: 9ab05b8a -> 0184c8c88e5c
recent history: 0184c8c8 Record integration hold status; 9ab05b8a Refresh independent audit status; ef93587f Record integration hold status; 78bdee1c Refresh independent audit status
tracked dirty rows: 328
default status rows including untracked moved: 16921 -> 16922
git diff --shortstat moved: 328 files changed, 217105 insertions(+), 28857 deletions(-) -> 328 files changed, 217289 insertions(+), 28855 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 10:13:24 UTC with 32 rows (22 candidate, 10 deferred); dashboard still shows 22 rows
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' during audit samples:
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was clear,
but the checkout failed the stability gate because `HEAD`, untracked-inclusive
status, and aggregate shortstat moved during this audit. `jq empty` passed for
all 12 root lane manifests, all 12 lane-status files, `porting-summary.json`,
and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    996 / 757                      3123 / 0          735 / 374 / 374
dolt          string total / 613             420 / 0           inventory / 613 / 356
esbuild       2567 / 411                     411 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    6998 / 0          2877 / 2751 / 5634
libsqlite     1589 / 340                     340 / 0           1589 / 286 / 286
LightningCSS  3535 / 2728                    3924 / 0          3532 / 1732 / 2197
markerPDF     383 / 334                      471 / 0           330 / 280 / 416
pandoc        2276 / 1813                    349 / 0           2276 / 1061 / 278
quadrable     55 / 55                        227 / 0           55 / 55 / 190
rclone        1601 / 877                     877 / 0           1601 / 698 / 698
readability   1984 / 1984                    251 / 0           1984 / 1984 / 204
syncthing     658 / 658                      7432 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:43`, `progress.md:45`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires small, reviewable committed
     slices with passing tests, and requires the supervisor to verify, commit,
     integrate, and clean up finished agent work.
   - Evidence: `HEAD` moved from `9ab05b8a` to `0184c8c88e5c` during this
     audit. The tree still has 328 tracked dirty rows and 16,922
     untracked-inclusive status rows. The aggregate shortstat moved from `328
     files changed, 217105 insertions(+), 28857 deletions(-)` to `328 files
     changed, 217289 insertions(+), 28855 deletions(-)` while this audit was
     sampling. Multiple lane statuses still mark work as pending, uncommitted,
     or supervisor/integrator-owned.

2. **Critical - there is still no acceptable no-argument repo-wide PHP result
   for the current snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:45`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the exact pre-root gate returned no rows, but no root run was
     started because the checkout moved during audit. Focused lane evidence is
     not a substitute for one serialized no-argument root result from a frozen
     accepted tree.

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
     `0184c8c88e5c`, and `dependency-backlog.json` has 32 rows.

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
   - Goal requirement at risk: `goal.md` requires the dashboard to expose
     comparable upstream denominator, mapped tests, PHP pass/fail, blocker, and
     commit fields at a glance.
   - Evidence: the count sample above shows mismatches across the current
     working tree and dashboard. Example: esbuild is manifest `2567/411`, status
     PHP `411/0`, dashboard `2567/311/311`; libsqlite is manifest `1589/340`,
     status `340/0`, dashboard `1589/286/286`; LightningCSS is manifest
     `3535/2728`, status `3924/0`, dashboard `3532/1732/2197`.

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/lane-status.json:5`, `porting.html:57`,
     `porting-summary.json:32`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator to be mapped and visible in the dashboard.
   - Evidence: `benchmarkDenominator.total` is still a prose string instead of
     a numeric denominator. The same lane reports mapped `613`, lane-status PHP
     `420/0`, and dashboard PHP `356`, so the denominator cannot be validated
     mechanically.

6. **High - near-complete percentages overstate accepted upstream/root parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says passing focused tests are not
     enough, upstream tests are the source of truth where possible, and hard
     gaps must be blockers or future slices.
   - Evidence: dashboard rows still show 92-99 percent while root verification
     is pending. Difftastic full Cargo parity is unavailable, esbuild
     `make test-all` remains static-only, Gitoxide full workspace Cargo parity
     is unrun, SQLite `all`/`release` permutations remain outside the bounded
     pass, Pandoc full Haskell runner parity is unexecuted, rclone live
     provider/mount parity remains open, markerPDF full runner depends on heavy
     external runtimes/models, and Syncthing full `go test ./...` is unexecuted.

7. **High - essential optional-library coverage is still backlog-only, with no
   active bounded support-library port.**
   - Paths: `progress.md:17`, `progress.md:29`,
     `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `porting.html:75`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much of the upstream/spec suite as can honestly run.
   - Evidence: all 32 support rows are still `candidate` or `deferred`; none is
     an active support-library manifest with PHP pass/fail evidence. Rich gaps
     remain for ZIP/package containers, XML/HTML, WebDAV, URL percent encoding,
     Unicode/charset, JSON/JSON5, source maps, tree-sitter grammar subsets,
     sequence diff/merge, protobuf, checksum/hash, SQL expression semantics,
     and archive/compression.

8. **High - dependency-adjacent behavior is accumulating inside lanes before
   shared support-library gates are opened.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:5`,
     `lanes/esbuild/lane-status.json:5`,
     `lanes/libsqlite/lane-status.json:5`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`.
   - Goal requirement at risk: optional dependency work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators before it can count as support-library progress.
   - Evidence: rclone is carrying substantial WebDAV/XML/URL behavior,
     Readability is carrying URL cleanup, esbuild is carrying source-map and
     JSON behavior, and libsqlite is carrying JSON/JSON5/operator semantics.
     These may be valid lane slices, but they are not support-library progress
     until separate activation-gated manifests, upstream/spec denominators,
     malformed cases, and PHP pass/fail evidence exist.

9. **High - markerPDF still mixes native PDF slices with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: the lane has useful native PDF parsing/extraction slices, but
     its manifest/status also enumerate benchmark runners, Streamlit,
     FastAPI/Uvicorn, multiprocessing/model workers, OCRMyPDF/Tesseract/
     Ghostscript, Texify/Nougat, Poetry/publish tooling, and shell lifecycle
     plans. These must stay labeled as preflight/oracle metadata unless a
     bounded native PHP component owns and tests the behavior.

10. **Medium - shell-out and external-process boundaries need continued
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

## Next Best Intervention

Freeze active writers, status/dashboard publishers, focused lane harnesses, and
external runners. Require two stable polls of `HEAD`, tracked dirty rows,
untracked-inclusive rows, shortstat, exact `php tools/run-tests.php` gate, and
dashboard source commit. Then accept one lane batch at a time: normalize
manifest/status/dashboard counts, run focused lane verification plus
`git diff --check`, add activation-gated support-library manifests only when a
real rich-function blocker requires them, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, and run one serialized
no-argument root result only if the exact process gate remains empty on that
frozen snapshot.
