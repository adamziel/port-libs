# Independent Audit - 2026-05-24T09:45Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T09:38:24Z, 2026-05-24T09:39:17Z, 2026-05-24T09:42:39Z, 2026-05-24T09:43:11Z, and 2026-05-24T09:45:23Z
HEAD moved during audit: 9c281932d640 -> 1c1cb23ef1a8
recent history: 1c1cb23e Record integration hold status; 9c281932 Refresh independent audit status; ac6ad836 Record integration hold status; e67ad554 Track Pandoc syntax highlighting dependency
tracked dirty rows: 325 -> 327 -> 328
default status rows including untracked: 16470 -> 16472 -> 16473 -> 16480 -> 16481
git diff --shortstat: 325 files changed, 208008 insertions(+), 27830 deletions(-) -> 325 files changed, 208116 insertions(+), 27829 deletions(-) -> 327 files changed, 208334 insertions(+), 27992 deletions(-) -> 326 files changed, 208440 insertions(+), 27969 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json has 32 rows (22 candidate, 10 deferred); dashboard still shows 22 rows
root run by this audit: not started; the checkout failed the stability gate while HEAD, status, and shortstat moved; validation later matched a focused Syncthing PHP harness, then an externally started no-argument root harness
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:38:24Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:39:17Z:
no rows

final validation pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:43:11Z:
726554 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,ppid,user,stat,etime,args -p 726554:
726554 687393 claude Rs 00:13 php tools/run-tests.php lanes/syncthing/tests

pre-final pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:45:23Z:
745306 php tools/run-tests.php

ps -o pid,ppid,user,stat,etime,args -p 745306:
745306 745270 claude R 00:17 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. Early process-gate samples were
empty, but the tree was not stable enough for an audit-owned root run. Final
validation matched a focused Syncthing lane harness, and the pre-final gate
then matched externally started no-argument root PID `745306` owned by
`claude`. All lanes still advertise pending or uncommitted handoffs that
require supervisor acceptance.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `lanes/*/lane-status.json`, recent Git history.
   - Goal requirement at risk: `goal.md:29` requires small, reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, and integrated cleanly; `goal.md:49` requires
     repo-wide verification.
   - Evidence: `HEAD` moved during this audit from `9c281932d640` to
     `1c1cb23ef1a8`, and the worktree still has 328 tracked dirty rows plus
     more than 16,000 default status rows. Default status rows moved `16470 ->
     16472 -> 16473 -> 16480 -> 16481`, and shortstat moved from `325 files
     changed, 208008 insertions(+), 27830 deletions(-)` to `326 files changed,
     208440 insertions(+), 27969 deletions(-)`. Every lane status still
     reports `latestCommit` as
     `pending`, `uncommitted`, `not committed`, or a stale HEAD reference.

2. **Critical - there is no acceptable repo-wide PHP result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with honest failure recording.
   - Evidence: `pgrep -af '^php tools/run-tests\.php( |$)'` returned no rows
     in early audit samples, but the checkout failed the stability gate. Final
     validation matched focused Syncthing PID `726554` owned by `claude`
     (`php tools/run-tests.php lanes/syncthing/tests`), and the pre-final gate
     matched externally started no-argument root PID `745306` owned by
     `claude` (`php tools/run-tests.php`). Neither process is an audit-owned
     serialized root result for `1c1cb23ef1a8` plus the current dirty
     worktree.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:4`,
     `porting-summary.json:215`, `dependency-backlog.json`.
   - Goal requirement at risk: `goal.md:3` requires current coordination
     files; `goal.md:45` requires the dashboard to show current per-lane
     denominator, mapped tests, PHP pass/fail, phase, audit, current work,
     blocker, and commit.
   - Evidence: the dashboard still claims average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and `22`
     dependency rows. Current `HEAD` is `1c1cb23ef1a8`, and
     `dependency-backlog.json` has 32 rows.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable upstream denominators, mapped upstream tests, PHP
     pass/fail counts, audit status, and latest commit per lane.
   - Evidence:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    969 / 709 / n/a                 3053              735 / 374 / 374
dolt          prose total / 613 / 401         417               inventory / 613 / 356
esbuild       2567 / 402 / 402                402               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6890              2877 / 2751 / 5634
libsqlite     1589 / 335 / n/a                335               1589 / 286 / 286
LightningCSS  3535 / 2706 / n/a               3893              3532 / 1732 / 2197
markerPDF     378 / 329 / 466                 466               330 / 280 / 416
pandoc        2276 / 1774 / n/a               343               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   223               55 / 55 / 190
rclone        1601 / 867 / 867                867               1601 / 698 / 698
readability   1984 / 1984 / 244               245               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7284              658 / 658 / 4579
```

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2496`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2502`,
     `lanes/dolt/lane-status.json:5`,
     `lanes/dolt/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:29` requires accepted slices to be reviewable and
     passing; `goal.md:45` requires dashboard counts to be accurate.
   - Evidence: `benchmarkDenominator.total` is a prose BIN evidence paragraph,
     not a numeric denominator. The manifest says `mapped = 613` and
     `phpBehaviorTests = 401`; lane status says `phpPass = 417`; dashboard
     still says `356 pass` and `inventory` total.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`,
     `porting.html:56` through `porting.html:67`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough;
     `goal.md:37` says upstream tests are the source of truth where possible;
     `goal.md:40` requires hard gaps to be blockers or future slices.
   - Evidence: multiple lanes claim 98-99 percent while full Difftastic Cargo,
     Gitoxide Cargo workspace, Pandoc Haskell, Syncthing `go test ./...`,
     broad Dolt Go/BATS, broad rclone provider/mount parity, release-extra
     esbuild `make test-all`, and root aggregate PHP remain unexecuted or
     explicitly pending.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:45`, `dependency-backlog.json:162`,
     `dependency-backlog.json:378`, `dependency-backlog.json:467`,
     `dependency-backlog.json:527`, `dependency-backlog.json:548`,
     `porting.html:75`.
   - Goal requirement at risk: support libraries need the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec coverage as can honestly run.
   - Evidence: all 32 dependency rows are `candidate` or `deferred`; none is
     an active support-library manifest with PHP pass/fail evidence. Rich
     Pandoc/markerPDF/rclone/esbuild/LightningCSS/Syncthing gaps still need
     bounded rows such as ZIP/package, XML/HTML, WebDAV, URL-percent-encoding,
     Pandoc syntax highlighting, Source Map v3, protobuf wire, SQL expression
     semantics, and archive/compression streams before their behavior can
     count as shared dependency progress.

8. **High - rclone WebDAV/provider/compression expansion is too broad to count
   as shared dependency progress.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:56`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1348` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1365`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:12`,
     `dependency-backlog.json:45`.
   - Goal requirement at risk: support-library expansion must be bounded,
     gated, tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone carries lane-local WebDAV behavior across filename/XML
     escaping, PROPFIND, PROPPATCH, LOCK/If, COPY/MOVE error ordering, gzip,
     VFS ZIP/serve surfaces, OneDrive metadata and permission planning, and
     provider upload/copy flows. That is not accepted shared WebDAV/XML/URL or
     archive/provider progress until a bounded support library has its own
     manifest, activation gate, upstream/spec denominator, malformed cases,
     and PHP pass/fail evidence.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:501`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:929` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1048`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, shell-outs, external converter/runtime execution,
     and whole applications as native port progress.
   - Evidence: markerPDF has useful native PDF text/filter/font slices, but
     the lane status and manifest still enumerate plan-only or supplied
     boundaries for `marker_app`, `marker_server`, `convert.py`,
     `chunk_convert`, `pdftext`, Streamlit, FastAPI/Uvicorn, Poetry,
     Torch/Surya/Texify, Nougat, OCRMyPDF/Tesseract, Ghostscript, Pandoc and
     XeLaTeX. These must remain explicit preflight/oracle metadata unless a
     bounded native PHP component owns the behavior.

10. **Medium - Gitoxide shell-outs remain acceptable only as explicit oracle
    tooling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1573`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1574`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1576`.
    - Goal requirement at risk: `goal.md:30` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide correctly says shell-backed filter process launch is
      not implemented or counted, but its status still tracks shell-backed
      filters, askpass, SSH, external merge drivers, URLs, and transport
      surfaces. They can remain caller-supplied or oracle boundaries, but
      should not inflate accepted native parity.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops. Wait for two stable `HEAD`, dirty-count, shortstat, and exact
process-gate polls. Accept or reject one lane batch at a time, normalizing
manifest/status/dashboard numeric fields before publication. Split optional
dependency work into manifest-backed bounded support-library ports only behind
real base-lane gates. Run focused verification plus `git diff --check`, then
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit. Only after that, run one serialized no-argument
`php tools/run-tests.php` from the frozen snapshot if the exact process gate is
still empty.
