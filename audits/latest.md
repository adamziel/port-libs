# Independent Audit - 2026-05-23T02:55:57Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status
files needed to check status drift, bridge/shell-out usage in PHP files, tmux
session presence without launching anything, and recent Git history/reflog.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. The only intended writes from this pass are this audit and the
audit-status/next-intervention text in `progress.md`.

## Findings

1. **Critical - the public dashboard is stale and materially understates or misstates current lane data.**
   - Paths: `porting.html:30`-`64`, `porting-summary.json:2`-`208`,
     `lanes/dolt/lane-status.json:4`-`13`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/libsqlite/lane-status.json:4`-`13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Evidence: `porting.html` and `porting-summary.json` still show
     `Generated: 2026-05-22 15:40:20 UTC` and `Average progress: 14.3%`.
     Rows still show old values such as Dolt `5 / 613` mapped, Gitoxide
     `737 / 2877`, libsqlite `18 / 1454`, Pandoc `19 / 1979`, Quadrable
     `24 / 55`, rclone `20 / 327`, and Syncthing `27 / 264`. Current manifests
     or lane-status files have moved materially: Dolt status claims 168 PHP
     behavior tests, Gitoxide manifest maps 1345, libsqlite status claims 127,
     Pandoc manifest maps 397, Quadrable status claims 104, rclone status claims
     265, and Syncthing manifest maps 200.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard tracking by upstream denominator, mapped tests, PHP pass/fail,
     phase, audit, current work, blocker, and latest commit.
   - Audit judgment: do not publish or rely on the dashboard until it is
     regenerated from the same accepted snapshot as `progress.md`,
     `porting-summary.json`, manifests, and lane statuses.

2. **Critical - the repo remains a moving dirty aggregate, not an accepted integration checkpoint, even though the latest root PHP sample is green.**
   - Paths: worktree-wide; `progress.md:27`-`42`, `progress.md:235`-`243`.
   - Evidence: recent reflog moved during this audit from `3da0f5a` through
     `cc9f512`, amended to `25a8ba6`, then `39faae0`, `b6e4b77`, and current
     `HEAD` `9fe7e44`. Current status reports `361` `git status --short`
     entries, `54` tracked modified entries, and `git diff --shortstat` reports
     `54 files changed, 9249 insertions(+), 400 deletions(-)`. `tmux ls`
     reports `258` existing sessions. The exact required root run in this audit
     passes, but it is a moving-worktree sample.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verification before integration, and
     honest repo-wide failure/status recording.
   - Audit judgment: the green root sample removes the immediate red-test
     blocker, but it does not make the aggregate acceptable. Freeze or explicitly
     coordinate writers before accepting, committing, or publishing more lane
     batches.

3. **High - Gitoxide still counts shell-backed external merge-driver execution as native progress.**
   - Paths: `lanes/gitoxide/src/ExternalMergeDriverCommand.php:24`-`28`,
     `lanes/gitoxide/src/ExternalMergeDriverCommand.php:59`-`75`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`20`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:187`-`190`,
     `lanes/gitoxide/lane-status.json:5`-`13`.
   - Evidence: `ExternalMergeDriverCommand::run()` defaults to
     `runShellCommand()`, and `runShellCommand()` invokes `proc_open()`. The
     manifest and lane status count external merge-driver readback/execution in
     mapped progress even though `nativeImplementation.shellOutsAllowedForProgress`
     is `false`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, generated fixtures, or shell-outs to upstream
     binaries as native implementation progress unless they are temporary oracle
     tooling.
   - Audit judgment: keep injected-runner/readback preparation as native
     behavior if desired, but demote the default `proc_open()` execution path
     from native-progress accounting or remove the default shell runner.

4. **High - lane status files carry contradictory root-test and latest-commit claims.**
   - Paths: `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     `porting-summary.json:16`-`20`, `porting-summary.json:101`-`105`,
     `porting-summary.json:186`-`207`.
   - Evidence: current `php tools/run-tests.php` passes with `176` files and
     `16637` assertions, but Gitoxide status still says final root verification
     exited `1` with `83` failures, LightningCSS says the current root harness is
     red in Pandoc, and Readability/Syncthing still say commits are blocked by
     esbuild/quadrable failures. Several latest-commit fields remain prose or
     pending labels instead of accepted SHAs.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and `goal.md:45`
     require trustworthy audit, blocker, owner/session, next task, percentage,
     and latest-commit fields.
   - Audit judgment: normalize lane status into separate machine fields for
     accepted commit SHA, dirty batch label, lane-local test result, root-test
     result, blocker, and prose notes before regenerating the dashboard.

5. **High - upstream denominator units are still mixed, so mapped percentages are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Evidence: Difftastic counts behavior artifacts, golden pairs, fixture
     pairs, and corpus files; Dolt mixes executable files, BATS cases, Go test
     functions, benchmarks, and fixtures; LightningCSS counts helper invocations
     as behavior checks; markerPDF reports `78` tracked upstream paths, `0`
     committed Python unit tests, `2` actual benchmark PDF/reference pairs, and
     `mapped: 80`; Pandoc counts files/artifacts, not executable Tasty cases;
     Syncthing counts Go test/benchmark entry points while also referring to a
     separate 264-path inventory.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`-`38`, and
     `goal.md:45` require real upstream denominators, meaningful fixture parity,
     source-of-truth upstream tests, and interpretable dashboard mapped fields.
   - Audit judgment: split denominator schema into upstream files, executable
     upstream tests, behavior cases, mapped behavior cases, native PHP tests,
     native assertions, native failures, runner parity class, and static/bounded/full
     evidence. Until then, percentages should be treated as qualitative labels.

6. **Medium - `progress.md` still carries stale active-lane estimates and task text.**
   - Paths: `progress.md:27`-`42`.
   - Evidence: the Active Lanes table still lists stopped sessions and old
     estimates such as Gitoxide `66%`, LightningCSS `14%`, libsqlite `12%`,
     Quadrable `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`, while current
     lane-status files claim much higher estimates and different current work.
   - Goal requirement at risk: `goal.md:44` requires current active lanes,
     blockers, owner/session, next task per lane, and percentage estimates.
   - Audit judgment: update the table only from a coordinated accepted snapshot;
     this audit updates the current status/next intervention but leaves the table
     as a known stale coordination surface.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|\bexec\(|passthru|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
lanes/gitoxide/src/ExternalMergeDriverCommand.php:62:        $process = proc_open(
```

The dashboard Git metadata shell-out is coordination tooling. The Gitoxide
`proc_open()` path is lane implementation code and must not be counted as native
port progress under this audit's constraints.

## Test Run

Required command: `php tools/run-tests.php`

Final exact result from this audit:

```text
exit status: 0
176 test files, 16637 assertions, 0 failures
```

Because `HEAD` and the dirty worktree moved during the audit, this is a green
moving-worktree sample, not an accepted integration checkpoint.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers and preserve ownership of the
dirty lane batches. First demote or remove Gitoxide's shell-backed external
merge-driver execution from native-progress accounting. Then accept or reject
dirty lane batches one lane at a time, rerun focused lane tests and
`php tools/run-tests.php` after each accepted batch, and regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
one stable green snapshot with normalized denominator, mapped-case,
native-test, runner-parity, blocker, and latest-commit fields.
