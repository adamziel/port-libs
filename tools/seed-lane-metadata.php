<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$lanes = [
    'gitoxide' => [
        'priority' => 1,
        'library' => 'gitoxide',
        'upstream' => 'GitoxideLabs/gitoxide',
        'url' => 'https://github.com/GitoxideLabs/gitoxide',
        'commit' => '87433ed33eee9ba974111d20b854f6acb07cd4a6',
        'license' => 'MIT OR Apache-2.0',
        'architecture' => 'Rust workspace for Git object database, packfiles, refs, protocol, worktree, and plumbing crates.',
        'testSuite' => 'Safe upstream tree inventory: 93 Cargo manifests, 472 Rust test/bench source files, 605 fixture files, 180 fixture shell scripts, 214 generated archive fixtures.',
        'nativeTests' => 6,
        'progress' => 5,
        'phase' => 'upstream tree inventory plus commit primitive slice',
        'suiteProgress' => 'static upstream tree inventory counted; full cargo runner not executed',
        'currentWork' => 'Native loose Git object storage plus canonical commit header parsing.',
        'blocker' => 'Full cargo workspace runner not executed under VM cap; broad blob scans on filtered clones were stopped to avoid resource spikes.',
        'nextTask' => 'Target the gitoxide object/ref crates with a controlled non-filtered checkout, then add tree object parsing and ref storage tests.',
        'wp' => 'Git-backed WordPress content workflows, package installs, Playground snapshots, and server-side repo primitives.',
    ],
    'lightningcss' => [
        'priority' => 2,
        'library' => 'LightningCSS',
        'upstream' => 'parcel-bundler/lightningcss',
        'url' => 'https://github.com/parcel-bundler/lightningcss',
        'commit' => '22bdda3d190f1cd321d98026225cfc964af64ad9',
        'license' => 'MPL-2.0',
        'architecture' => 'Rust CSS parser, transformer, minifier, prefixer, modules, and bundler semantics.',
        'testSuite' => 'Shallow sparse upstream inventory: 241 behavior checks counted (160 Rust #[test], 81 Node uvu tests) plus 8 CSS fixtures.',
        'nativeTests' => 6,
        'progress' => 4,
        'phase' => 'static upstream inventory + native value minifier slice',
        'suiteProgress' => 'static upstream inventory counted: 241 behavior checks plus 8 CSS fixtures; upstream runners not executed',
        'currentWork' => 'Native CSS comment/whitespace minifier, declaration parser, value-level color minification, calc operator spacing, and WordPress block-theme fixture.',
        'blocker' => 'Full upstream runners not executed: npm test lacks node_modules/uvu and offline Cargo no-run cannot resolve napi-derive for the Node workspace member.',
        'nextTask' => 'Port a small selector/tokenizer parser slice so PHP can distinguish selectors, declarations, at-rules, and nested rules before adding more transformer semantics.',
        'wp' => 'Shared-hosting CSS transforms for themes, blocks, Playground, and plugin build-free delivery.',
    ],
    'markerpdf' => [
        'priority' => 3,
        'library' => 'markerPDF',
        'upstream' => 'sddai/markerPDF',
        'url' => 'https://github.com/sddai/markerPDF',
        'commit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
        'license' => 'GPL-3.0-or-later',
        'architecture' => 'Python PDF-to-Markdown extraction pipeline using pdftext, pypdfium2, Surya OCR/layout models, table/equation/image extraction, and Markdown post-processing.',
        'testSuite' => 'Cloned static inventory: 6 README benchmark documents, 2 CI score thresholds, and 8 committed markdown examples counted.',
        'nativeTests' => 5,
        'progress' => 5,
        'phase' => 'cloned inventory + native text-line slice',
        'suiteProgress' => 'cloned static inventory: 6 upstream benchmark documents identified, 2 CI score thresholds identified, 0 benchmark documents mapped; upstream runner not executed',
        'currentWork' => 'Native PDF content stream text-line extraction for literal, array, hex, UTF-16 hex, FlateDecode streams, adjacent same-line text operators, PDF line continuations, and text line movement operators.',
        'blocker' => 'Full upstream benchmark runner not executed: benchmark PDFs/references are downloaded from Google Drive by CI and heavy Poetry dependencies/model downloads are required; no external benchmark pair is mapped yet.',
        'nextTask' => 'Acquire or sample one upstream benchmark PDF/reference pair and map it to a native PHP parity fixture before broadening layout/table/OCR behavior.',
        'wp' => 'PDF import into clean post content and Data Liberation document conversion workflows, with fixture output convertible to heading/paragraph WordPress blocks.',
    ],
    'libsqlite' => [
        'priority' => 4,
        'library' => 'libsqlite',
        'upstream' => 'sqlite/sqlite',
        'url' => 'https://github.com/sqlite/sqlite',
        'commit' => '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
        'license' => 'SQLite blessing / public-domain style; verify exact upstream notice before redistribution',
        'architecture' => 'SQLite database file format, b-tree pages, records, varints, journaling, and SQL-visible primitives.',
        'testSuite' => 'SQLite TCL/permutation tests plus file-format fixtures; denominator pending upstream inventory.',
        'nativeTests' => 3,
        'progress' => 4,
        'phase' => 'seed implementation',
        'currentWork' => 'Native SQLite database header parser and SQLite varint decoder.',
        'nextTask' => 'Parse first b-tree page headers and map SQLite file-format tests.',
        'wp' => 'SQLite fallback/read-write tooling for WordPress hosts where the SQLite extension is unavailable.',
    ],
    'readability' => [
        'priority' => 5,
        'library' => 'Readability/content rewrite engine',
        'upstream' => 'mozilla/readability',
        'url' => 'https://github.com/mozilla/readability',
        'commit' => '08be6b4bdb204dd333c9b7a0cfbc0e730b257252',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'DOM scoring/extraction with content cleanup and WordPress block-oriented rewriting.',
        'testSuite' => 'Mozilla Readability fixture corpus; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 3,
        'phase' => 'seed implementation',
        'currentWork' => 'Native DOM extractor removes chrome, scores content containers, and emits simple WordPress blocks.',
        'nextTask' => 'Compare against Mozilla Readability fixture corpus and improve scoring/error behavior.',
        'wp' => 'Migration-aware article cleanup into clean WordPress blocks with link/media/page-builder cleanup.',
    ],
    'pandoc' => [
        'priority' => 6,
        'library' => 'pandoc',
        'upstream' => 'jgm/pandoc',
        'url' => 'https://github.com/jgm/pandoc',
        'commit' => '0640c4c9859aa5a3ede082c190fcd5883c24ac83',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Shared document AST with readers/writers for Markdown, HTML, WXR, EPUB/PDF intermediates, and WordPress blocks.',
        'testSuite' => 'Pandoc golden tests/readers/writers; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 3,
        'phase' => 'seed implementation',
        'currentWork' => 'Native Markdown block reader and WordPress block writer for headings, paragraphs, and lists.',
        'nextTask' => 'Add inline marks/list nesting and map Pandoc native AST tests.',
        'wp' => 'Document conversion kernel for Data Liberation imports and block-oriented output.',
    ],
    'quadrable' => [
        'priority' => 7,
        'library' => 'quadrable',
        'upstream' => 'hoytech/quadrable',
        'url' => 'https://github.com/hoytech/quadrable',
        'commit' => '4f44437dc9b951a91986ad69e2856938387be614',
        'license' => 'BSD-2-Clause',
        'architecture' => 'Header-only C++ sparse binary Merkle tree, compact proofs, sync, copy-on-write LMDB-backed store, MemStore, and quadb CLI.',
        'testSuite' => 'Static inventory of upstream check.cpp and Makefile: 34 top-level scenarios, 29 equivHeads subcases, 136 verify checks, 20 verifyThrow checks.',
        'nativeTests' => 8,
        'progress' => 4,
        'phase' => 'upstream inventory plus key primitive slice',
        'suiteProgress' => 'static upstream check.cpp inventory counted; 8 PHP tests mapped to hashing/key behavior',
        'currentWork' => 'Native hash/key primitives for empty branches, leaf domain separation, path-bit addressing, integer key encoding, and prefix retention.',
        'blocker' => 'C++ runner not executed yet; upstream build requires LMDB, BLAKE2, and initialized submodules. Most tree/proof/sync scenarios remain unported.',
        'nextTask' => 'Port the in-memory sparse tree update/get model for basic put/get, empty heads, batch insert, and deletion scenarios.',
        'wp' => 'Authenticated local-first state sync for Playground snapshots and content databases.',
    ],
    'syncthing' => [
        'priority' => 8,
        'library' => 'syncthing',
        'upstream' => 'syncthing/syncthing',
        'url' => 'https://github.com/syncthing/syncthing',
        'commit' => '3962a237232473c20a44945a6c8ce8c930375360',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Go device/folder model, block exchange, conflict handling, protocol messages, and resumable sync.',
        'testSuite' => 'Go package tests and integration tests; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 2,
        'phase' => 'seed implementation',
        'currentWork' => 'Native content block hashing and verification primitives.',
        'nextTask' => 'Map protocol tests and add block vector/update conflict fixtures.',
        'wp' => 'Resumable media/content synchronization for local-first WordPress and Playground folders.',
    ],
    'difftastic' => [
        'priority' => 9,
        'library' => 'difftastic',
        'upstream' => 'Wilfred/difftastic',
        'url' => 'https://github.com/Wilfred/difftastic',
        'commit' => '7ccfcb315f7e46fd015809416c7d7dffa5be7078',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Rust syntax-aware structural diff with parser integrations and HTML/text output.',
        'testSuite' => 'Rust tests, parser fixtures, sample diffs; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 2,
        'phase' => 'seed implementation',
        'currentWork' => 'Native token-level differ that avoids raw line-only comparison.',
        'nextTask' => 'Map parser fixtures and replace generic tokens with syntax-tree anchors.',
        'wp' => 'Readable diffs for blocks, templates, theme.json, code snippets, and structured documents.',
    ],
    'rclone' => [
        'priority' => 10,
        'library' => 'rclone',
        'upstream' => 'rclone/rclone',
        'url' => 'https://github.com/rclone/rclone',
        'commit' => '28d6b0b7b906da70afdc036ba5bb21f3c86613b8',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Go storage provider abstraction, copy/sync/check semantics, filters, checksums, and resumability.',
        'testSuite' => 'Go backend/unit tests plus integration remotes; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 2,
        'phase' => 'seed implementation',
        'currentWork' => 'Native in-memory provider contract with object metadata, copy, list, and checksum sync plan.',
        'nextTask' => 'Map filter/checksum tests and add filesystem provider contract tests.',
        'wp' => 'Portable backup/import/export sync for shared hosts and cloud storage providers.',
    ],
    'dolt' => [
        'priority' => 11,
        'library' => 'dolt',
        'upstream' => 'dolthub/dolt',
        'url' => 'https://github.com/dolthub/dolt',
        'commit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Git-for-data database versioning, table storage, commits, branches, diffs, merges, and SQL behavior.',
        'testSuite' => 'Go tests/BATS integration tests; denominator pending upstream inventory.',
        'nativeTests' => 1,
        'progress' => 2,
        'phase' => 'deferred',
        'audit' => 'deferred by user direction until non-dolt lanes reach baseline',
        'currentWork' => 'Sidetracked by user direction; no active worker should be assigned until the other lanes reach the required baseline.',
        'blocker' => 'User deferred Dolt until the rest of the portfolio has stronger baselines.',
        'nextTask' => 'Do not schedule Dolt work until the other lanes have real upstream denominators, native slices, passing tests, WordPress scenarios, and dashboard status.',
        'wp' => 'Versioned content/data migrations and inspectable database change sets.',
    ],
    'esbuild' => [
        'priority' => 12,
        'library' => 'esbuild',
        'upstream' => 'evanw/esbuild',
        'url' => 'https://github.com/evanw/esbuild',
        'commit' => '6a794dff68e6a43539f6da671e3080efdf11ca70',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Go JS/TS/JSX/CSS parser, transformer, linker, bundler, and minifier.',
        'testSuite' => 'Go parser/bundler/end-to-end tests; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 2,
        'phase' => 'seed implementation',
        'currentWork' => 'Native JavaScript lexer for identifiers, numbers, strings, comments, and punctuators.',
        'nextTask' => 'Map parser tests and add JS/TS lexer token coverage.',
        'wp' => 'Node-free asset tooling for shared hosting, Playground, PHAR tools, and browser-backed PHP environments.',
    ],
];

foreach ($lanes as $slug => $lane) {
    $dir = "{$root}/lanes/{$slug}";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $manifest = [
        'schemaVersion' => 1,
        'lane' => $slug,
        'priority' => $lane['priority'],
        'upstream' => [
            'name' => $lane['upstream'],
            'url' => $lane['url'],
            'commit' => $lane['commit'],
            'license' => $lane['license'],
            'architecture' => $lane['architecture'],
        ],
        'benchmarkDenominator' => [
            'status' => isset($lane['suiteProgress']) ? 'static-upstream-inventory' : 'static-seed',
            'total' => isset($lane['suiteProgress']) && $slug === 'quadrable'
                ? '34 top-level check.cpp scenarios; 29 equivHeads subcases; 136 verify checks; 20 verifyThrow checks'
                : ($slug === 'gitoxide'
                    ? '93 Cargo manifests; 472 Rust test/bench source files; 605 fixture files; 180 fixture shell scripts; 214 generated archive fixtures; 2877 upstream files total'
                    : ($slug === 'lightningcss'
                        ? '241 behavior checks counted (160 Rust tests, 81 Node uvu tests) plus 8 CSS fixtures'
                        : ($slug === 'markerpdf'
                            ? '6 README benchmark documents, 2 CI score thresholds, 8 committed markdown examples, 0 benchmark documents mapped'
                            : 'pending full upstream inventory'))),
            'mapped' => $lane['nativeTests'],
            'source' => $lane['testSuite'],
            'warning' => 'This is a defensible seed manifest, not a completed upstream denominator. A worker must clone/count the upstream suite before this lane can claim real parity progress.',
        ],
        'nativeImplementation' => [
            'language' => 'PHP',
            'shellOutsAllowedForProgress' => false,
            'currentSlice' => $lane['currentWork'],
        ],
        'wordpressScenario' => $lane['wp'],
        'nextTask' => $lane['nextTask'],
    ];
    if ($slug === 'quadrable') {
        $manifest['benchmarkDenominator']['runner'] = 'make test';
        $manifest['benchmarkDenominator']['runnerStatus'] = 'not executed in this lane slice; upstream runner requires LMDB, BLAKE2, and initialized submodules';
        $manifest['benchmarkDenominator']['inventoryPath'] = 'lanes/quadrable/notes/upstream-inventory.md';
        $manifest['benchmarkDenominator']['warning'] = 'The denominator is counted from upstream sources, but parity is still partial. Native PHP tests only map a small subset of hashing/key behavior.';
    }
    if ($slug === 'gitoxide') {
        $manifest['benchmarkDenominator']['runner'] = 'cargo test workspace and crate-level tests';
        $manifest['benchmarkDenominator']['runnerStatus'] = 'not executed; full workspace test run would hydrate/build too much for the current VM cap';
        $manifest['benchmarkDenominator']['inventoryPath'] = 'lanes/gitoxide/notes/upstream-inventory.md';
        $manifest['benchmarkDenominator']['warning'] = 'Tree inventory is stronger than the seed manifest but still not a full upstream test denominator. Next slice should target object/ref crates in a controlled non-filtered checkout.';
    }
    if ($slug === 'lightningcss') {
        $manifest['benchmarkDenominator']['runner'] = 'npm test and cargo test';
        $manifest['benchmarkDenominator']['runnerStatus'] = 'not executed; npm test lacks node_modules/uvu and offline Cargo no-run cannot resolve napi-derive';
        $manifest['benchmarkDenominator']['warning'] = 'Static inventory is counted, but upstream runner parity remains blocked.';
    }
    if ($slug === 'markerpdf') {
        $manifest['benchmarkDenominator']['runner'] = 'upstream benchmark workflow plus scripts/verify_benchmark_scores.py';
        $manifest['benchmarkDenominator']['runnerStatus'] = 'not executed; upstream CI downloads benchmark data from Google Drive and requires heavy Poetry/ML/PDF dependencies';
        $manifest['benchmarkDenominator']['warning'] = 'Cloned static inventory is counted, but no upstream benchmark PDF/reference pair is mapped yet.';
    }
    if (!empty($lane['phase']) && $lane['phase'] === 'deferred') {
        $manifest['deferred'] = [
            'status' => true,
            'reason' => 'User requested this lane be sidetracked until the rest of the portfolio is established.',
        ];
    }

    $status = [
        'schemaVersion' => 1,
        'library' => $lane['library'],
        'estimatedProgress' => $lane['progress'],
        'suiteProgress' => $lane['suiteProgress'] ?? 'static manifest seed; upstream suite not yet executed',
        'phpPass' => $lane['nativeTests'],
        'phpFail' => 0,
        'wordpressScenarios' => $lane['wp'],
        'phase' => $lane['phase'],
        'audit' => $lane['audit'] ?? 'needs independent auditor review',
        'currentWork' => $lane['currentWork'],
        'blocker' => $lane['blocker'] ?? 'Full upstream benchmark denominator still needs cloned/tested inventory.',
        'latestCommit' => 'pending first repository commit',
        'nextTask' => $lane['nextTask'],
    ];

    file_put_contents($dir . '/UPSTREAM_TEST_MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    file_put_contents($dir . '/lane-status.json', json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $notes = "# {$lane['library']} WordPress Scenario\n\n"
        . $lane['wp'] . "\n\n"
        . "## Current Native Slice\n\n{$lane['currentWork']}\n\n"
        . "## Next Task\n\n{$lane['nextTask']}\n";
    file_put_contents($dir . '/notes/wordpress-scenarios.md', $notes);
}

fwrite(STDOUT, 'Seeded metadata for ' . count($lanes) . " lanes\n");
