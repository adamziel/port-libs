# Independent Audit - 2026-05-24T10:07Z

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
UTC samples: 2026-05-24T10:06:52Z and 2026-05-24T10:07:23Z, plus required input reads
HEAD moved during this audit: d166e056 -> f591ba88
recent history: f591ba88 Record integration hold status; d166e056 Refresh independent audit status; fca48fb8 Record integration hold status; 45ccf1b0 Record integration hold status
tracked dirty rows: 326 -> 326
default status rows including untracked: 16686 -> 16686
git diff --shortstat: 326 files changed, 211454 insertions(+), 27798 deletions(-) -> 326 files changed, 211709 insertions(+), 27775 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json has 32 rows (22 candidate, 10 deferred); dashboard still shows 22 rows
root run by this audit: not started; the exact process gate returned no rows, but HEAD and shortstat moved while this audit was reading required files
```

Required pre-root process-gate evidence:

```text
initial pgrep -af '^php tools/run-tests\.php( |$)':
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:06:52Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T10:07:23Z:
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was clear,
but the checkout was not stable enough for a serialized audit-owned root
result: `HEAD` moved from `d166e056` to `f591ba88`, and shortstat changed from
`326 files changed, 211454 insertions(+), 27798 deletions(-)` to
`326 files changed, 211709 insertions(+), 27775 deletions(-)` between polls.
`jq empty` passed for all 12 root lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status/dashboard count sample:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    982 / 738 / n/a                 3088              735 / 374 / 374
dolt          prose total / 613 / 402         418               inventory / 613 / 356
esbuild       2567 / 408 / 408                408               2567 / 311 / 311
gitoxide      2877 / 2877 / 6934              6934              2877 / 2751 / 5634
libsqlite     1589 / 337 / n/a                337               1589 / 286 / 286
LightningCSS  3535 / 2713 / n/a               3903              3532 / 1732 / 2197
markerPDF     380 / 331 / 467                 468               330 / 280 / 416
pandoc        2276 / 1803 / n/a               346               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   225               55 / 55 / 190
rclone        1601 / 871 / 871                871               1601 / 698 / 698
readability   1984 / 1984 / 248               248               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7357              658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:42`, `audits/integration-status.md:3`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` requires small, reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: `HEAD` moved during this audit from `d166e056` to `f591ba88`.
     The tracked dirty row count stayed broad at 326, while shortstat moved
     from `326 files changed, 211454 insertions(+), 27798 deletions(-)` to
     `326 files changed, 211709 insertions(+), 27775 deletions(-)`. Every lane
     still reports `pending`, `uncommitted`, or lane-local `latestCommit`
     status, for example `lanes/dolt/lane-status.json:13`,
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
   - Evidence: the required exact process gate returned no rows at the initial,
     10:06:52Z, and 10:07:23Z samples, but I did not start
     `php tools/run-tests.php` because the checkout failed the stability gate.
     Lane statuses continue to say aggregate root verification is pending,
     including `lanes/difftastic/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`, and
     `lanes/readability/lane-status.json:12`.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md:3` requires current coordination
     files; `goal.md:45` requires the dashboard to show current per-lane
     denominator, mapped tests, PHP pass/fail, phase, audit, work, blocker,
     and commit.
   - Evidence: the dashboard still claims average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and 22
     dependency rows. Current `HEAD` is `f591ba88`, and
     `dependency-backlog.json` is updated `2026-05-24 09:55:45 UTC` with 32
     rows.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:9`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable upstream denominators, mapped upstream tests, PHP
     pass/fail counts, audit status, and latest commit per lane.
   - Evidence: the count table above shows every lane has at least one
     manifest/status/dashboard mismatch. Several manifests omit a normalized
     PHP pass field, while lane statuses mix behavior-test counts and assertion
     counts. This prevents `porting.html` from being an at-a-glance source of
     truth.

5. **High - Dolt still has a non-machine-checkable denominator and stale PHP
   counts.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2500`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2506`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`,
     `porting.html:57`, `porting-summary.json:28`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     benchmark denominator; `goal.md:29` requires accepted slices to be
     reviewable and passing; `goal.md:45` requires dashboard counts to be
     accurate.
   - Evidence: `benchmarkDenominator.total` is still a long prose OCT evidence
     paragraph rather than a numeric denominator. The same lane reports
     manifest `mapped = 613`, manifest `phpBehaviorTests = 402`, lane-status
     `phpPass = 418`, and dashboard `356 pass` with an `inventory`
     denominator.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/dolt/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`, `porting.html:56` through
     `porting.html:67`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough;
     `goal.md:37` says upstream tests are the source of truth where possible;
     `goal.md:40` requires hard gaps to be blockers or future slices.
   - Evidence: lanes continue to claim 98-99 percent while full Difftastic
     Cargo, Gitoxide Cargo workspace, Pandoc Haskell, Syncthing `go test
     ./...`, broad Dolt Go/BATS, broad rclone provider/mount parity, esbuild
     release-extra `make test-all`, and a serialized no-argument root PHP
     result from the accepted snapshot remain unexecuted or explicitly
     pending.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:5`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `dependency-backlog.json:308`,
     `dependency-backlog.json:333`, `dependency-backlog.json:359`,
     `dependency-backlog.json:381`, `dependency-backlog.json:399`,
     `dependency-backlog.json:416`, `dependency-backlog.json:451`,
     `dependency-backlog.json:486`, `dependency-backlog.json:530`,
     `dependency-backlog.json:551`, `porting.html:75`.
   - Goal requirement at risk: `goal.md:35` requires meaningful fixture parity,
     edge-case coverage, and error behavior; the support-library audit
     requirement requires the same granularity as lanes: bounded native PHP
     component, activation gate, dependency-specific upstream/spec
     denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and as much upstream/spec
     coverage as can honestly run.
   - Evidence: all 32 dependency rows are still `candidate` or `deferred`;
     none is an active support-library manifest with PHP pass/fail evidence.
     Rich-function gaps remain for WebDAV, URL percent encoding, JSON/JSON5,
     source maps, browser target data, JS package resolution, sequence
     diff/merge, checksums, SQL expression semantics, ZIP/package containers,
     XML/HTML, Unicode, charset, and archive/compression. The dashboard still
     publishes only 22 older dependency rows.

8. **High - dependency-adjacent work is still lane-local rather than bounded
   shared support-library progress.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/esbuild/lane-status.json:5`,
     `lanes/libsqlite/lane-status.json:5`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:359`, `dependency-backlog.json:381`,
     `dependency-backlog.json:530`.
   - Goal requirement at risk: optional-library work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators before it can count as support-library progress.
   - Evidence: rclone carries lane-local WebDAV/x/net behavior, esbuild carries
     lane-local Source Map v3 and JS/runtime-adjacent parsing, and libsqlite
     carries JSON/JSON5-adjacent semantics. Those may be useful lane slices,
     but they are not shared dependency progress until separate
     activation-gated manifests, upstream/spec denominators, malformed cases,
     and PHP pass/fail evidence exist.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:802`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, shell-outs, external converter/runtime execution,
     and whole applications as native port progress.
   - Evidence: markerPDF has useful native PDF text/filter/font/outline slices,
     but its status still enumerates Streamlit, FastAPI/Uvicorn,
     multiprocessing, `chunk_convert.sh`, pdftext, pypdfium/PIL,
     Torch/Surya/Texify, OCRMyPDF, Tesseract, Ghostscript, Pandoc/XeLaTeX,
     Poetry, workflow, and model-runtime surfaces. Those must remain
     preflight/oracle metadata unless a bounded native PHP component owns and
     tests the behavior.

10. **Medium - shell-out and external-process boundaries need continued
    explicit labeling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/dolt/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:30` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide shell-backed filters/askpass/SSH, markerPDF external
      converters/runtime apps, rclone live providers/auth-proxy/FUSE, and Dolt
      server/client/benchmark boundaries remain open. Keep bounded upstream
      runners and oracle tooling separate from native parity accounting.

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
