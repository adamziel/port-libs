# Independent Audit - 2026-05-23T05:24:07Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
recent lane status/process state needed to verify coordination drift, PHP
shell-out usage in `lanes/`, `tools`, and `scripts`, and recent Git history
through `ee95f909bc6d`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. I treated bridge/generated/oracle tooling as non-progress unless it was
explicitly marked as temporary fixture or oracle evidence.

## Findings

1. **High - the latest required root harness is red after new lane commits.**
   - Paths: `tools/run-tests.php`,
     `lanes/readability/tests/ArticleExtractorTest.php`, and the current dirty
     worktree aggregate.
   - Requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:48`, and
     `goal.md:49` require reviewable slices, meaningful fixture parity,
     supervisor verification, and honest repo-wide test recording.
   - Evidence: after `HEAD` advanced from `af8cb1a54746` through `8d6e1121`,
     `845832f2`, and `ee95f909`, the latest completed
     `php tools/run-tests.php` exited `1` with `183` test files, `18899`
     assertions, and `6` failures. The failures are in Readability fixture
     parity; visible root-run failures included the Mozilla `wapo-1`/`wapo-2`
     Washington Post fixtures, `lazy-image-1`, and the readability-page wrapper
     serialization case. A focused
     `lanes/readability/tests/ArticleExtractorTest.php` rerun then reported
     `1094` assertions and `2` failures, reproducing the `wapo-1` and `wapo-2`
     drift.
   - Audit judgment: the current aggregate is red. The supervisor should treat
     the Readability batch as the first integration blocker before accepting
     further status/dashboard stamps.

2. **High - the coordination surface says capped/stopped, but the repository is under active multi-agent writes.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `.tmux-team/tmp/*`, `.tmux-team/prompts/*`, and the dirty
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Requirement at risk: `goal.md:20`, `goal.md:44`, `goal.md:48`, and
     `goal.md:49` require a practical concurrency cap, current owner/session
     state, deliberate integration, and honest repo-wide test recording.
   - Evidence: this audit sampled many active `scripts/run-tmux-agent.sh`
     sessions, including `port-dolt`, `port-rclone`, `port-syncthing`,
     `port-lightningcss`, `port-pandoc`, `port-libsqlite`, `port-readability`,
     `port-quadrable`, `port-markerpdf`, `port-esbuild`, `port-difftastic`,
     `port-gitoxide`, `port-auditor`, `port-integrator`, and
     `port-clean-head-verifier-*`.
   - Evidence of live mutation: while the audit was reading manifests and
     running tests, Dolt, LightningCSS, and Syncthing manifests changed line
     counts; LightningCSS mapped count changed from `797` to `799`; `HEAD`
     advanced by three commits; and the root harness moved from an earlier
     green same-audit sample (`183` files, `18888` assertions, `0` failures) to
     the current red sample above.
   - Audit judgment: the active writer set invalidates claims that the Active
     Lanes table is current or that a root run is an accepted integration
     checkpoint.

3. **High - `porting.html` and `porting-summary.json` are stale relative to `HEAD` and current manifests.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`8`, and `porting-summary.json:10`-`213`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     browsable dashboard with upstream denominator, mapped tests, PHP
     pass/fail, blocker, and latest commit per lane.
   - Evidence: the dashboard is a snapshot of `bda83c6b93d4`, while current
     `HEAD` is `ee95f909bc6d`. Current manifest mapped counts disagree with
     the page: difftastic `169` vs dashboard `160`, Dolt `254` vs `242`,
     esbuild `167` vs `164`, Gitoxide `1436` vs `1432`, libsqlite `160` vs
     `149`, LightningCSS `799` vs `773`, markerPDF `163` vs `159`, Pandoc
     `467` vs `426`, rclone `301` vs `291`, Readability `1057` vs `1031`,
     and Syncthing `242` vs `235`.
   - Evidence: the dashboard still compresses required fields by putting
     denominator inside "Benchmark" and PHP pass/fail inside "Mapped" instead
     of rendering separate upstream-denominator, mapped-test, and PHP
     pass/fail columns.
   - Audit judgment: `porting.html` is a publication snapshot, not the current
     portfolio source of truth.

4. **High - the tree remains a broad dirty aggregate, not reviewable accepted slices.**
   - Paths: representative dirty paths include `tools/run-tests.php`,
     `progress.md`, `porting.html`, `porting-summary.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/src/ReferenceStore.php`,
     `lanes/rclone/src/SyncPlan.php`, and
     `lanes/pandoc/src/MarkdownReader.php`.
   - Requirement at risk: `goal.md:29`, `goal.md:36`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, passing tests, deliberate
     integration, and recorded repo-wide verification.
   - Evidence: the latest pre-audit-edit sample reported `623`
     `git status --short` entries, and `git diff --shortstat` reported
     `86 files changed, 13764 insertions(+), 568 deletions(-)`.
   - Audit judgment: red root tests inside this much dirty state are an
     integration blocker, not a lane-local detail.

5. **Medium - manifest denominator units remain mixed, so percentages are not machine-checkable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators, explicit slices for huge
     suites, and dashboard fields that distinguish denominator, mapped tests,
     and PHP pass/fail.
   - Evidence: markerPDF reports `78` upstream repository paths while mapping
     `163` source/dependency/supplied semantics. Quadrable reports `55`
     tracked paths mapped while its runner evidence is 34 upstream scenarios
     plus assertion/check counts. Dolt uses upstream test-file counts while
     also citing thousands of BATS cases and Go functions. Difftastic counts
     inspected artifacts rather than executable cases. Readability uses 1984
     upstream Mocha tests while the native mapped count is local PHP behavior
     coverage.
   - Audit judgment: normalize manifests into typed units such as
     `upstream_test_cases`, `fixture_pairs`, `source_paths`,
     `runner_evidence`, `mapped_behavior_checks`, and `php_assertions` before
     treating percentages as comparable.

6. **Medium - bounded/static upstream evidence is still easy to overread as full parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`-`20`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:206`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:387`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:213`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:380`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:300`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:539`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:301`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:775`.
   - Requirement at risk: `goal.md:35`-`40` require upstream tests as source of
     truth, meaningful fixture parity, edge-case coverage, and explicit
     blockers/future slices for hard features.
   - Evidence: Gitoxide still has no full cargo workspace pass despite a 96%
     dashboard score. Difftastic, markerPDF, Pandoc, and Syncthing remain
     static or bounded evidence rather than full upstream runner parity.
     rclone and Dolt have useful bounded runner evidence, but their full
     provider/mount/full-BATS/full-Go surfaces remain outside those passes.
   - Audit judgment: make evidence class (`full-runner`, `bounded-runner`,
     `static-inventory`, `oracle-fixture`, `supplied-boundary`) first-class in
     manifests, lane statuses, and dashboard rows.

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

Exact latest completed result for this audit:

```text
exit status: 1
183 test files, 18899 assertions, 6 failures
```

Earlier same-audit note: a root run before `HEAD` advanced to `ee95f909`
exited `0` with `183` test files, `18888` assertions, and `0` failures. That
green sample is superseded by the red result above.

## Recommended Next Intervention

Freeze active writers and duplicate root-test processes, then enforce the
documented cap before accepting more work. Fix or revert the Readability
fixture regression that makes the root harness red, rerun the focused
Readability file and one root `php tools/run-tests.php` from a quiesced tree,
then accept or reject dirty lane batches one lane at a time. Only after that
should the supervisor stamp real accepted SHAs, normalize manifest denominator
and evidence units, and regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same accepted green
snapshot.
