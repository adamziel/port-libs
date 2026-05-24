# Independent Audit - 2026-05-24T10:31Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `78bdee1c Refresh independent audit status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T10:25Z through 2026-05-24T10:31Z
HEAD sampled: 78bdee1ceecb
recent history: 78bdee1c Refresh independent audit status; a2bd2646 Record integration hold status; 4c8f12f8 Record integration hold status; d21ed3f2 Refresh independent audit status
tracked dirty rows: 329
default status rows including untracked: 16901
git diff --shortstat moved during audit: 329 files changed, 216741 insertions(+), 28825 deletions(-) -> 329 files changed, 216776 insertions(+), 28832 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 10:13:24 UTC with 32 rows (22 candidate, 10 deferred); dashboard still shows 22 rows
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' during initial gate:
1218156 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,user,ppid,etimes,stat,args -p 1218156:
PID     USER    PPID     ELAPSED STAT COMMAND
1218156 claude  1143808  56      Rs   php tools/run-tests.php lanes/syncthing/tests

later pgrep -af '^php tools/run-tests\.php( |$)':
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate later cleared,
but the checkout failed the stability gate because the aggregate diff changed
while this audit was running. `jq empty` passed for all 12 root lane manifests,
all 12 lane-status files, `porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    996 / 757 / no normalized field 3123              735 / 374 / 374
dolt          string total / 613 / 402        420               inventory / 613 / 356
esbuild       2567 / 410 / 408                410               2567 / 311 / 311
gitoxide      2877 / 2877 / 6979              6979              2877 / 2751 / 5634
libsqlite     1589 / 339 / no normalized field 339              1589 / 286 / 286
LightningCSS  3535 / 2726 / no normalized field 3918            3532 / 1732 / 2197
markerPDF     382 / 333 / 470                 470               330 / 280 / 416
pandoc        2276 / 1813 / no normalized field 349             2276 / 1061 / 278
quadrable     55 / 55 / no normalized field   226               55 / 55 / 190
rclone        1601 / 877 / 877                875               1601 / 698 / 698
readability   1984 / 1984 / 250               250               1984 / 1984 / 204
syncthing     658 / 658 / no normalized field 7379              658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:43`, `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires small, reviewable committed
     slices with passing tests, and requires the supervisor to verify, commit,
     integrate, and clean up finished agent work.
   - Evidence: the tree has 329 tracked dirty rows and 16901 short-status rows
     including untracked files. The aggregate shortstat changed from `329 files
     changed, 216741 insertions(+), 28825 deletions(-)` to `329 files changed,
     216776 insertions(+), 28832 deletions(-)` during the audit. Multiple lane
     statuses still say pending, uncommitted, or supervisor/integrator-owned
     acceptance.

2. **Critical - there is no acceptable repo-wide PHP result for this exact
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:45`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required pre-root gate initially matched active PID
     `1218156` owned by `claude` running `php tools/run-tests.php
     lanes/syncthing/tests`. It later cleared, but the checkout was still
     moving, so no serialized audit-owned root result was started or recorded.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md` requires current coordination files and
     a dashboard showing current denominator, mapped tests, PHP pass/fail,
     phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`, source
     snapshot `79768df0c427`, generated time `2026-05-23 23:43:54 UTC`, and 22
     dependency rows. Current `HEAD` is `78bdee1ceecb`, and
     `dependency-backlog.json` has 32 rows.

4. **High - manifest, lane-status, and dashboard counts still disagree across
   every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:213`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires a real upstream denominator,
     mapped tests, PHP pass/fail counts, blocker, and commit fields that can be
     compared at a glance.
   - Evidence: the count table above shows every lane has a mismatch or missing
     normalized PHP pass/fail field. Example: rclone reports manifest
     `1601/877/877`, lane-status PHP `875`, and dashboard PHP `698`; esbuild
     reports manifest PHP `408`, lane-status PHP `410`, and dashboard PHP
     `311`; LightningCSS has manifest denominator `3535` while the dashboard
     still shows `3532`.

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2506`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2512`,
     `lanes/dolt/lane-status.json:6`, `porting.html:57`.
   - Goal requirement at risk: `goal.md` requires the real upstream benchmark
     denominator to be mapped and visible in the dashboard.
   - Evidence: `benchmarkDenominator.total` is still a prose string rather than
     a numeric denominator. The same lane reports manifest mapped `613`,
     manifest PHP `402`, lane-status PHP `420`, and dashboard PHP `356`.

6. **High - near-complete percentages overstate accepted upstream/root parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says passing tests are not enough,
     upstream tests are the source of truth where possible, and hard gaps must
     be blockers or future slices.
   - Evidence: dashboard rows remain 92-99 percent while root verification is
     pending. Difftastic full Cargo parity is unavailable, esbuild `make
     test-all` remains static-only, Gitoxide full Cargo workspace parity is
     unrun, SQLite all/release permutations remain outside the bounded pass,
     Pandoc full Haskell runner parity is unexecuted, rclone live
     provider/mount parity is open, and Syncthing full `go test ./...` remains
     unexecuted.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:4`, `dependency-backlog.json:7`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:309`, `dependency-backlog.json:333`,
     `porting.html:75`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/spec coverage as can honestly run.
   - Evidence: all 32 support rows are still `candidate` or `deferred`; none is
     an active support-library manifest with PHP pass/fail evidence. Rich gaps
     remain for ZIP/package containers, XML/HTML, WebDAV, URL percent encoding,
     Unicode/charset, JSON/JSON5, source maps, tree-sitter grammar subsets,
     sequence diff/merge, protobuf, checksum/hash, SQL expression semantics,
     and archive/compression.

8. **High - dependency-adjacent behavior is still lane-local rather than shared
   support-library progress.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1378`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1384`,
     `lanes/esbuild/lane-status.json:5`,
     `lanes/libsqlite/lane-status.json:12`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:333`.
   - Goal requirement at risk: optional-library work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators before it can count as support-library progress.
   - Evidence: rclone carries WebDAV/gzip/archive-adjacent behavior, esbuild
     carries source-map/JSON behavior, and libsqlite carries JSON/JSON5
     expression behavior. These may be useful lane slices, but they are not
     shared support-library progress until separate activation-gated manifests,
     upstream/spec denominators, malformed cases, and PHP pass/fail evidence
     exist.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:511`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:746`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:754`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1047`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: the lane has useful native PDF parsing/extraction slices, but
     its manifest/status also enumerate Pandoc/XeLaTeX helper planning,
     `chunk_convert` shell launcher planning, Streamlit/FastAPI/Uvicorn
     boundaries, multiprocessing/model-runtime planning, OCRMyPDF/Tesseract/
     Ghostscript readiness, and package/workflow setup. These must stay labeled
     as preflight/oracle metadata unless a bounded native PHP component owns and
     tests the behavior.

10. **Medium - shell-out and external-process boundaries need continued
    explicit labeling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:806`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1089`.
    - Goal requirement at risk: `goal.md` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide still leaves shell-backed filter/askpass child-process
      integration and authenticated/channel adapters as caller-supplied gaps;
      rclone excludes live providers, FUSE, Docker, auth-proxy, and
      credential-bearing tests; markerPDF correctly marks PDF/page-label work
      as native but still has many shell/application planning entries. Keep this
      labeling strict so oracle tooling does not become hidden progress credit.

## Next Best Intervention

Freeze active writers, status/dashboard publishers, focused lane harnesses, and
external runners. Require two stable polls of `HEAD`, tracked dirty rows,
untracked-inclusive rows, shortstat, exact `php tools/run-tests.php` gate, and
dashboard source commit. Then accept one lane batch at a time: normalize
manifest/status/dashboard counts, run focused lane verification plus
`git diff --check`, add activation-gated support-library manifests only when a
real rich-function blocker requires them, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, and run one serialized
no-argument root result only if the exact process gate remains empty.
