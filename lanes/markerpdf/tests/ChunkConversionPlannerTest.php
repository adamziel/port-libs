<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ChunkConversionPlanner;

return [
    'plans chunk_convert.sh marker jobs across CUDA devices' => static function (TestRunner $t): void {
        $plan = (new ChunkConversionPlanner())->planFromEnvironment('/srv/incoming-pdfs', '/srv/marker-output', [
            'NUM_DEVICES' => '3',
            'NUM_WORKERS' => '4',
            'METADATA_FILE' => '/srv/import-meta.json',
            'MIN_LENGTH' => '250',
        ]);

        $t->same(3, $plan['num_devices']);
        $t->same(4, $plan['num_workers']);
        $t->same(5, $plan['launch_delay_seconds']);
        $t->same(3, count($plan['jobs']));
        $t->same('0', $plan['jobs'][0]['env']['CUDA_VISIBLE_DEVICES']);
        $t->same('2', $plan['jobs'][2]['env']['CUDA_VISIBLE_DEVICES']);
        $t->same([
            'marker',
            '/srv/incoming-pdfs',
            '/srv/marker-output',
            '--num_chunks',
            '3',
            '--chunk_idx',
            '2',
            '--workers',
            '4',
            '--metadata_file',
            '/srv/import-meta.json',
            '--min_length',
            '250',
        ], $plan['jobs'][2]['argv']);
        $t->contains("CUDA_VISIBLE_DEVICES='2'", $plan['jobs'][2]['command']);
        $t->contains("'--chunk_idx' '2'", $plan['jobs'][2]['command']);
    },
    'omits optional chunk_convert.sh flags when environment variables are empty' => static function (TestRunner $t): void {
        $plan = (new ChunkConversionPlanner())->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => 1,
            'NUM_WORKERS' => 2,
            'METADATA_FILE' => '',
            'MIN_LENGTH' => '',
        ]);

        $t->same([
            'marker',
            '/in',
            '/out',
            '--num_chunks',
            '1',
            '--chunk_idx',
            '0',
            '--workers',
            '2',
        ], $plan['jobs'][0]['argv']);
    },
    'mirrors chunk_convert.sh validation for required environment and folders' => static function (TestRunner $t): void {
        $planner = new ChunkConversionPlanner();

        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('/in', '/out', [
            'NUM_WORKERS' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('', '/out', [
            'NUM_DEVICES' => 2,
            'NUM_WORKERS' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('/in', '', [
            'NUM_DEVICES' => 2,
            'NUM_WORKERS' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => 'two',
            'NUM_WORKERS' => 2,
        ]));
    },
    'produces WordPress queue shards without executing marker subprocesses' => static function (TestRunner $t): void {
        $plan = (new ChunkConversionPlanner())->planDeviceJobs(
            '/wp/uploads/pdf-import',
            '/wp/uploads/marker-output',
            2,
            5,
            '/wp/uploads/pdf-import/metadata.json',
            100
        );

        $queueItems = array_map(static fn (array $job): array => [
            'queue' => 'markerpdf-import',
            'chunk' => $job['chunk_idx'] + 1,
            'chunks' => $job['num_chunks'],
            'workers' => $job['workers'],
            'argv' => $job['argv'],
        ], $plan['jobs']);

        $t->same(2, count($queueItems));
        $t->same(1, $queueItems[0]['chunk']);
        $t->same(2, $queueItems[1]['chunk']);
        $t->same('/wp/uploads/pdf-import/metadata.json', $queueItems[1]['argv'][10]);
        $t->same('100', $queueItems[1]['argv'][12]);
    },
];
