# Native PHP Ports Progress

## Roadmap

- [x] Inspect local workspace and tooling.
- [x] Create lane directories for all priority ports.
- [x] Add root PHP test harness and dashboard generator.
- [x] Create defensible seed upstream benchmark manifests for every lane.
- [x] Publish progress through a generated `porting.html` dashboard.
- [x] Create GitHub repository and enable GitHub Pages for the dashboard.
- [ ] Replace seed manifests with full cloned/tested upstream benchmark denominators.
- [ ] Keep each lane moving through native PHP implementation slices with mapped upstream behavior.
- [x] Add capped tmux worker/auditor launch scripts.
- [ ] Run an independent auditor loop that challenges shallow progress and updates lane blockers.
- [ ] Reach the required baseline for every lane: upstream manifest, native PHP slice, passing PHP tests, WordPress scenarios, and visible `porting.html` status.

## Environment

- Workspace: `/home/claude/port-libs`
- PHP: 8.2.29 CLI
- Composer: 2.8.12
- tmux: 3.5a
- CPU: 6 logical cores
- Memory at bootstrap: about 6.2 GiB available
- Concurrency cap: 3 implementation lanes plus 1 auditor until the VM proves it can handle more.

## Active Lanes

| Priority | Lane | Session | Phase | Estimate | Next Task |
| --- | --- | --- | --- | ---: | --- |
| 1 | gitoxide | stopped | upstream tree inventory plus commit primitive slice | 5% | Target the gitoxide object/ref crates with a controlled non-filtered checkout, then add tree object parsing and ref storage tests. |
| 2 | lightningcss | `port-lightningcss` | static upstream inventory + native value minifier slice | 4% | Port a small selector/tokenizer parser slice so PHP can distinguish selectors, declarations, at-rules, and nested rules before adding more transformer semantics. |
| 3 | markerPDF | `port-markerpdf` | cloned inventory + native text-line slice | 5% | Acquire or sample one upstream benchmark PDF/reference pair and map it to a native PHP parity fixture. |
| 4 | libsqlite | `port-libsqlite` | seed implementation | 4% | Parse first b-tree page headers and map SQLite file-format tests. |
| 5 | readability | `port-readability` | seed implementation | 3% | Compare against Mozilla Readability fixture corpus and improve scoring. |
| 6 | pandoc | `port-pandoc` | seed implementation | 3% | Add inline marks/list handling and map Pandoc native AST tests. |
| 7 | quadrable | `port-quadrable` | upstream inventory plus key primitive slice | 4% | Port the in-memory sparse tree update/get model for basic put/get, empty heads, batch insert, and deletion scenarios. |
| 8 | syncthing | `port-syncthing` | seed implementation | 2% | Map protocol tests and add block hash/vector tests. |
| 9 | difftastic | `port-difftastic` | seed implementation | 2% | Map parser fixtures and replace line-only diff with syntax token anchors. |
| 10 | rclone | `port-rclone` | seed implementation | 2% | Map filter/checksum tests and add filesystem provider contract tests. |
| 11 | dolt | none | deferred | 2% | Sidetracked by user direction; ignore until the other lanes have reached baseline. |
| 12 | esbuild | `port-esbuild` | seed implementation | 2% | Map parser tests and add JS lexer token coverage. |

## Completed Milestones

- Created the initial lane layout required by the goal.
- Added a zero-dependency PHP test runner so native slices can be verified without waiting on PHPUnit.
- Added generated `porting.html` dashboard support backed by lane manifests/status files.
- Added seed native PHP slices and WordPress scenarios for all 12 lanes.
- Verified the seed suite: `php tools/run-tests.php` passes 13 test files, 75 assertions, 0 failures.
- Added `scripts/start-tmux-team.sh`, `scripts/check-tmux-team.sh`, and durable worker/auditor prompts under `.tmux-team/`.
- Replaced Quadrable's seed denominator with a static upstream `check.cpp` inventory: 34 top-level scenarios, 29 `equivHeads` subcases, 136 `verify` checks, and 20 `verifyThrow` checks.
- Gitoxide: replaced the seed denominator with a safe upstream tree inventory at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: 93 Cargo manifests, 472 Rust test/bench source files, and 605 fixture files counted; added native commit parsing tests.
- markerPDF: replaced the seed denominator with a shallow cloned upstream inventory at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`: 6 README benchmark documents, 2 CI score thresholds, and 8 committed markdown examples counted; added native text-line extraction plus a WordPress block import fixture/example.
- LightningCSS: replaced the seed denominator with a shallow sparse upstream inventory at `22bdda3d190f1cd321d98026225cfc964af64ad9`: 241 behavior checks counted (160 Rust `#[test]`, 81 Node `uvu` tests) plus 8 CSS fixtures; added value-level color minification, calc operator spacing, and a WordPress block-theme fixture/example.
- Latest root suite: `php tools/run-tests.php` passes 14 test files, 87 assertions, 0 failures.

## Open Blockers

- Most upstream benchmark denominators are still static seed inventories until upstream repos are cloned or queried and their test suites counted; Quadrable, markerPDF, and LightningCSS now have stronger static inventories but not upstream runner parity.
- Gitoxide now has a safe tree inventory, but the full workspace test runner was not executed under the VM cap and the broad filtered-clone blob scan was stopped.
- Quadrable now has a counted static upstream denominator, but the C++ runner has not executed and most tree/proof/sync behavior remains unported.
- markerPDF now has a cloned static upstream inventory, but its full benchmark runner was not executed because the upstream workflow downloads external Google Drive benchmark data and installs heavy Poetry/ML/PDF dependencies; no benchmark PDF/reference pair is mapped yet.
- LightningCSS full upstream runners were not executed: `npm test` fails before tests because `node_modules`/`uvu` is absent, and an offline Cargo no-run probe cannot resolve `napi-derive` for the Node workspace member.
- Independent audit on 2026-05-22 found zero lanes with committed real upstream denominators; current PHP pass/fail counts are seed-local and must not be treated as upstream parity.
- GitHub Pages may take a few minutes to finish its first build after each push.
- The tmux team should stay capped to avoid saturating the 6-core VM.
- Dolt is explicitly deferred by user direction until the other lanes reach the required baseline.

## Current Owner / Session

- Supervisor: main Codex session.
- Auditor: `port-auditor` session is present; latest independent audit is recorded in `audits/latest.md`.
- Worker sessions: `port-gitoxide`, `port-lightningcss`, and `port-markerpdf` sessions are present under the current cap; lower-priority lane sessions are not active yet.

## Next Best Step

Integrate the active LightningCSS and markerPDF worker slices, then map one markerPDF benchmark PDF/reference pair and target Gitoxide object/ref crates with a controlled non-filtered checkout.
