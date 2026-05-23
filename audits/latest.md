# Independent Audit - 2026-05-23T02:07:59Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed to check status drift, bridge/shell-out usage in PHP files,
and recent Git history through observed `HEAD` `5fb8ea2`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. The only intended writes from this pass are this audit and the
audit-status/next-intervention text in `progress.md`.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are not current enough to satisfy the dashboard requirement.**
   - Paths: `porting.html:30`-`64`, `porting-summary.json`.
   - Evidence: `porting.html` still reports `Average progress: 14.3%` and
     `Generated: 2026-05-22 15:40:20 UTC`. `porting-summary.json` has the same
     generated timestamp and stale commit/status values such as `eaf28e1`,
     `e47acb6`, `2e1fcb0`, `a184e4a`, `36031aa`, `uncommi`, `1213c5c`,
     `495e34b`, `01ab0eb`, and `pending`. Current manifests now report much
     broader mapped counts than the dashboard: Difftastic `129 / 415` versus
     dashboard `15 / 404`; Dolt `181 / 613` versus `5 / 613`; Esbuild
     `143 / 2,567` versus `16 / 2,567`; Gitoxide `1306 / 2877` versus
     `737 / 2877`; libsqlite `121 / 1454` versus `18 / 1454`; LightningCSS
     `693 / 3532` versus `78 / 312`; markerPDF `78 / 78` versus `11 / 27`;
     Pandoc `375 / 2028` versus `19 / 1979`; Quadrable `55 / 55` versus
     `24 / 55`; rclone `250 / 327` versus `20 / 327`; Readability
     `878 / 1984` versus `89 / 1984`; Syncthing `195 / 658` versus `27 / 264`.
   - Goal requirement at risk: `goal.md:3` requires durable current tracking,
     and `goal.md:45` requires `porting.html` to show current denominator,
     mapped tests, PHP pass/fail, audit, blocker, and commit fields.
   - Audit judgment: do not publish or rely on the dashboard until it is
     regenerated from a frozen accepted snapshot.

2. **High - the root harness is currently green, but the tree is still a broad unreviewed dirty aggregate.**
   - Paths: the dirty files listed by `git status --short --untracked-files=no`,
     including implementation files under `lanes/*/src`, `lanes/*/tests`,
     manifests/status files, `porting.html`, `porting-summary.json`,
     supervisor scripts, and audit/progress files.
   - Evidence: current `HEAD` advanced during this audit from `fa4c928` through
     `364b6a0`, `556abbf`, `3b8f371`, `eeb84d0`, `8d04998`, and `5fb8ea2`.
     The latest tracked-dirty sample reports `61` modified tracked files and
     the unstaged `git diff --shortstat` reports
     `59 files changed, 8162 insertions(+), 254 deletions(-)`. Full
     `git status --short` currently has `301` entries. The required root harness
     passed on the final rerun before the last observed `HEAD` movement, but
     that only proves the dirty aggregate executed at that point; it does not
     identify which lane batches are accepted or reviewable.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests; `goal.md:48` requires verifying, committing, updating
     progress, and cleaning accidental unrelated changes before assigning the
     next slice; `goal.md:49` requires repo-wide checks to be recorded honestly.
   - Audit judgment: treat this as a green but unaccepted integration snapshot,
     not as completed lane progress.

3. **High - `progress.md` still has stale active-lane estimates and next tasks.**
   - Paths: `progress.md:27`-`42`, `lanes/*/lane-status.json`.
   - Evidence: the Active Lanes table still reports old estimates such as
     Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`,
     Quadrable `8%`, rclone `9%`, Dolt `5%`, and Esbuild `8%`. Current lane
     status files report materially different estimates: Difftastic `46%`,
     Dolt `48%`, Esbuild `45%`, Gitoxide `91%`, libsqlite `65%`,
     LightningCSS `60%`, markerPDF `55%`, Pandoc `61%`, Quadrable `68%`,
     rclone `65%`, Readability `53%`, and Syncthing `63%`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include
     current active lanes, blockers, owner/session, next task per lane, and
     percentage estimates.
   - Audit judgment: refresh the full table only after dirty lane batches are
     accepted or rejected; until then, keep the next intervention focused on
     freezing and integrating the aggregate.

4. **High - lane status root-test and latest-commit fields are not reliable.**
   - Paths: `lanes/*/lane-status.json`, especially `audit`, `blocker`, and
     `latestCommit` fields.
   - Evidence: several lane statuses cite older root totals (`170 / 15703`,
     `170 / 15764`, `171 / 15819`, `171 / 15837`) while the current required
     root run is `171 / 15984 / 0`. Gitoxide and Syncthing status text still
     mentions a later red root run in `lanes/rclone/tests/ListDirectoryTest.php`;
     that is obsolete for the current audit because the latest root run is
     green. `latestCommit` also mixes SHAs with prose or pending values such as
     `current batch uncommitted`, `Port libsqlite lower expression custom
     collation lookup`, `unchanged from prior accepted markerPDF slice...`,
     `pending numeric-head LMDB string-key batch`, and `uncommitted current lane
     batch`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require latest
     commit and PHP pass/fail fields that can be trusted; `goal.md:31` requires
     blockers to be recorded precisely.
   - Audit judgment: stamp root-test state centrally from the accepted snapshot
     and split real accepted commit SHA from status prose.

5. **High - upstream denominator units remain mixed across manifests.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     and `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Evidence: Difftastic counts inspected behavior artifacts; Dolt mixes test
     files, BATS cases, Go tests, benchmarks, and fixtures; Gitoxide counts
     upstream files plus targeted inventories and fixture baselines; LightningCSS
     counts helper-invocation behavior checks; markerPDF reports `78 / 78` over
     tracked repository paths while also saying only `2` actual CI benchmark
     PDF/reference pairs and `0` committed Python unit tests exist; Pandoc
     counts files/artifacts; rclone counts Go test files; Readability counts
     Mocha tests; Syncthing counts Go test/benchmark entry points.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:35`-`38` require meaningful fixture parity and
     upstream-test grounding; `goal.md:45` requires dashboard fields that can be
     interpreted consistently.
   - Audit judgment: normalize the schema into separate fields for upstream
     files/artifacts, executable upstream tests, upstream behavior cases, mapped
     behavior cases, native PHP tests/checks/assertions, failures, runner parity,
     and static/bounded/full evidence class.

6. **Medium - high progress percentages can be mistaken for upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4`-`12`,
     `lanes/markerpdf/lane-status.json:4`-`12`,
     `lanes/pandoc/lane-status.json:4`-`12`,
     `lanes/rclone/lane-status.json:4`-`12`,
     `lanes/syncthing/lane-status.json:4`-`12`.
   - Evidence: Gitoxide reports `91%` without full Cargo workspace parity;
     markerPDF reports `55%` while the full benchmark/model runner is still not
     executed; Pandoc reports `61%` without full Haskell runner parity; rclone
     reports `65%` from a bounded runner excluding live providers, mount/FUSE,
     Docker serve coverage, and `fstest/test_all`; Syncthing reports `63%`
     without full `go test ./...`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough,
     `goal.md:37` says upstream tests are the source of truth, and
     `goal.md:40` requires hard features to stay visible as blockers or future
     slices.
   - Audit judgment: expose a parity class beside every percentage so static
     inventory and bounded runner evidence cannot read as production maturity.

7. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/markerpdf/lane-status.json`.
   - Evidence: the manifest denominator is repository-path based and explicitly
     notes `2` actual CI benchmark PDF/reference pairs and `0` committed Python
     unit test files. Much of the native slice maps supplied `pdftext`,
     `pypdfium`, Surya, Texify, tabled, preview, server, and model-planning
     boundaries. The full upstream benchmark runner remains not executed.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     bridge calls, shell-outs, generated fixtures, or upstream boundary output
     as native implementation progress.
   - Audit judgment: keep markerPDF progress conservative until native
     document-level extraction parity broadens against actual benchmark pairs.

## Bridge / Shell-Out Check

Command:

```text
rg -n -e 'shell_exec' -e '\bexec\(' -e 'proc_open' -e 'passthru' -e 'system\(' -e 'popen\(' -e 'Symfony\\Component\\Process' -e 'new Process' -e 'Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane implementation process-execution bridge calls were found. The remaining
match is dashboard Git metadata tooling and must not be counted as native port
progress.

## Test Run

Required command: `php tools/run-tests.php`

Exact results from this audit:

```text
initial run exit status: 0
171 test files, 15875 assertions, 0 failures

final rerun exit status: 0
171 test files, 15984 assertions, 0 failures
```

The final rerun completed before later observed `HEAD` movement to `5fb8ea2`;
no post-`5fb8ea2` root rerun was performed because the tree continued moving.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers and preserve ownership of
in-flight lane batches. Because the current root harness is green, the next
supervisor action should be to accept or reject the dirty lane batches one lane
at a time, rerunning focused lane tests plus `php tools/run-tests.php` after
each accepted slice. Then regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from that same accepted snapshot;
stamp root-test results and latest accepted SHAs in one place; normalize
dashboard/status fields for upstream denominator units, mapped upstream cases,
native tests/checks/assertions, failures, runner parity, parity class, and
latest accepted commit.
