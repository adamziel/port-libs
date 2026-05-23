# Independent Audit - 2026-05-23T02:30:15Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed to check status drift, bridge/shell-out usage in PHP files,
and recent Git history through observed `HEAD` movement to `3cdb7ec`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. The only intended writes from this pass are this audit and the
audit-status/next-intervention text in `progress.md`.

## Findings

1. **Critical - the tree is still a moving dirty aggregate, and the root test result is currently red/unstable.**
   - Paths: tracked dirty files include lane implementation and test files under
     `lanes/difftastic`, `lanes/dolt`, `lanes/esbuild`, `lanes/gitoxide`,
     `lanes/lightningcss`, `lanes/markerpdf`, `lanes/quadrable`, `lanes/rclone`,
     `lanes/readability`, and `lanes/syncthing`, plus `progress.md`,
     `porting.html`, `porting-summary.json`, scripts, and status files.
   - Evidence: `HEAD` advanced during this audit from initially observed
     `6e8d154` through `c7db622`, `76a5dd9`, `2bf98a9`, `0bc5002`, `a0a4694`,
     `7c7ab2f`, `9434234`, `4aae69d`, and `3cdb7ec`. The latest sample reports
     `309` `git status --short` entries, `40` tracked modified entries, and
     `git diff --shortstat` reports `40 files changed, 7657 insertions(+), 341
     deletions(-)`. The required root command was not stable across runs: one
     exact run exited `1` with `174` files, `16246` assertions, and `4`
     failures; later reruns were green with different assertion totals; the final
     post-edit run exited `1` with `174` files, `16316` assertions, and `1`
     failure in Difftastic.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices with
     passing tests, `goal.md:48` requires verification and cleanup before
     assigning the next slice, and `goal.md:49` requires repo-wide checks to be
     recorded honestly.
   - Audit judgment: treat all green samples from this audit as diagnostic
     evidence only. Freeze or explicitly coordinate active writers, repair or
     reject the current Difftastic failure, then accept/reject one lane batch at a
     time from a stable root snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and do not satisfy the dashboard requirement.**
   - Paths: `porting.html:30`-`64`, `porting-summary.json`.
   - Evidence: `porting.html` still reports `Average progress: 14.3%` and
     `Generated: 2026-05-22 15:40:20 UTC`. Current manifests now report much
     different mapped counts: Difftastic `133` vs dashboard `15`; Dolt `189` vs
     `5`; Esbuild `146` vs `16`; Gitoxide `1340` vs `737`; libsqlite `122` vs
     `18`; LightningCSS `696 / 3532` vs `78 / 312`; markerPDF `78 / 78` vs
     `11 / 27`; Pandoc `382 / 2028` vs `19 / 1979`; Quadrable `55 / 55` vs
     `24 / 55`; rclone `261` vs `20`; Readability `883` vs `89`; Syncthing
     `198 / 658` vs `27 / 264`.
   - Goal requirement at risk: `goal.md:3` requires current tracking by upstream
     denominator, mapped tests, PHP pass/fail, audit, blocker, and latest commit;
     `goal.md:45` requires `porting.html` to show those fields.
   - Audit judgment: do not publish or rely on the dashboard until it is
     regenerated from the same stable, accepted snapshot used for root
     verification.

3. **High - `progress.md` still carries stale active-lane estimates and next tasks.**
   - Paths: `progress.md:31`-`42`, `lanes/*/lane-status.json`.
   - Evidence: the Active Lanes table still shows Gitoxide `66%`, LightningCSS
     `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, rclone `9%`, Dolt
     `5%`, and esbuild `8%`, while current manifests/status files have moved
     substantially and several lanes report pending or uncommitted current
     batches.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include
     current active lanes, blockers, owner/session, next task per lane, and
     percentage estimates.
   - Audit judgment: update the Active Lanes table only after the supervisor
     freezes the tree and decides which dirty lane batches are accepted.

4. **High - upstream denominator units remain mixed, so mapped percentages can mislead.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`-`21`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Evidence: Difftastic counts "inspected upstream behavior artifacts"; Dolt
     mixes executable files, BATS cases, Go tests, benchmarks, and fixtures;
     Gitoxide mixes full-tree files with targeted static inventories and bounded
     runner evidence; LightningCSS counts helper-invocation behavior checks;
     markerPDF reports `78 / 78` over repository/source paths while also saying
     `0` committed Python unit test files and only `2` actual CI benchmark
     PDF/reference pairs exist; Pandoc counts files/artifacts; rclone counts Go
     test files while excluding live providers, mount/FUSE, Docker serve, and
     `fstest/test_all`; Syncthing counts Go test/benchmark entry points plus
     focused behavior paths.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator, `goal.md:35`-`38` require meaningful upstream-grounded parity,
     and `goal.md:45` requires dashboard denominator/mapped-test fields that can
     be interpreted consistently.
   - Audit judgment: normalize manifests into separate machine fields for
     upstream files/artifacts, executable upstream tests, upstream behavior cases,
     mapped behavior cases, native PHP tests/assertions, failures, runner parity
     class, and static/bounded/full evidence.

5. **High - latest-commit and audit/blocker fields are not machine-checkable enough.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Evidence: latest commit/status values include prose or pending states such
     as "not committed", "uncommitted ... slice", "pending lane-local commit",
     "current lane commit", "pending-current-batch", and "final SHA reported by
     worker". Several status files also cite root-test results from different
     file/assertion totals.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current,
     trustworthy audit/blocker/commit fields.
   - Audit judgment: split accepted commit SHA, dirty batch label, root-test
     result, and human status prose into separate fields before regenerating the
     dashboard.

6. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`.
   - Evidence: the manifest explicitly says full conversion still depends on
     pdftext/pypdfium, Surya, Texify, tabled, Torch/model boundaries, and supplied
     dictionaries/callbacks; the full upstream benchmark runner remains
     unexecuted. The two real CI PDF/reference pairs are valuable, but the
     `78 / 78` mapped denominator is repository-path based rather than a real
     document benchmark denominator.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, generated fixtures, or upstream boundary output as
     native implementation progress; `goal.md:35` requires meaningful fixture
     parity.
   - Audit judgment: keep markerPDF progress conservative until native
     document-level extraction expands against actual benchmark pairs rather than
     supplied model outputs.

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

## Test Runs

Required command: `php tools/run-tests.php`

Exact results from this audit:

```text
first exact run exit status: 1
174 test files, 16246 assertions, 4 failures

failure-extraction rerun summary:
174 test files, 16250 assertions, 0 failures

captured green run exit status: 0
174 test files, 16262 assertions, 0 failures

final post-edit captured run exit status: 1
174 test files, 16316 assertions, 1 failures
FAIL wordpress large single-line asset manifest display stays bounded (lanes/difftastic/tests/TokenDifferTest.php)
Large single-line asset manifests should wrap over multiple display rows.
```

The first red run's individual failure lines were not preserved before output
truncation, and the later failure-extraction rerun did not reproduce them. The
final run is still better than the first exact run from this audit, but it is
red. The changing assertion totals plus `HEAD` movement mean this is an unstable
moving aggregate, not a stable accepted baseline.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. First repair or reject the
Difftastic long-line display batch that currently leaves `php tools/run-tests.php`
red. Then accept or reject dirty lane batches one lane at a time, starting with
files whose status says pending/uncommitted. After each accepted batch, rerun
focused lane tests and `php tools/run-tests.php`, commit that lane slice, then
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from the same accepted snapshot with normalized
denominator/parity/status fields.
