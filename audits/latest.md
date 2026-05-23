# Independent Audit - 2026-05-23T04:32:13Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
`lanes/*/lane-status.json` files needed to verify status drift, PHP shell-out
usage in `lanes/`, `tools/`, and `scripts/`, and recent Git history observed
through `03140c94`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
or count bridge/generated/oracle tooling as native implementation progress.

## Findings

1. **High - the dashboard and progress surfaces are not current enough to be a coordination source of truth.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:113`-`119`,
     `porting-summary.json:164`-`170`, `porting-summary.json:198`-`204`,
     `progress.md:31`-`42`, `progress.md:243`-`249`.
   - Requirement at risk: `goal.md:3`, `goal.md:44`, `goal.md:45`, and
     `goal.md:49` require current coordination, visible per-lane dashboard
     status, and honest repo-wide test recording.
   - Evidence: `porting.html` and `porting-summary.json` still publish a
     `2026-05-23 03:09:50 UTC` snapshot from `3f4ea3cda693`, while recent
     history advanced through `03140c94`. Several dashboard counts are now
     stale relative to current manifests: difftastic publishes `139 / 417`
     while the manifest records `mapped: 160`; markerPDF publishes `81 / 78`
     while the manifest records `mapped: 157`; libsqlite publishes
     `129 / 1454` while the manifest records `mapped: 149`; rclone publishes
     `265 / 327` while the manifest records `mapped: 288`; Readability
     publishes `907 / 1984` while the manifest records `mapped: 1031`;
     Syncthing publishes `204 / 658` while the manifest records `mapped: 232`;
     Gitoxide publishes `1358 / 2877` while the manifest records
     `mapped: 1432`.
   - Audit judgment: do not publish or use the dashboard as the current
     portfolio status until it is regenerated from one accepted snapshot.

2. **High - the green root harness is a moving dirty-tree sample, not an accepted integration checkpoint.**
   - Paths: worktree-wide; representative dirty implementation/status paths
     include `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/src/ConstraintViolationsTable.php`,
     `lanes/readability/src/ArticleExtractor.php`,
     `lanes/syncthing/src/PullItemUpdater.php`, `porting.html`, and
     `porting-summary.json`.
   - Requirement at risk: `goal.md:29`, `goal.md:31`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, verified integration, and
     recorded repo-wide tests.
   - Evidence: while this audit was running, HEAD advanced from the prior audit
     area through `8fe785a4`, `ef1b09ef`, `b1282ff7`, `e8b5bf63`, `7307c161`,
     `08ca6341`, `e7cd718e`, `0dfdae86`, `35b56921`, `b555e29b`,
     `eaada69c`, and `03140c94`. The latest status sample shows `490` `git status --short`
     entries, including `44` modified tracked entries and `446` untracked entries;
     `git diff --shortstat` reports
     `44 files changed, 10760 insertions(+), 396 deletions(-)`.
   - Audit judgment: the root test result below is useful smoke evidence, but
     it should not be treated as a release/integration checkpoint until the
     supervisor freezes writers and accepts/rejects dirty lane batches.

3. **High - manifest denominator units are still mixed, so progress percentages are not machine-checkable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream benchmark denominators, mapped upstream
     tests, explicit slices for huge suites, and separate dashboard columns.
   - Evidence: Gitoxide uses upstream file count as `total` while mapped
     progress is behavior slices and bounded runner probes. Difftastic mixes
     Rust test attributes, golden pairs, numbered fixtures, parser corpora, and
     targeted source files in one prose denominator. Dolt mixes test files,
     BATS cases, Go test functions, benchmark functions, fixture paths, and
     reference matches. markerPDF reports path inventory plus benchmark pairs,
     surrogate pairs, and supplied-document excerpts, then maps more units than
     the dashboard denominator. Quadrable reports `55 tracked upstream paths`
     plus `34` scenarios and assertion counts, but the dashboard reduces that
     to `55 / 55`.
   - Audit judgment: normalize manifests into typed numeric fields before
     percentages are used for planning or publication.

4. **Medium - stale red blocker/root-test prose remains in lane metadata after the current green run.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/dolt/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/syncthing/lane-status.json`, `progress.md:243`-`249`.
   - Requirement at risk: `goal.md:39`, `goal.md:44`, and `goal.md:49` require
     precise blockers and honest repo-wide test recording.
   - Evidence: Gitoxide's manifest still says required root verification is
     red with 7 LightningCSS/Quadrable failures. Dolt, Gitoxide, and Syncthing
     lane statuses still contain red-root blocker prose, while the required
     command in this audit exits `0` with `183 test files, 18198 assertions,
     0 failures`.
   - Audit judgment: status files need a single-source root-test stamp instead
     of stale lane-local prose copied forward across unrelated commits.

5. **Medium - bounded/static upstream evidence is still easy to overread as full parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`-`20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:35`-`40` require upstream tests as the
     source of truth and hard features to be marked as blockers or future
     slices.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, and Syncthing still do
     not have full upstream runner parity. rclone and Dolt have useful bounded
     runner evidence but still exclude provider/mount/live-service, full BATS,
     full Go, or broader integration coverage. The dashboard collapses these
     evidence classes into progress percentages.
   - Audit judgment: make evidence class (`full-runner`, `bounded-runner`,
     `static-inventory`, `oracle-fixture`) first-class in manifests, status
     files, and the dashboard.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only PHP shell-out match is dashboard
coordination tooling.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Exact result for this audit:

```text
exit status: 0
183 test files, 18198 assertions, 0 failures
```

This is a green dirty-worktree sample, not an accepted integration checkpoint,
because the tree was moving during the audit and still has 490 status entries
in the latest sample.

## Recommended Next Intervention

Freeze active writers. Accept or reject dirty lane batches one lane at a time,
remove stale red-root blocker prose, stamp accepted SHAs and a single root-test
result, normalize manifest denominator/evidence fields, then regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from that same green
snapshot.
