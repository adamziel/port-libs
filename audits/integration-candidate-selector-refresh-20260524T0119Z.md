# Integration Candidate Selector Refresh 2026-05-24T01:21:54Z

## Inputs Read

- `goal.md`
- `progress.md`
- `audits/evaluator-feedback.md`
- `audits/integration-status.md`
- `audits/publisher-status.md`
- `audits/support-library-activation-scout-refresh-20260524T011508Z.md`
- current `git status --short --branch`
- current `git diff --name-only`
- recent `.tmux-team/logs/port-*.log` lane/control logs
- current PHP, Dolt BATS, Go, Cargo, and SQLite Tcl/testfixture process samples
- all 12 `lanes/*/lane-status.json`
- all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`

This selector did not edit lane files, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, scripts, provider configs,
process environments, remotes, auth files, or secret-bearing inputs. It did
not stage, commit, push, publish, or start root `php tools/run-tests.php`.

## Current Snapshot

- Sample time: `2026-05-24T01:21:54Z`.
- `HEAD`: `e194c05fd23a`.
- Branch: `main...origin/main [ahead 629, behind 68]`.
- The tree moved during this selector pass: earlier samples observed
  `29e48310cada`; the current committed snapshot is `e194c05fd23a`.
- Tracked dirty rows: `293`.
- Total status rows with untracked files: `10631`.
- `git diff --shortstat`: `293 files changed, 138542 insertions(+), 16520 deletions(-)`.
- tmux sessions: `165`.
- Active PHP sample after the dirty-root run completed: focused Syncthing PID
  `1794580` running `php tools/run-tests.php lanes/syncthing/tests/...`.
- Exact no-argument root PHP sample: no active process at the final sample.
- Active upstream/local runners: Dolt BATS run
  `bats-diff-schema-local-runner-20260524T010205Z` remains active in the
  selected 14-file, 369-case local subset. No Cargo or SQLite Tcl/testfixture
  runner was active in the final sample. An earlier same-pass sample observed
  `go test ./vfs -run TestRcPollInterval -count=1` for rclone.
- Latest dirty-root evidence:
  `audits/capacity-feed-dirty-root-1a112c6ebaef-c7b1627db5fb.md` records
  `php tools/run-tests.php` at `29e48310cada`, exit `0`, `323` test files,
  `42831` assertions, and `0` failures. This is not an accepted root gate for
  the current tree because `HEAD`, dirty counts, and focused/runner state moved
  after the gate sample.
- `jq empty dependency-backlog.json porting-summary.json`: exit `0`.

## Acceptance Matrix

| Lane | Dirty rows | Primary active | Newest lane log | Focused PHP evidence visible | Root/aggregate state | Support-library activation risk |
| --- | ---: | --- | --- | --- | --- | --- |
| Gitoxide | 54 tracked, 82 untracked | yes; reseed also active | `2026-05-24T01:21:10Z` `port-gitoxide-watchdog-20260524T003746Z.log`; active `gix-ignore` work and runner sampling, not a clean handoff | lane status reports full Gitoxide lane PHP green with `5896` pass / `0` fail; latest mailmap evidence is pending, and watchdog has already moved to ignore parsing | dirty-root green only at old `29e48310cada`; current tree moved | no current support blocker; checksum/archive/glob/charset helpers remain later-only |
| LightningCSS | 14 tracked, 127 untracked | yes | `2026-05-24T01:21:42Z` `port-lightningcss-watchdog-20260524T010719Z.log`; active transition/gradient edits, not a handoff | lane status reports `2374` pass / `0` fail; latest capacity focused shard passed `11` files / `530` assertions / `0` failures | dirty-root green only at old `29e48310cada`; current tree moved | source-map/charset/tree-sitter support rows are future gates, not active |
| markerPDF | 77 tracked, 101 untracked | yes | `2026-05-24T01:21:44Z` `port-markerpdf-watchdog-20260524T011857Z.log`; active `MarkdownPostProcessor` diff, not a handoff | lane status reports `425` pass / `0` fail; latest focused slice claims `MarkdownPostProcessorTest` 1 file / 39 assertions / 0 failures | dirty-root green only at old `29e48310cada`; current tree moved | high risk: PDF text/OCR/table/render/package support must stay gated; external/runtime/preflight plans do not count as native progress |
| libsqlite | 9 tracked, 88 untracked | yes; reseed also active | `2026-05-24T01:21:51Z` `port-libsqlite-watchdog-20260524T004407Z.log`; active WAL test diff, not a handoff | lane status reports `294` pass / `0` fail and focused `SQLiteHeaderTest.php`/harness evidence of `3228` assertions / `0` failures | dirty-root green only at old `29e48310cada`; current tree moved | `sql-storage-codec-core` stays later-only unless a shared codec boundary blocks Dolt/libsqlite/Quadrable |
| Readability | 11 tracked, 177 untracked | yes; reseed also active | `2026-05-24T01:21:08Z` `port-readability-watchdog-20260524T003747Z.log`; active share-class cleanup edit, not a handoff | lane status reports `213` tests / `2996` assertions / `0` failures; upstream `npm test` pass is previously recorded | dirty-root green only at old `29e48310cada`; current tree moved | XML/HTML/Unicode/table/media support rows remain later; no activation gate is present |
| Pandoc | 9 tracked, 61 untracked | yes | `2026-05-24T01:21:53Z` `port-pandoc-watchdog-20260524T011857Z.log`; active upstream Native reads and planned wrapper work, not a handoff | lane status reports `290` tests / `2730` assertions / `0` failures for `MarkdownReaderTest.php`; full Haskell runner not executed | dirty-root green only at old `29e48310cada`; current tree moved | `shared-zip-package-core` is the first support row only after an accepted `pandoc-rich-format-next` gate; do not activate from this moving tree |
| Quadrable | 38 tracked, 47 untracked | yes | `2026-05-24T01:21:49Z` `port-quadrable-watchdog-20260524T012103Z.log`; active session prompt/instructions, no clean handoff | lane status reports `196` tests / `4701` assertions / `0` failures; upstream `make -r test` pass remains prior evidence | dirty-root green only at old `29e48310cada`; current tree moved | no current support need; checksum/storage rows are later-only |
| Syncthing | 19 tracked, 134 untracked | yes; reseed also active | `2026-05-24T01:21:43Z` focused capacity log; focused PHP shard still active as PID `1794580` | lane status reports full lane `89` files / `5012` assertions / `0` failures; active focused shard prevents acceptance | dirty-root green only at old `29e48310cada`; current tree moved | `protobuf-wire-core` waits for a real BEP wire blocker; no support activation now |
| Difftastic | 10 tracked, 231 untracked | yes; reseed also active | `2026-05-24T01:21:53Z` `port-difftastic-watchdog-20260524T003851Z.log`; active Java interface/record/enum/annotation test diff, not a handoff | lane status reports `395` pass / `0` fail; focused lane evidence in status predates current active watchdog edits | dirty-root green only at old `29e48310cada`; current tree moved | tree-sitter/parser-generator support remains rejected unless a bounded dependency row is explicitly activated |
| rclone | 8 tracked, 103 untracked | yes; reseed also active | `2026-05-24T01:21:52Z` `port-rclone-watchdog-20260524T003954Z.log`; active VFS poll-interval edit, not a handoff | lane status reports `727` pass / `0` fail and VFS rc focused evidence; earlier same-pass process sample saw `go test ./vfs -run TestRcPollInterval -count=1` | dirty-root green only at old `29e48310cada`; current tree moved | provider metadata/checksum/archive/glob rows remain gated; live-provider/provider-integration work is excluded |
| Dolt | 12 tracked, 99 untracked | yes; reseed and `port-dolt-runner` active | `2026-05-24T01:21:43Z` `port-dolt-watchdog-20260524T003851Z.log`; active `STR_TO_DATE` upstream read while BATS remains in flight | lane status reports `370` pass / `0` fail, but the selected 14-file BATS run is still active and no coherent runner result exists | dirty-root green only at old `29e48310cada`; current tree moved | SQL/storage codec support stays later-only; current query-diff work is in-lane |
| esbuild | 20 tracked, 8 untracked | yes; reseed also active | `2026-05-24T01:21:51Z` `port-esbuild-watchdog-20260524T003748Z.log`; active class-expression decorator edits, not a handoff | lane status reports `322` tests / `2569` assertions / `0` failures for the prior JSX slice; current decorator edits are newer than that handoff | dirty-root green only at old `29e48310cada`; current tree moved | source-map/glob/charset support rows remain future gates; no support activation now |

## Candidate Decision

Selected candidate: **no safe candidate**.

Every lane has either an active primary session with current log writes,
unclear ownership from overlapping primary/reseed/capacity work, or a current
runner conflict. The checkout also moved from `29e48310cada` to
`e194c05fd23a` during this selector pass, and the latest root-green result is
for the older moving dirty snapshot rather than a frozen lane batch.

No narrow acceptance batch should be attempted until all of these are true:

- no primary lane/reseed session is actively editing the candidate lane;
- no focused or exact-root PHP process is active;
- Dolt BATS, Go, Cargo, and SQLite Tcl/testfixture runners are idle unless
  their completed evidence belongs to the candidate batch;
- `HEAD`, tracked dirty count, shortstat, relevant lane log mtimes, and runner
  state are unchanged across two polls;
- the candidate's scoped diff excludes unrelated lanes, status publication
  files, scripts, support-library rows, and dashboard artifacts unless those
  files are explicitly part of the accepted batch.

Exact root-gate precheck before any later no-argument root run:

```bash
git rev-parse --short=12 HEAD
git status --porcelain=v1 --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)' || true
pgrep -af '^php tools/run-tests\.php$' || true
pgrep -af '(bats|run-dolt-bats|dolt.*bats)' || true
pgrep -af '(^|/)(go|gofmt|gotestsum)( |$)|go test|go run|go build' || true
pgrep -af '(^|/)cargo( |$)|cargo test|cargo build' || true
pgrep -af '(tclsh|testfixture|sqlite.*testfixture|run-sqlite-tcl)' || true
find .tmux-team/logs -maxdepth 1 -type f -name 'port-*.log' -printf '%T@ %p\n' | sort -n | tail -40
```

Reject any later batch if the scoped files changed after the focused evidence,
if a root/focused PHP or upstream runner is active at the precheck, if
`HEAD`/dirty counts/log mtimes move between precheck and root run, if the diff
includes cross-lane leakage, if focused PHP evidence cannot be reproduced, or
if the batch activates a support library without a bounded row, activation
gate, upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
malformed/corrupt cases where relevant.

## Top Skipped Alternatives

1. rclone VFS poll-interval/rc batch: it is tempting because tracked dirt is
   small (`8` tracked rows), but the primary watchdog is actively editing VFS
   poll-interval behavior at `01:21:52Z`; an earlier same-pass process sample
   saw `go test ./vfs -run TestRcPollInterval -count=1`; and the lane has
   `103` untracked rows plus provider/support boundaries that must remain
   local-only and credential-free.
2. esbuild decorator batch: focused evidence for the previous JSX slice is
   green, but the latest watchdog at `01:21:51Z` is editing class-expression
   decorator lowering after the recorded handoff. Accepting now would mix the
   prior JSX evidence with newer unverified decorator changes.
3. Readability share-class cleanup batch: it has strong upstream Mocha evidence
   and only `11` tracked rows, but the watchdog is actively editing
   share-class/WordPress chrome behavior at `01:21:08Z`, and the lane has
   `177` untracked fixture/example rows. Ownership and exact fixture scope are
   not clean enough for acceptance.

## Checks Run

- `git status --short --branch`: final branch line
  `main...origin/main [ahead 629, behind 68]`.
- `git diff --name-only`: read for current dirty scope.
- `git status --porcelain=v1 --untracked-files=no | wc -l`: `293`.
- `git status --porcelain=v1 --untracked-files=all | wc -l`: `10631`.
- `git diff --shortstat`: `293 files changed, 138542 insertions(+), 16520 deletions(-)`.
- Lane dirty-count `awk` over `git status --porcelain=v1 --untracked-files=all`.
- `tmux list-sessions -F '#S'`: primary sessions present for every lane;
  `165` sessions in the final count.
- Process samples for PHP root/focused, Dolt BATS, Go, Cargo, and SQLite
  Tcl/testfixture runners.
- `jq empty dependency-backlog.json porting-summary.json`: exit `0`.
- Read-only lane-status and manifest extraction with `jq`.
- Recent lane/control log mtime and tail reads under `.tmux-team/logs`.

- `git diff --check`: exit `0`, no output.
