# Independent Audit - 2026-05-24T10:00Z

Scope reviewed: `goal.md`, `progress.md`, current worktree
`porting.html`, `porting-summary.json`, every root
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, current `lanes/*/lane-status.json`,
`dependency-backlog.json`, `audits/integration-status.md`, and recent Git
history. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T09:55:55Z, 2026-05-24T09:57:20Z, 2026-05-24T10:00:21Z, 2026-05-24T10:00:31Z, 2026-05-24T10:00:37Z, and 2026-05-24T10:02:39Z, plus initial audit sampling
HEAD moved during audit: 6d72151a -> 45ccf1b0 -> fca48fb8
recent history: fca48fb8 Record integration hold status; 45ccf1b0 Record integration hold status; 6d72151a Refresh independent audit status; b975423a Record integration hold status
tracked dirty rows: 325 -> 327 -> 328
default status rows including untracked: 16554 -> 16560 -> 16626 -> 16627
git diff --shortstat: 325 files changed, 209811 insertions(+), 27872 deletions(-) -> 327 files changed, 210072 insertions(+), 27813 deletions(-) -> 328 files changed, 211133 insertions(+), 27949 deletions(-) -> 328 files changed, 211285 insertions(+), 27946 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json has 32 rows (22 candidate, 10 deferred); dashboard still shows 22 rows
root run by this audit: not started; the exact process gate initially returned no rows, then transiently matched an externally started no-argument root PID that exited before owner sampling, and the checkout failed the stability gate while HEAD/status/shortstat and manifests/status files moved
```

Required pre-root process-gate evidence:

```text
Initial pgrep -af '^php tools/run-tests\.php( |$)':
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:57:20Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:00:21Z:
933029 php tools/run-tests.php

ps -o pid,ppid,user,stat,etime,args -p 933029 at 2026-05-24T10:00:31Z:
no row; PID exited before owner sampling

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:00:37Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:02:39Z:
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was clear in
the early samples, then transiently matched external no-argument root PID
`933029`; it exited before owner sampling and was gone by the next gate. The
tree was not stable enough for a serialized audit-owned root result:
`HEAD` moved from `6d72151a` to `fca48fb8`, tracked dirty rows moved
`325 -> 328`, untracked-inclusive rows moved `16554 -> 16627`, and shortstat
changed through `328 files changed, 211285 insertions(+), 27946 deletions(-)`
while this audit was reading required files. `jq empty` passed for all 12 root
lane manifests, all 12 lane-status files, `porting-summary.json`, and
`dependency-backlog.json`.

Current manifest/status/dashboard count sample:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    982 / 738 / n/a                 3071              735 / 374 / 374
dolt          prose total / 613 / 402         418               inventory / 613 / 356
esbuild       2567 / 408 / 408                406               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6934              2877 / 2751 / 5634
libsqlite     1589 / 337 / n/a                336               1589 / 286 / 286
LightningCSS  3535 / 2713 / n/a               3896              3532 / 1732 / 2197
markerPDF     380 / 331 / 467                 467               330 / 280 / 416
pandoc        2276 / 1798 / n/a               346               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   225               55 / 55 / 190
rclone        1601 / 871 / 871                869               1601 / 698 / 698
readability   1984 / 1984 / 247               247               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7338              658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:42`, `audits/integration-status.md:1`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` requires small, reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: `HEAD` moved during this audit from `6d72151a` through
     `45ccf1b0` to `fca48fb8`. Tracked dirty rows moved `325 -> 328`,
     default status rows moved `16554 -> 16627`, and shortstat moved from
     `325 files changed, 209811 insertions(+), 27872 deletions(-)` to
     `328 files changed, 211285 insertions(+), 27946 deletions(-)`. Every
     lane still has
     pending, uncommitted, lane-local, or stale `latestCommit` prose, for
     example `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

2. **Critical - there is still no acceptable repo-wide PHP result for this
   exact snapshot.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:34`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with honest failure recording.
   - Evidence: the required exact process gate initially returned no rows,
     then transiently matched external no-argument root PID `933029`
     (`php tools/run-tests.php`) at 10:00:21Z; `ps` at 10:00:31Z had no row
     because the PID exited before owner sampling, and the 10:00:37Z gate was
     clear again. I still did not start `php tools/run-tests.php` because the
     checkout failed the stability gate during required reads. The latest
     integration status records active no-argument root PID `914360` from
     another pass and no accepted lane output; lane statuses keep root
     verification pending, including `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`, and
     `lanes/readability/lane-status.json:12`.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `porting-summary.json:218`, `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md:3` requires current coordination
     files; `goal.md:45` requires the dashboard to show current per-lane
     denominator, mapped tests, PHP pass/fail, phase, audit, work, blocker,
     and commit.
   - Evidence: the dashboard still claims average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and 22
     dependency rows. Current `HEAD` is `fca48fb8`, and
     `dependency-backlog.json` was updated `2026-05-24 09:55:45 UTC` with 32
     rows.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:213`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable upstream denominators, mapped upstream tests, PHP
     pass/fail counts, audit status, and latest commit per lane.
   - Evidence: the count table in the snapshot shows every lane has at least
     one manifest/status/dashboard mismatch. Several manifests omit a
     normalized PHP pass field, while lane statuses use assertion counts for
     some lanes and behavior-test counts for others. This keeps the dashboard
     from being an at-a-glance source of truth.

5. **High - Dolt still has a non-machine-checkable denominator and stale PHP
   counts.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2500`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2506`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`,
     `porting.html:57`, `porting-summary.json:28` through
     `porting-summary.json:42`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     benchmark denominator; `goal.md:29` requires accepted slices to be
     reviewable and passing; `goal.md:45` requires dashboard counts to be
     accurate.
   - Evidence: `benchmarkDenominator.total` is still a long prose OCT
     evidence paragraph rather than a numeric denominator. The same lane now
     reports manifest `mapped = 613`, manifest `phpBehaviorTests = 402`,
     lane-status `phpPass = 418`, and dashboard `356 pass` with an
     `inventory` denominator.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/dolt/lane-status.json:4`, `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`, `porting.html:56` through
     `porting.html:67`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not
     enough; `goal.md:37` says upstream tests are the source of truth where
     possible; `goal.md:40` requires hard gaps to be blockers or future
     slices.
   - Evidence: lanes continue to claim 98-99 percent while full Difftastic
     Cargo, Gitoxide Cargo workspace, Pandoc Haskell, Syncthing `go test
     ./...`, broad Dolt Go/BATS, broad rclone provider/mount parity, release
     extra esbuild `make test-all`, and a serialized no-argument root PHP
     result from the accepted snapshot remain unexecuted or explicitly
     pending.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:5`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `dependency-backlog.json:147`,
     `dependency-backlog.json:163`, `dependency-backlog.json:359`,
     `dependency-backlog.json:381`, `dependency-backlog.json:451`,
     `dependency-backlog.json:486`, `dependency-backlog.json:530`,
     `porting.html:75`, `porting-summary.json:215`.
   - Goal requirement at risk: support libraries need the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec coverage as can honestly run.
   - Evidence: all 32 dependency rows are still `candidate` or `deferred`;
     none is an active support-library manifest with PHP pass/fail evidence.
     Rich-function gaps remain for WebDAV, URL percent encoding, JSON/JSON5,
     source maps, browser target data, JS package resolution, sequence
     diff/merge, checksums, SQL expression semantics, ZIP/package containers,
     XML/HTML, Unicode, charset, and archive/compression. The dashboard still
     publishes only 22 older rows.

8. **High - dependency-adjacent work is still being counted inside lanes
   rather than bounded shared support ports.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:5`,
     `lanes/libsqlite/lane-status.json:5`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:359`, `dependency-backlog.json:381`,
     `dependency-backlog.json:530`.
   - Goal requirement at risk: optional-library work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators before it can count as support-library progress.
   - Evidence: rclone carries lane-local WebDAV/x/net behavior, esbuild
     carries lane-local Source Map v3 and JS/runtime-adjacent parsing, and
     libsqlite carries JSON/JSON5-adjacent semantics. Those may be useful lane
     slices, but they are not shared dependency progress until they have
     separate activation-gated manifests, upstream/spec denominators,
     malformed cases, and PHP pass/fail evidence.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:800`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, shell-outs, external converter/runtime
     execution, and whole applications as native port progress.
   - Evidence: markerPDF has useful native PDF text/filter/font slices, but
     its status still enumerates Streamlit, FastAPI/Uvicorn, multiprocessing,
     chunk_convert shell lifecycle, pdftext, pypdfium/PIL, Torch/Surya/Texify,
     OCRMyPDF, Tesseract, Ghostscript, Pandoc/XeLaTeX, Poetry, workflow, and
     model-runtime surfaces. Those must remain preflight/oracle metadata
     unless a bounded native PHP component owns and tests the behavior.

10. **Medium - shell-out boundaries need continued explicit labeling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/dolt/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:30` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide shell-backed filters/askpass/SSH, markerPDF external
      converters/runtime apps, rclone live providers/auth-proxy/FUSE, and
      Dolt server/client/benchmark boundaries are all still open. Keep
      bounded upstream runners and oracle tooling separate from native parity
      accounting.

## Next Best Intervention

Freeze active writers, status/dashboard publishers, focused lane harnesses,
root loops, Dolt BATS shards, and broad upstream runners. Require two stable
polls of `HEAD`, tracked dirty rows, untracked-inclusive status rows,
shortstat, exact PHP process gate, dependency/dashboard counts, and relevant
log mtimes. Accept or reject one lane batch at a time, normalizing
manifest/status/dashboard numeric fields before publication. Split optional
dependency work into manifest-backed bounded support-library ports only behind
real base-lane gates. Run focused verification plus `git diff --check`;
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit; then run one serialized no-argument `php tools/run-tests.php`
only if the exact process gate stays empty on that frozen snapshot.
