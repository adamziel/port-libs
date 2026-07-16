<?php

declare(strict_types=1);

use PortLibs\Dolt\DiffSummaryRenderer;
use PortLibs\Dolt\TableDeltaMatcher;
use PortLibs\Dolt\TableSchema;

$summaryRows = static function (): array {
    $baseSchema = TableSchema::fromColumns([
        ['name' => 'i', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ]);
    $expandedSchema = TableSchema::fromColumns([
        ['name' => 'i', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ['name' => 'j', 'tag' => 2, 'type' => 'int'],
    ]);
    $newTableSchema = TableSchema::fromColumns([
        ['name' => 'i', 'tag' => 10, 'type' => 'int', 'primaryKey' => true],
    ]);

    return (new TableDeltaMatcher())->summaryRows(
        [
            ['name' => 't1', 'schema' => $baseSchema, 'rowHash' => 't1', 'rowCount' => 0],
            ['name' => 't2', 'schema' => $baseSchema, 'rowHash' => 't2', 'rowCount' => 0],
            ['name' => 't3', 'schema' => $baseSchema, 'rowHash' => 'empty', 'rowCount' => 0],
        ],
        [
            ['name' => 't2', 'schema' => $expandedSchema, 'rowHash' => 't2', 'rowCount' => 0],
            ['name' => 't3', 'schema' => $baseSchema, 'rowHash' => 'with-row', 'rowCount' => 1],
            ['name' => 't4', 'schema' => $newTableSchema, 'rowHash' => 'empty-new', 'rowCount' => 0],
        ],
    );
};

return [
    'dolt diff summary renderer matches upstream table-only CLI output' => static function (TestRunner $t) use ($summaryRows): void {
        $output = (new DiffSummaryRenderer())->render($summaryRows());

        $t->same(implode("\n", [
            '+------------+-----------+-------------+---------------+',
            '| Table name | Diff type | Data change | Schema change |',
            '+------------+-----------+-------------+---------------+',
            '| t1         | dropped   | false       | true          |',
            '| t2         | modified  | false       | true          |',
            '| t3         | modified  | true        | false         |',
            '| t4         | added     | false       | true          |',
            '+------------+-----------+-------------+---------------+',
        ]), $output);
    },
    'dolt diff summary renderer filters table names and name-only output' => static function (TestRunner $t) use ($summaryRows): void {
        $renderer = new DiffSummaryRenderer();

        $filtered = $renderer->render($summaryRows(), ['tableNames' => ['t3']]);

        $t->same(implode("\n", [
            '+------------+-----------+-------------+---------------+',
            '| Table name | Diff type | Data change | Schema change |',
            '+------------+-----------+-------------+---------------+',
            '| t3         | modified  | true        | false         |',
            '+------------+-----------+-------------+---------------+',
        ]), $filtered);
        $t->same("t1\nt2\nt3\nt4", $renderer->renderNameOnly($summaryRows()));
        $t->same('', $renderer->render($summaryRows(), ['tableNames' => ['missing']]));
    },
    'dolt diff summary table args match upstream short circuit boundary' => static function (TestRunner $t) use ($summaryRows): void {
        $renderer = new DiffSummaryRenderer();

        $t->same(implode("\n", [
            '+------------+-----------+-------------+---------------+',
            '| Table name | Diff type | Data change | Schema change |',
            '+------------+-----------+-------------+---------------+',
            '| t4         | added     | false       | true          |',
            '+------------+-----------+-------------+---------------+',
        ]), $renderer->renderForTableArgs($summaryRows(), ['t4']));
        $t->same(implode("\n", [
            '+------------+-----------+-------------+---------------+',
            '| Table name | Diff type | Data change | Schema change |',
            '+------------+-----------+-------------+---------------+',
            '| t4         | added     | false       | true          |',
            '| t1         | dropped   | false       | true          |',
            '+------------+-----------+-------------+---------------+',
        ]), $renderer->renderForTableArgs($summaryRows(), ['t4', 't1']));
        $t->same('', $renderer->renderForTableArgs($summaryRows(), ['t1']));
        $t->same('', $renderer->renderForTableArgs($summaryRows(), ['t3']));
        $t->same($renderer->render($summaryRows()), $renderer->renderForTableArgs($summaryRows(), []));
    },
    'dolt diff summary renderer filters upstream diff types and removed alias' => static function (TestRunner $t) use ($summaryRows): void {
        $renderer = new DiffSummaryRenderer();

        $t->same(implode("\n", [
            '+------------+-----------+-------------+---------------+',
            '| Table name | Diff type | Data change | Schema change |',
            '+------------+-----------+-------------+---------------+',
            '| t4         | added     | false       | true          |',
            '+------------+-----------+-------------+---------------+',
        ]), $renderer->render($summaryRows(), ['filter' => TableDeltaMatcher::DIFF_ADDED]));
        $t->same("t2\nt3", $renderer->renderNameOnly($summaryRows(), ['filter' => TableDeltaMatcher::DIFF_MODIFIED]));
        $t->same('t1', $renderer->renderNameOnly($summaryRows(), ['filter' => 'removed']));
        $t->same('', $renderer->render($summaryRows(), ['filter' => TableDeltaMatcher::DIFF_RENAMED]));
        $t->throws(InvalidArgumentException::class, static fn () => $renderer->render($summaryRows(), ['filter' => 'invalid']));
    },
    'wordpress diff summary example renders migration table changes for review' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-diff-summary-cli.php';
        $lines = explode("\n", $output);

        $t->same('+------------------------------+-----------+-------------+---------------+', $lines[0]);
        $t->contains('wp_content_posts', $output);
        $t->contains('wp_legacy_links', $output);
        $t->contains('wp_import_audit', $output);
        $t->contains('wp_posts -> wp_content_posts', $output);
    },
    'wordpress filtered diff summary example separates migration table review queues' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-filtered-diff-summary-cli.php';

        $t->contains('wp_posts -> wp_content_posts', $output['renamedSummary']);
        $t->true(!str_contains($output['renamedSummary'], 'wp_legacy_links'));
        $t->contains('wp_import_audit', $output['addedSummary']);
        $t->same('wp_legacy_links', $output['droppedNames']);
    },
    'wordpress summary table arg example exposes upstream short circuit boundary' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-summary-table-arg-boundary.php';

        $t->contains('wp_import_audit', $output['firstChangedTable']);
        $t->same('', $output['laterDroppedTable']);
        $t->contains('wp_legacy_links', $output['genericFilteredTable']);
    },
];
