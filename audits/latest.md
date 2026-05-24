# Independent Audit - 2026-05-24T10:25Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T10:18Z through 2026-05-24T10:25:13Z
HEAD moved during this audit: d21ed3f2 -> 4c8f12f8 -> a2bd2646
recent history: a2bd2646 Record integration hold status; 4c8f12f8 Record integration hold status; d21ed3f2 Refresh independent audit status; 1df50db8 Record integration hold status
tracked dirty rows: 329 -> 328 -> 328 -> 330
default status rows including untracked: 16822 -> 16881 -> 16885 -> 16894
git diff --shortstat: 328 files changed, 215455 insertions(+), 28968 deletions(-) -> 328 files changed, 215897 insertions(+), 28979 deletions(-) -> 330 files changed, 216704 insertions(+), 29568 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 10:13:24 UTC with 32 rows (22 candidate, 10 deferred); dashboard still shows 22 rows
root run by this audit: not started; the exact process gate transiently matched a focused MarkerPDF harness that exited before owner sampling, then returned no rows, but HEAD/status/shortstat moved while required files were being read
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' during initial gate:
1148287 php tools/run-tests.php lanes/markerpdf/tests

ps -o pid,user,ppid,etimes,stat,args -p 1148287:
no row; process exited before owner sampling

later pgrep -af '^php tools/run-tests\.php( |$)' samples:
no rows

final pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:22:03Z:
no rows
```

I did not start `php tools/run-tests.php`. The final exact gate was clear, but
the checkout was not stable enough for a serialized audit-owned root result:
`HEAD`, untracked-inclusive status rows, and shortstat changed during the
audit. `jq empty` passed for all 12 root lane manifests, all 12 lane-status
files, `porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status/dashboard count sample:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    990 / 746 / n/a                 3109              735 / 374 / 374
dolt          prose total / 613 / 402         419               inventory / 613 / 356
esbuild       2567 / 409 / 408                409               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6979              2877 / 2751 / 5634
libsqlite     1589 / 338 / n/a                338               1589 / 286 / 286
LightningCSS  3535 / 2726 / n/a               3918              3532 / 1732 / 2197
markerPDF     382 / 333 / 469                 469               330 / 280 / 416
pandoc        2276 / 1807 / n/a               348               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   226               55 / 55 / 190
rclone        1601 / 875 / 875                873               1601 / 698 / 698
readability   1984 / 1984 / 249               249               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7379              658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:43`, `progress.md:45`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:28`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/readability/lane-status.json:73`,
     `lanes/syncthing/lane-status.json:88`.
   - Goal requirement at risk: `goal.md:29` requires small, reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: `HEAD` moved from `d21ed3f2` through `4c8f12f8` to `a2bd2646`
     while this audit was reading required inputs. The dirty tree moved from
     329 tracked rows and 16822 total status rows to 330 tracked rows and
     16894 total rows, and shortstat moved from `328 files changed, 215455
     insertions(+), 28968 deletions(-)` to `330 files changed, 216704
     insertions(+), 29568 deletions(-)`. Lane statuses still say pending,
     uncommitted, or supervisor-owned acceptance rather than accepted lane
     commits.

2. **Critical - there is still no acceptable repo-wide PHP result for this
   exact snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:584`,
     `lanes/esbuild/lane-status.json:25`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/readability/lane-status.json:72`,
     `lanes/syncthing/lane-status.json:87`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with honest failure recording.
   - Evidence: the exact pre-root gate first matched focused MarkerPDF PID
     `1148287` and then cleared; the PID exited before owner sampling. No
     audit-owned no-argument root run was started because the checkout failed
     the stability gate. Current lane blockers continue to leave aggregate
     root verification to the supervisor/integrator.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `porting-summary.json:216`, `porting-summary.json:218`,
     `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md:3` requires current coordination
     files; `goal.md:45` requires the dashboard to show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and
     22 dependency rows. Current `HEAD` is `a2bd2646`, and
     `dependency-backlog.json` is updated `2026-05-24 10:13:24 UTC` with 32
     rows.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:213`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     denominator; `goal.md:44` and `goal.md:45` require comparable
     denominator, mapped-test, PHP pass/fail, blocker, and commit fields.
   - Evidence: every lane has at least one manifest/status/dashboard mismatch
     in the count table above. Some manifests have no normalized PHP pass
     field; lane statuses mix behavior-test counts and assertion counts; the
     dashboard is an older generated artifact. `rclone` now reports manifest
     PHP `875`, lane-status PHP `873`, and dashboard PHP `698`.

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2503`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2509`,
     `lanes/dolt/lane-status.json:6`, `porting.html:57`.
   - Goal requirement at risk: `goal.md:25` requires the real upstream
     benchmark denominator to be mapped; `goal.md:45` requires a dashboard
     denominator that can be compared at a glance.
   - Evidence: `benchmarkDenominator.total` is still a prose OCT evidence
     paragraph rather than a numeric denominator. The same lane reports
     manifest `mapped = 613`, manifest `phpBehaviorTests = 402`, lane-status
     `phpPass = 419`, and dashboard `356 pass` against an `inventory`
     denominator.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:358`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:87`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not
     enough; `goal.md:37` says upstream tests are the source of truth where
     possible; `goal.md:40` requires hard gaps to be blockers or future
     slices.
   - Evidence: rows remain at 95-99 percent while root verification is
     pending, Difftastic full Cargo parity is unavailable, esbuild
     release-extra `make test-all` is static-only, Gitoxide full Cargo
     workspace parity is unrun, SQLite all/release permutations remain outside
     the bounded pass, rclone live provider/mount parity is open, and
     Syncthing full `go test ./...` remains unexecuted.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:4`, `dependency-backlog.json:21`,
     `dependency-backlog.json:41`, `dependency-backlog.json:57`,
     `dependency-backlog.json:77`, `dependency-backlog.json:253`,
     `dependency-backlog.json:270`, `dependency-backlog.json:286`,
     `dependency-backlog.json:447`, `dependency-backlog.json:466`,
     `dependency-backlog.json:503`, `dependency-backlog.json:544`,
     `porting.html:75`.
   - Goal requirement at risk: `goal.md:35` requires meaningful fixture parity,
     edge-case coverage, and error behavior; the support-library audit
     requirement requires bounded native PHP components, activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec coverage as can honestly run.
   - Evidence: all 32 dependency rows are still `candidate` or `deferred`.
     None has an active support-library manifest with PHP pass/fail evidence.
     Rich gaps remain for ZIP/package containers, XML/HTML, WebDAV, URL
     percent encoding, Unicode/charset, JSON/JSON5, source maps, tree-sitter
     grammar subsets, sequence diff/merge, protobuf, checksum/hash, SQL
     expression semantics, and archive/compression. The dashboard still shows
     only the stale 22-row table.

8. **High - dependency-adjacent work is still lane-local rather than bounded
   shared support-library progress.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1371`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1373`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:55`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:56`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:57`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:352`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:1062`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:530`, `dependency-backlog.json:551`.
   - Goal requirement at risk: optional-library work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators before it can count as support-library progress.
   - Evidence: rclone carries WebDAV/gzip/archive-adjacent behavior,
     esbuild carries source-map/JSON behavior, and libsqlite carries JSON/JSON5
     expression behavior. These may be useful lane slices, but they are not
     shared support-library progress until separate activation-gated manifests,
     upstream/spec denominators, malformed cases, and PHP pass/fail evidence
     exist.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:798`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1036`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1037`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1044`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1046`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1052`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1057`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1078`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, shell-outs, external converter/runtime execution,
     and whole applications as native port progress.
   - Evidence: markerPDF has useful native PDF parsing/extraction slices, but
     the manifest still enumerates Pandoc/XeLaTeX helper planning,
     multiprocessing/model-runtime planning, `chunk_convert` shell launcher
     planning, Streamlit command planning, OCRMyPDF/Tesseract/Ghostscript
     setup planning, and dotenv/env-file discovery planning. These must remain
     preflight/oracle metadata unless a bounded native PHP component owns and
     tests the behavior.

10. **Medium - shell-out and external-process boundaries need continued
    explicit labeling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:66`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1096`.
    - Goal requirement at risk: `goal.md:30` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide still leaves shell-backed filter/askpass child-process
      integration and authenticated/channel adapters as caller-supplied gaps;
      rclone excludes live provider, FUSE, Docker, auth-proxy, and
      credential-bearing tests; Dolt records focused upstream runners and root
      harness ownership as external to the lane; markerPDF correctly states
      native outline parsing avoids external PDF tools. Keep this labeling
      strict so oracle tooling does not become hidden progress credit.

## Next Best Intervention

Freeze active writers, status/dashboard publishers, focused lane harnesses, and
external runners. Require two stable polls of `HEAD`, tracked dirty rows,
untracked-inclusive rows, shortstat, exact `php tools/run-tests.php` gate,
dependency/dashboard counts, and relevant log mtimes. Then accept exactly one
owner-free lane batch, normalize its manifest/status/dashboard counts, run
focused lane verification plus `git diff --check`, run one serialized
no-argument `php tools/run-tests.php` only from that same frozen snapshot if the
process gate remains empty, regenerate dashboard artifacts from the accepted
commit, and commit or reject.
