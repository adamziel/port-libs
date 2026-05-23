# Independent Audit - 2026-05-23T02:49:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status
files needed to check status drift, bridge/shell-out usage in PHP files, and
recent Git history through observed movement from `HEAD` `db58be0` to
`e734cd5` before this audit commit.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. The only intended writes from this pass are this audit and the
audit-status/next-intervention text in `progress.md`.

## Findings

1. **Critical - the root PHP suite is red and still moving under a dirty aggregate.**
   - Paths: `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`,
     `lanes/quadrable/tests/QuadbStoreTest.php`, `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`, `progress.md:237`.
   - Evidence: the first exact `php tools/run-tests.php` run in this pass exited
     `1` with `174` test files, `16386` assertions, and `7` failures. A captured
     rerun exited `1` with `174` test files, `16432` assertions, and `6`
     failures: four esbuild async-generator TypeScript lowerer expectation
     failures and two Quadrable `QuadbStore` failures caused by missing
     `encodePrunedProjectedNode()`. The current sample also shows `362`
     `git status --short` entries, `77` tracked modified files, and
     `git diff --shortstat` reports `77 files changed, 10100 insertions(+), 295
     deletions(-)`. During finalization, `HEAD` advanced through `0f66687` and
     `e734cd5` markerPDF commits before this audit commit; no post-`e734cd5`
     root rerun was performed, so the captured result remains an audit sample,
     not a final baseline.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices with
     passing tests; `goal.md:48` requires verification before integration; and
     `goal.md:49` requires repo-wide failures to be recorded honestly.
   - Audit judgment: no lane batch should be treated as accepted until active
     writers are frozen or explicitly coordinated and the esbuild/quadrable
     failures are repaired or the batches rejected.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and no longer represent the manifests.**
   - Paths: `porting.html:30`-`64`, `porting-summary.json`.
   - Evidence: `porting.html` still reports `Average progress: 14.3%` and
     `Generated: 2026-05-22 15:40:20 UTC`. Its rows still show old values such
     as esbuild `16 / 2567` mapped, Gitoxide `737 / 2877`, LightningCSS
     `78 / 312`, markerPDF `11 / 27`, Pandoc `19 / 1979`, Quadrable `24 / 55`,
     rclone `20 / 327`, Readability `89 / 1984`, and Syncthing `27 / 264`.
     Current manifests/status files have moved materially: esbuild maps `146`,
     Gitoxide `1339`, LightningCSS `703 / 3532`, markerPDF `78`, Pandoc `388`,
     Quadrable status claims `104` PHP behavior tests, rclone `265`,
     Readability `101`, and Syncthing `200`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     tracking by upstream denominator, mapped tests, PHP pass/fail, phase, audit,
     current work, blocker, and latest commit.
   - Audit judgment: do not publish or rely on the dashboard until it is
     regenerated from the same stable snapshot that passes or intentionally
     records the root test result.

3. **High - lane status files contain contradictory root-test and commit claims.**
   - Paths: `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`.
   - Evidence: esbuild says the required root run passes with `174` files,
     `16300` assertions, and `0` failures, but the current captured run fails in
     esbuild tests. Quadrable says the required repo-wide run passes with `174`
     files, `16311` assertions, and `0` failures, but the current captured run
     fails in Quadrable. Syncthing says the latest commit was blocked by an
     unrelated difftastic failure while its own audit text names esbuild and
     quadrable failures. Gitoxide latest-commit fields are prose/uncommitted
     batch labels rather than an accepted SHA.
   - Goal requirement at risk: `goal.md:3`, `goal.md:31`, and `goal.md:45`
     require trustworthy audit, blocker, and latest-commit fields.
   - Audit judgment: split lane status into machine fields for accepted commit
     SHA, dirty batch label, lane-local test result, root-test result, blocker,
     and prose notes before dashboard regeneration.

4. **High - Gitoxide is counting shell-backed external merge-driver execution as port progress.**
   - Paths: `lanes/gitoxide/src/ExternalMergeDriverCommand.php:24`-`28`,
     `lanes/gitoxide/src/ExternalMergeDriverCommand.php:59`-`75`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:180`-`186`,
     `lanes/gitoxide/lane-status.json:5`-`13`.
   - Evidence: `ExternalMergeDriverCommand::run()` defaults to
     `runShellCommand()`, which calls `proc_open()`. The manifest and lane status
     count "external merge-driver execution/readback" and the WordPress scenario
     as native progress, even though `nativeImplementation.shellOutsAllowedForProgress`
     is `false`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, generated fixtures, or shell-outs as progress unless
     they are temporary oracle tooling.
   - Audit judgment: keep only injected-runner/readback preparation as native
     progress, or explicitly mark the shell-backed execution path as non-progress
     integration behavior. The current default `proc_open()` path should not
     improve mapped progress or dashboard percentages.

5. **High - upstream denominator units remain mixed and inflate mapped-percent meaning.**
   - Paths: `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`15`.
   - Evidence: LightningCSS counts helper invocations and behavior checks;
     markerPDF reports `78 / 78` over repository/source paths while also saying
     there are `0` committed Python unit test files and only `2` actual CI
     benchmark PDF/reference pairs; Pandoc counts files/artifacts, not executable
     test cases; Dolt mixes executable files, BATS cases, Go functions,
     benchmarks, and fixtures; Difftastic counts inspected behavior artifacts,
     sample pairs, parser corpus files, and source paths.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:35`-`38` require meaningful upstream-grounded parity;
     and `goal.md:45` requires dashboard denominator/mapped fields that can be
     interpreted consistently.
   - Audit judgment: normalize manifests into separate fields for upstream files,
     executable upstream tests, behavior cases, mapped behavior cases, native PHP
     tests/assertions/failures, runner parity class, and static/bounded/full
     evidence.

6. **Medium - markerPDF still risks treating supplied model-boundary scaffolding as extraction parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:470`-`496`,
     `lanes/markerpdf/lane-status.json:10`-`14`.
   - Evidence: the manifest counts supplied converter, model-pipeline planning,
     debug render planning, table/image/OCR handoff, and benchmark-callback
     boundaries, while the full upstream benchmark runner remains unexecuted and
     the defensible real-document denominator is still only two CI
     PDF/reference pairs plus surrogate examples.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:35`
     require native implementation progress and meaningful fixture parity, not
     supplied model-output or callback boundary breadth.
   - Audit judgment: keep markerPDF progress conservative until native
     document-level extraction expands against actual benchmark pairs and is not
     primarily supplied dictionaries/callback output.

7. **Medium - `progress.md` Active Lanes and next intervention are stale.**
   - Paths: `progress.md:31`-`42`, `progress.md:237`-`242`.
   - Evidence: the Active Lanes table still lists old phases and estimates such
     as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, Quadrable `8%`,
     rclone `9%`, Dolt `5%`, and esbuild `8%`. The current owner/status text
     still says the latest root blocker is Pandoc raw-TeX, but the current
     captured root failures are esbuild and Quadrable.
   - Goal requirement at risk: `goal.md:44` requires current active lanes,
     blockers, owner/session, next task per lane, and percentage estimates.
   - Audit judgment: update the human roadmap only after deciding which dirty
     lane batches are accepted; meanwhile the next intervention should name the
     current esbuild/quadrable blockers and Gitoxide shell-out demotion.

## Bridge / Shell-Out Check

Command:

```text
rg -n -e 'shell_exec' -e '\bexec\(' -e 'proc_open' -e 'passthru' -e 'system\(' -e 'popen\(' -e 'Symfony\\Component\\Process' -e 'new Process' -e 'Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
lanes/gitoxide/src/ExternalMergeDriverCommand.php:62:        $process = proc_open(
```

The dashboard Git metadata shell-out is coordination tooling. The Gitoxide
`proc_open()` path is lane implementation code and must not be counted as native
port progress under this audit's constraints.

## Test Runs

Required command: `php tools/run-tests.php`

Exact results from this audit:

```text
first exact run exit status: 1
174 test files, 16386 assertions, 7 failures
visible failures included Quadrable missing parsePrunedProofProjection() and encodePrunedProjectedNode() calls; terminal output was truncated before all failure names were preserved.

captured rerun exit status: 1
FAIL erases upstream object async generator method await using types (lanes/esbuild/tests/TypeScriptModuleLowererTest.php)
String does not contain 'foo = {async *bar(queue) {'
FAIL lowers upstream object async generator method await using cleanup (lanes/esbuild/tests/TypeScriptModuleLowererTest.php)
String does not contain 'foo = {async *bar(queue) {'
FAIL lowers wordpress async generator function asset queue runtime without node (lanes/esbuild/tests/TypeScriptModuleLowererTest.php)
String does not contain 'yield { handle: asset.handle, url: asset.url };'
FAIL lowers wordpress object async generator asset queue cleanup without node (lanes/esbuild/tests/TypeScriptModuleLowererTest.php)
String does not contain 'export const previewAssets = {async *assets(queue) {'
FAIL native quadb store retains mergeProof import garbage until quadb gc (lanes/quadrable/tests/QuadbStoreTest.php)
Call to undefined method PortLibs\Quadrable\QuadbStore::encodePrunedProjectedNode()
FAIL native quadb store matches upstream LMDB cursor oracle for binary proof heads (lanes/quadrable/tests/QuadbStoreTest.php)
Call to undefined method PortLibs\Quadrable\QuadbStore::encodePrunedProjectedNode()
174 test files, 16432 assertions, 6 failures
```

The captured rerun is fewer failures than the first exact run, but the changing
assertion totals and failure set confirm that the aggregate is still moving.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. First repair or reject the
esbuild async-generator TypeScript lowering batch and the Quadrable proof-head
LMDB/cursor batch that currently leave `php tools/run-tests.php` red. In
parallel, demote the Gitoxide shell-backed external merge-driver execution from
native progress or remove the default shell execution path. Then accept or reject
dirty lane batches one lane at a time, rerunning focused lane tests and
`php tools/run-tests.php` after each accepted batch, and regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same stable snapshot.
