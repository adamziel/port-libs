# Independent Audit - 2026-05-24T10:17Z

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
UTC samples: 2026-05-24T10:12:15Z, 2026-05-24T10:13:58Z, and 2026-05-24T10:16:47Z, plus required input reads
HEAD moved during this audit: b9972f5e -> 9c283fa6 (transient) -> beadd96f -> 1df50db8
recent history: 1df50db8 Record integration hold status; beadd96f Record integration hold status; b9972f5e Refresh independent audit status; 50254be3 Record integration hold status
tracked dirty rows: 326 -> 326 -> 330 (final sample includes this audit/progress edit)
default status rows including untracked: 16754 -> 16754 -> 16819
git diff --shortstat: 326 files changed, 213036 insertions(+), 27839 deletions(-) -> 326 files changed, 213182 insertions(+), 27842 deletions(-) -> 330 files changed, 213520 insertions(+), 27993 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 09:55:45 UTC with 32 rows (22 candidate, 10 deferred); dashboard still shows 22 rows
root run by this audit: not started; the exact process gate returned no rows, but HEAD and shortstat moved while this audit was reading required files
```

Required pre-root process-gate evidence:

```text
initial pgrep -af '^php tools/run-tests\.php( |$)':
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:12:15Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:13:58Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:16:47Z:
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was clear,
but the checkout was not stable enough for a serialized audit-owned root
result: `HEAD` moved during the audit, and shortstat changed between polls.
`jq empty` passed for all 12 root lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status/dashboard count sample:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    990 / 746 / n/a                 3109              735 / 374 / 374
dolt          prose total / 613 / 402         418               inventory / 613 / 356
esbuild       2567 / 408 / 408                408               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6957              2877 / 2751 / 5634
libsqlite     1589 / 337 / n/a                338               1589 / 286 / 286
LightningCSS  3535 / 2721 / n/a               3910              3532 / 1732 / 2197
markerPDF     381 / 332 / 469                 469               330 / 280 / 416
pandoc        2276 / 1803 / n/a               347               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   225               55 / 55 / 190
rclone        1601 / 873 / 873                873               1601 / 698 / 698
readability   1984 / 1984 / 248               248               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7357              658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:42`, `progress.md:123`, `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` requires small, reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: `HEAD` moved during this audit from audit commit `b9972f5e` to
     transient `9c283fa6`, then `beadd96f`, then `1df50db8`. Dirty status
     moved from 326 tracked rows and 16754 total rows to 330 tracked rows and
     16819 total rows in the final sample, and shortstat moved from
     `326 files changed, 213036 insertions(+), 27839 deletions(-)` to
     `330 files changed, 213520 insertions(+), 27993 deletions(-)`. Lane
     statuses still describe pending or uncommitted shared dirty batches rather
     than accepted commits.

2. **Critical - there is still no acceptable repo-wide PHP result for this
   exact snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:581`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with honest failure recording.
   - Evidence: the required exact process gate returned no rows in all audit
     samples, but no audit-owned no-argument root run was started because the
     checkout failed the stability gate. Current lane statuses continue to
     leave root aggregate verification pending or explicitly outside the lane
     worker handoff.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:1`, `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md:3` requires current coordination files;
     `goal.md:45` requires current per-lane denominator, mapped tests, PHP
     pass/fail, phase, audit, work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and
     22 dependency rows. Current observed `HEAD` is `1df50db8`, and
     `dependency-backlog.json` is updated `2026-05-24 09:55:45 UTC` with 32
     rows.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:1`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable upstream denominators, mapped upstream tests, PHP
     pass/fail counts, audit status, and latest commit per lane.
   - Evidence: the count table above shows every lane has at least one
     manifest/status/dashboard mismatch. Several manifests omit a normalized
     PHP pass field, while lane-status files mix behavior-test counts and
     assertion counts. `porting.html` is not a reliable at-a-glance source of
     truth for the current checkout.

5. **High - Dolt still has a non-machine-checkable denominator and stale PHP
   counts.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2503`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2509`,
     `lanes/dolt/lane-status.json:6`, `porting.html:57`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:29` requires accepted slices to be reviewable and
     passing; `goal.md:45` requires dashboard counts to be accurate.
   - Evidence: `benchmarkDenominator.total` is still a prose OCT evidence
     paragraph rather than a numeric denominator. The same lane reports
     manifest `mapped = 613`, manifest `phpBehaviorTests = 402`, lane-status
     `phpPass = 418`, and dashboard `356 pass` with an `inventory` denominator.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:669`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:1023`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:98`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:358`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:1053`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1262`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1610`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough;
     `goal.md:37` says upstream tests are the source of truth where possible;
     `goal.md:40` requires hard gaps to be blockers or future slices.
   - Evidence: dashboard rows still show 98-99 percent for many lanes while
     full Difftastic Cargo, esbuild release-extra `make test-all`, SQLite
     all/release permutations, rclone provider/mount parity, Syncthing
     full-runner parity, markerPDF full Python/PDF/model benchmark parity, and
     a serialized no-argument root PHP result from one accepted snapshot remain
     unexecuted or explicitly out of the current handoff.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:5`, `dependency-backlog.json:17`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:308`, `dependency-backlog.json:333`,
     `dependency-backlog.json:359`, `dependency-backlog.json:381`,
     `dependency-backlog.json:486`, `dependency-backlog.json:530`,
     `dependency-backlog.json:551`, `porting.html:75`.
   - Goal requirement at risk: `goal.md:35` requires meaningful fixture parity,
     edge-case coverage, and error behavior; the support-library audit
     requirement requires bounded native PHP components, activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec coverage as can honestly run.
   - Evidence: all 32 dependency rows are still `candidate` or `deferred`; none
     is an active support-library manifest with PHP pass/fail evidence. Rich
     gaps remain for ZIP/package containers, XML/HTML, WebDAV, URL percent
     encoding, Unicode/charset, JSON/JSON5, source maps, checksum/hash, SQL
     expression semantics, and archive/compression. The dashboard still
     publishes only the older 22-row dependency table.

8. **High - dependency-adjacent work is still lane-local rather than bounded
   shared support-library progress.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1262`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1373`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:351`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:363`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:17`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:359`, `dependency-backlog.json:381`,
     `dependency-backlog.json:551`.
   - Goal requirement at risk: optional-library work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators before it can count as support-library progress.
   - Evidence: rclone carries lane-local WebDAV/gzip/archive-adjacent behavior,
     esbuild carries a bounded source-map/JSON-adjacent parser slice, and
     libsqlite carries JSON/JSON5 expression behavior. Those may be useful lane
     slices, but they are not shared dependency progress until separate
     activation-gated manifests, upstream/spec denominators, malformed cases,
     and PHP pass/fail evidence exist.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:798`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:805`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, shell-outs, external converter/runtime execution,
     and whole applications as native port progress.
   - Evidence: markerPDF has useful native PDF parsing and text/filter/font
     slices, but the lane still enumerates Streamlit, FastAPI/Uvicorn,
     multiprocessing, `chunk_convert.sh`, pdftext, pypdfium/PIL,
     Torch/Surya/Texify, OCRMyPDF, Tesseract, Ghostscript, Pandoc/XeLaTeX,
     Poetry, model downloads, workflow, and package runtime surfaces. These
     must remain preflight/oracle metadata unless a bounded native PHP component
     owns and tests the behavior.

10. **Medium - shell-out and external-process boundaries need continued
    explicit labeling.**
    - Paths: `lanes/gitoxide/lane-status.json:13`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/rclone/lane-status.json:13`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:25`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:51`.
    - Goal requirement at risk: `goal.md:30` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide external driver boundaries, markerPDF external
      converters/runtime apps, rclone live providers/auth-proxy/FUSE surfaces,
      and Dolt full Go/BATS/server/client/benchmark boundaries remain open.
      Keep bounded upstream runners and oracle tooling separate from native
      parity accounting.

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
