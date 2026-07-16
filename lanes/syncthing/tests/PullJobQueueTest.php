<?php

declare(strict_types=1);

use PortLibs\Syncthing\PullJobQueue;

return [
    'maps upstream job queue push pop done cycle' => static function (TestRunner $t): void {
        $queue = new PullJobQueue();
        foreach (['f1', 'f2', 'f3', 'f4'] as $name) {
            $queue->push($name);
        }

        $t->same(['progress' => [], 'queued' => ['f1', 'f2', 'f3', 'f4'], 'skipped' => 0], $queue->jobs(1, 100));

        for ($i = 1; $i < 5; $i++) {
            $name = 'f' . $i;
            $t->same($name, $queue->pop());
            $t->same(1, $queue->progressCount());
            $t->same(3, $queue->queuedCount());

            $queue->done($name);
            $t->same(0, $queue->progressCount());
            $t->same(3, $queue->queuedCount());

            $queue->push($name);
            $t->same(0, $queue->progressCount());
            $t->same(4, $queue->queuedCount());

            $queue->done('f5');
            $t->same(0, $queue->progressCount());
            $t->same(4, $queue->queuedCount());
        }

        for ($i = 4; $i > 0; $i--) {
            $name = 'f' . $i;
            $queue->bringToFront($name);
            $t->same($name, $queue->pop());
            $queue->done('f5');
            $t->same(5 - $i, $queue->progressCount());
            $t->same($i - 1, $queue->queuedCount());
        }

        $t->same(null, $queue->pop());
        $t->same(4, $queue->progressCount());

        foreach (['f1', 'f2', 'f3', 'f4', 'f5'] as $name) {
            $queue->done($name);
        }
        $t->same(null, $queue->pop());
        $t->same(['progress' => [], 'queued' => [], 'skipped' => 0], $queue->jobs(1, 100));
        $queue->bringToFront('');
        $queue->done('f5');
        $t->same(['progress' => [], 'queued' => [], 'skipped' => 0], $queue->jobs(1, 100));
    },
    'maps upstream bring to front ordering cases' => static function (TestRunner $t): void {
        $queue = syncthing_pull_job_queue(['f1', 'f2', 'f3', 'f4']);

        $t->same(['f1', 'f2', 'f3', 'f4'], $queue->jobs(1, 100)['queued']);
        $queue->bringToFront('f1');
        $t->same(['f1', 'f2', 'f3', 'f4'], $queue->jobs(1, 100)['queued']);

        $queue->bringToFront('f3');
        $t->same(['f3', 'f1', 'f2', 'f4'], $queue->jobs(1, 100)['queued']);

        $queue->bringToFront('f2');
        $t->same(['f2', 'f3', 'f1', 'f4'], $queue->jobs(1, 100)['queued']);

        $queue->bringToFront('f4');
        $t->same(['f4', 'f2', 'f3', 'f1'], $queue->jobs(1, 100)['queued']);
    },
    'maps upstream queue pagination across progress and queued jobs' => static function (TestRunner $t): void {
        $names = [];
        for ($i = 0; $i < 10; $i++) {
            $names[] = 'f' . $i;
        }
        $queue = syncthing_pull_job_queue($names);

        $t->same(['progress' => [], 'queued' => $names, 'skipped' => 0], $queue->jobs(1, 100));
        $t->same(['progress' => [], 'queued' => array_slice($names, 0, 5), 'skipped' => 0], $queue->jobs(1, 5));
        $t->same(['progress' => [], 'queued' => array_slice($names, 5), 'skipped' => 5], $queue->jobs(2, 5));
        $t->same(['progress' => [], 'queued' => array_slice($names, 7), 'skipped' => 7], $queue->jobs(2, 7));
        $t->same(['progress' => [], 'queued' => [], 'skipped' => 10], $queue->jobs(3, 5));

        $t->same('f0', $queue->pop());
        $t->same(['progress' => ['f0'], 'queued' => array_slice($names, 1), 'skipped' => 0], $queue->jobs(1, 100));
        $t->same(['progress' => ['f0'], 'queued' => array_slice($names, 1, 4), 'skipped' => 0], $queue->jobs(1, 5));
        $t->same(['progress' => [], 'queued' => array_slice($names, 5), 'skipped' => 5], $queue->jobs(2, 5));
        $t->same(['progress' => [], 'queued' => array_slice($names, 7), 'skipped' => 7], $queue->jobs(2, 7));
        $t->same(['progress' => [], 'queued' => [], 'skipped' => 10], $queue->jobs(3, 5));

        for ($i = 1; $i < 8; $i++) {
            $t->same($names[$i], $queue->pop());
        }

        $t->same(['progress' => array_slice($names, 0, 8), 'queued' => array_slice($names, 8), 'skipped' => 0], $queue->jobs(1, 100));
        $t->same(['progress' => array_slice($names, 0, 5), 'queued' => [], 'skipped' => 0], $queue->jobs(1, 5));
        $t->same(['progress' => array_slice($names, 5, 3), 'queued' => array_slice($names, 8), 'skipped' => 5], $queue->jobs(2, 5));
        $t->same(['progress' => array_slice($names, 7, 1), 'queued' => array_slice($names, 8), 'skipped' => 7], $queue->jobs(2, 7));
        $t->same(['progress' => [], 'queued' => [], 'skipped' => 10], $queue->jobs(3, 5));
    },
    'queues prioritized wordpress media pulls and validates inputs' => static function (TestRunner $t): void {
        $queue = new PullJobQueue();
        $queue->push('wp-content/uploads/2026/archive.zip', 25_000_000, 1_780_000_000_000_000_000);
        $queue->push('wp-content/uploads/2026/hero.jpg', 900_000, 1_780_000_001_000_000_000);
        $queue->push('wp-content/uploads/2026/private-export.zip', 5_000_000, 1_780_000_002_000_000_000);

        $queue->bringToFront('wp-content/uploads/2026/private-export.zip');
        $t->same('wp-content/uploads/2026/private-export.zip', $queue->pop());
        $t->same([
            'progress' => ['wp-content/uploads/2026/private-export.zip'],
            'queued' => ['wp-content/uploads/2026/archive.zip', 'wp-content/uploads/2026/hero.jpg'],
            'skipped' => 0,
        ], $queue->jobs(1, 10));

        $queue->done('wp-content/uploads/2026/private-export.zip');
        $queue->reset();
        $t->same(0, $queue->progressCount());
        $t->same(0, $queue->queuedCount());

        $t->throws(InvalidArgumentException::class, static fn () => $queue->push(''));
        $t->throws(InvalidArgumentException::class, static fn () => $queue->push('wp-content/uploads/2026/hero.jpg', -1));
        $t->throws(InvalidArgumentException::class, static fn () => $queue->jobs(0, 10));
        $t->throws(InvalidArgumentException::class, static fn () => $queue->jobs(1, 0));
    },
];

/**
 * @param list<string> $names
 */
function syncthing_pull_job_queue(array $names): PullJobQueue
{
    $queue = new PullJobQueue();
    foreach ($names as $name) {
        $queue->push($name);
    }

    return $queue;
}
