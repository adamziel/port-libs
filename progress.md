# Native PHP Ports Progress

## Roadmap

- [x] Inspect local workspace and tooling.
- [x] Create lane directories for all priority ports.
- [x] Add root PHP test harness and dashboard generator.
- [x] Create defensible seed upstream benchmark manifests for every lane.
- [x] Publish progress through a generated `porting.html` dashboard.
- [ ] Create GitHub repository and enable GitHub Pages for the dashboard.
- [ ] Replace seed manifests with full cloned/tested upstream benchmark denominators.
- [ ] Keep each lane moving through native PHP implementation slices with mapped upstream behavior.
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
| 1 | gitoxide | `port-gitoxide` | seed implementation | 4% | Expand loose object store into tree/commit parsing and map gitoxide object tests. |
| 2 | lightningcss | `port-lightningcss` | seed implementation | 3% | Broaden tokenizer fixtures and map Lightning CSS parser/minifier tests. |
| 3 | markerPDF | `port-markerpdf` | seed implementation | 3% | Add FlateDecode fixture coverage and map markerPDF extraction pipeline tests. |
| 4 | libsqlite | `port-libsqlite` | seed implementation | 4% | Parse first b-tree page headers and map SQLite file-format tests. |
| 5 | readability | `port-readability` | seed implementation | 3% | Compare against Mozilla Readability fixture corpus and improve scoring. |
| 6 | pandoc | `port-pandoc` | seed implementation | 3% | Add inline marks/list handling and map Pandoc native AST tests. |
| 7 | quadrable | `port-quadrable` | seed implementation | 2% | Inventory upstream tests and port the core coordinate/data model next. |
| 8 | syncthing | `port-syncthing` | seed implementation | 2% | Map protocol tests and add block hash/vector tests. |
| 9 | difftastic | `port-difftastic` | seed implementation | 2% | Map parser fixtures and replace line-only diff with syntax token anchors. |
| 10 | rclone | `port-rclone` | seed implementation | 2% | Map filter/checksum tests and add filesystem provider contract tests. |
| 11 | dolt | `port-dolt` | seed implementation | 2% | Map table-diff tests and add row schema/key encoding. |
| 12 | esbuild | `port-esbuild` | seed implementation | 2% | Map parser tests and add JS lexer token coverage. |

## Completed Milestones

- Created the initial lane layout required by the goal.
- Added a zero-dependency PHP test runner so native slices can be verified without waiting on PHPUnit.
- Added generated `porting.html` dashboard support backed by lane manifests/status files.
- Added seed native PHP slices and WordPress scenarios for all 12 lanes.
- Verified the seed suite: `php tools/run-tests.php` passes 12 test files, 58 assertions, 0 failures.

## Open Blockers

- Upstream benchmark denominators are currently static seed inventories until upstream repos are cloned or queried and their test suites counted.
- GitHub repository and Pages publishing are in progress.
- The tmux team should stay capped to avoid saturating the 6-core VM.

## Current Owner / Session

- Supervisor: main Codex session.
- Auditor: pending `port-auditor` tmux session.
- Worker sessions: pending launch via `scripts/start-tmux-team.sh`.

## Next Best Step

Initialize Git, create `adamziel/port-libs`, enable GitHub Pages from the `main` branch root, then start the capped tmux team on the top-priority lanes and auditor.
