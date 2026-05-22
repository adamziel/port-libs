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
- Current launch target: 2 implementation lanes plus 1 auditor under the current VM load.

## Active Lanes

| Priority | Lane | Session | Phase | Estimate | Next Task |
| --- | --- | --- | --- | ---: | --- |
| 1 | gitoxide | stopped | upstream tree inventory plus commit primitive slice | 5% | Target the gitoxide object/ref crates with a controlled non-filtered checkout, then add tree object parsing and ref storage tests. |
| 2 | lightningcss | stopped | static upstream inventory + native value minifier slice | 4% | Port a small selector/tokenizer parser slice so PHP can distinguish selectors, declarations, at-rules, and nested rules before adding more transformer semantics. |
| 3 | markerPDF | stopped | cloned inventory + native text-line slice | 5% | Acquire or sample one upstream benchmark PDF/reference pair and map it to a native PHP parity fixture. |
| 4 | libsqlite | stopped | upstream inventory plus first b-tree page-header slice | 7% | Parse table leaf cells on the schema root page and decode `sqlite_schema` records needed to locate WordPress tables such as `wp_options`. |
| 5 | readability | stopped | cloned static inventory + readerable preflight slice | 5% | Map the first Mozilla test-page source/expected/metadata fixture into PHP parity, then improve metadata/byline/media handling. |
| 6 | pandoc | stopped | cloned inventory plus inline/list slice | 5% | Map a small subset of Pandoc `Tests.Readers.Markdown` golden cases into PHP fixtures, then add nested list item blocks. |
| 7 | quadrable | stopped | upstream inventory plus key primitive slice | 4% | Port the in-memory sparse tree update/get model for basic put/get, empty heads, batch insert, and deletion scenarios. |
| 8 | syncthing | stopped | cloned static upstream inventory + scanner block parity slice | 4% | Port protocol vector update/merge/compare semantics and add concurrent WordPress edit conflict fixtures. |
| 9 | difftastic | stopped | seed implementation | 2% | Map parser fixtures and replace line-only diff with syntax token anchors. |
| 10 | rclone | stopped | seed implementation | 2% | Map filter/checksum tests and add filesystem provider contract tests. |
| 11 | dolt | none | deferred | 2% | Sidetracked by user direction; ignore until the other lanes have reached baseline. |
| 12 | esbuild | stopped | seed implementation | 2% | Map parser tests and add JS lexer token coverage. |

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
- libsqlite: replaced the seed denominator with a shallow blobless upstream inventory at Git mirror `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7` / official manifest UUID `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`: 1,454 upstream test-related files/scripts counted plus 58 declared permutation suites; added native b-tree page header parsing and a WordPress SQLite root-page inspection example.
- Readability: replaced the seed denominator with a shallow sparse Mozilla Readability inventory at `08be6b4bdb204dd333c9b7a0cfbc0e730b257252`: 1,984 Mocha tests counted over 130 fixture pages; added native `isProbablyReaderable` thresholds, unlikely-candidate cleanup, semantic article scoring, and a WordPress page-builder migration fixture/example.
- Syncthing: replaced the seed denominator with a shallow blob-filtered upstream inventory at `3962a237232473c20a44945a6c8ce8c930375360`: 211 unique test-related paths counted, including 141 Go test files; added scanner-style block hashing, empty-file blocks, block-list hashes, upstream block size selection, and a WordPress media-resume example. Lane PHP: 6 passing tests, 0 failing tests.
- Pandoc: replaced the seed denominator with a blob-filtered shallow upstream inventory at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: 1,979 upstream test files/artifacts counted, including 1,974 under `test/`, 62 Haskell test modules, 1,064 command markdown fixtures, 252 `.native` expected artifacts, and 5 Lua engine test modules.
- Pandoc: added native Markdown inline emphasis/strong/link/code parsing, grouped bullet/ordered list AST nodes, escaped WordPress block output, and a WordPress Markdown import fixture/example. Lane PHP: 5 passing tests, 0 failing tests.
- Latest root suite: `php tools/run-tests.php` passes 14 test files, 166 assertions, 0 failures.

## Open Blockers

- Four upstream benchmark manifests are still static seed inventories until upstream repos are cloned or queried and their test suites counted: difftastic, rclone, dolt, and esbuild. Quadrable, markerPDF, Gitoxide, LightningCSS, libsqlite, Readability, Syncthing, and Pandoc now have stronger static inventories but not upstream runner parity.
- Gitoxide now has a safe tree inventory, but the full workspace test runner was not executed under the VM cap and the broad filtered-clone blob scan was stopped.
- Quadrable now has a counted static upstream denominator, but the C++ runner has not executed and most tree/proof/sync behavior remains unported.
- markerPDF now has a cloned static upstream inventory, but its full benchmark runner was not executed because the upstream workflow downloads external Google Drive benchmark data and installs heavy Poetry/ML/PDF dependencies; no benchmark PDF/reference pair is mapped yet.
- LightningCSS full upstream runners were not executed: `npm test` fails before tests because `node_modules`/`uvu` is absent, and an offline Cargo no-run probe cannot resolve `napi-derive` for the Node workspace member.
- libsqlite full upstream runner was not executed: SQLite testing requires configuring/building `testfixture`/`sqlite3` with Tcl development libraries and then running `testrunner.tcl` suites/permutations. The current denominator is a cloned static inventory of 1,454 upstream test-related files/scripts, not upstream pass parity.
- Readability full upstream runner was not executed: `npm test` reaches `mocha test/test-*.js` but fails before tests because the sparse upstream cache has no `node_modules` and `mocha` is not installed. The current denominator is a cloned static inventory of 1,984 Mocha tests, not upstream pass parity.
- Syncthing full upstream runner was not executed: `go test ./...` would require hydrating the full blob-filtered checkout, downloading/building Go dependencies, and running 141 Go test files plus integration test paths under `test/`. The current denominator is a cloned static inventory of 211 unique test-related paths, not upstream pass parity.
- Pandoc full upstream runner was not executed: `ghc`, `cabal`, and `stack` are unavailable in this environment, and upstream `test-pandoc` must be built as a Haskell Tasty executable from a full checkout. The current denominator is a cloned static inventory of 1,979 upstream test files/artifacts, not upstream pass parity.
- Independent audit on 2026-05-22 found the current PHP pass/fail counts are seed-local and must not be treated as upstream parity.
- Independent audit on 2026-05-22 found `port-pandoc` and `port-syncthing` active while their manifests remained static seeds and their upstream cache worktrees were dirty/empty; Syncthing and Pandoc are now reconciled with cloned static inventories, but neither has upstream runner parity.
- GitHub Pages may take a few minutes to finish its first build after each push.
- The tmux team should stay capped at two implementation workers plus an auditor under the current background load.
- Dolt is explicitly deferred by user direction until the other lanes reach the required baseline.

## Current Owner / Session

- Supervisor: main Codex session.
- Auditor: stopped; latest independent audit is recorded in `audits/latest.md`.
- Worker sessions: none active after integrating the latest Pandoc and Syncthing slices.

## Next Best Step

Continue Syncthing with `lib/protocol/vector.go` update/merge/compare semantics and concurrent WordPress edit conflict fixtures, while keeping Pandoc focused on mapping a small subset of `Tests.Readers.Markdown` golden cases into PHP fixtures before nested list work. Keep dashboard PHP pass/fail values treated as local until they are tied to upstream fixture IDs.
