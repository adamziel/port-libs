<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfCallableSemanticRecordProcessor;
use PortLibs\Pandoc\PdfSemanticRecordPipeline;

return [
    'pdf semantic pipeline runs named stages in order and audits each boundary' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('append', static function (array $records): array {
                $records[0]['text'] .= ' beta';

                return $records;
            }),
            new PdfCallableSemanticRecordProcessor('classify', static function (array $records): array {
                $records[0]['layout']['role'] = 'body';

                return $records;
            }),
        ]);

        $result = $pipeline->run([['text' => 'alpha', 'layout' => ['page' => 1]]]);

        $t->same('alpha beta', $result['records'][0]['text']);
        $t->same('body', $result['records'][0]['layout']['role']);
        $t->same(['append', 'classify'], array_column($result['trace'], 'processor'));
        $t->same(true, $result['trace'][0]['changed']);
        $t->same(true, $result['trace'][1]['changed'], 'Layout-only classifications must be visible in the audit digest.');
        $t->same(5, $result['trace'][0]['textByteDelta']);
    },

    'pdf semantic pipeline reports a deterministic no-op stage' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('noop', static fn (array $records): array => $records),
        ]);
        $result = $pipeline->run([['text' => 'alpha', 'layout' => null]]);

        $t->same(false, $result['trace'][0]['changed']);
        $t->same($result['trace'][0]['inputDigest'], $result['trace'][0]['outputDigest']);
        $t->same(0, $result['trace'][0]['recordDelta']);
    },

    'pdf semantic pipeline can transfer sole record ownership into its result' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('noop', static fn (array $records): array => $records),
        ]);
        $records = [['text' => 'alpha', 'layout' => ['page' => 1]]];
        $copiedAlias = $records;

        $result = $pipeline->runOwned($records);

        $t->same([], $records);
        $t->same([['text' => 'alpha', 'layout' => ['page' => 1]]], $copiedAlias);
        $t->same([['text' => 'alpha', 'layout' => ['page' => 1]]], $result['records']);
    },

    'pdf semantic owned pipeline restores the last valid phase when a processor throws' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('append', static function (array $records): array {
                $records[0]['text'] .= ' beta';

                return $records;
            }),
            new PdfCallableSemanticRecordProcessor('throw', static function (array $_records): array {
                throw new RuntimeException('processor failed');
            }),
        ]);
        $records = [['text' => 'alpha', 'layout' => null]];

        $t->throws(RuntimeException::class, static function () use ($pipeline, &$records): array {
            return $pipeline->runOwned($records);
        });
        $t->same([['text' => 'alpha beta', 'layout' => null]], $records);
    },

    'pdf semantic owned pipeline consumes invalid returned output before validation' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('invalid', static fn (array $_records): array => [
                ['text' => 42, 'layout' => null],
            ]),
        ]);
        $records = [['text' => 'alpha', 'layout' => null]];

        $t->throws(RuntimeException::class, static function () use ($pipeline, &$records): array {
            return $pipeline->runOwned($records);
        });
        $t->same([], $records);
    },

    'pdf semantic pipeline rejects duplicate processor names' => static function (TestRunner $t): void {
        $t->throws(RuntimeException::class, static fn (): PdfSemanticRecordPipeline => new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('same', static fn (array $records): array => $records),
            new PdfCallableSemanticRecordProcessor('same', static fn (array $records): array => $records),
        ]));
    },

    'pdf semantic pipeline rejects malformed processor output' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('invalid', static fn (array $records): array => [['text' => 42]]),
        ]);

        $t->throws(RuntimeException::class, static fn (): array => $pipeline->run([
            ['text' => 'alpha', 'layout' => null],
        ]));
    },

    'pdf semantic pipeline normalizes processor records without retaining private fields' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('normalize', static fn (array $records): array => [
                7 => [
                    'text' => $records[0]['text'],
                    'layout' => $records[0]['layout'],
                    'sourcePdfOutputPage' => 3,
                    'processorPrivateField' => 'discarded',
                ],
            ]),
        ]);

        $result = $pipeline->run([['text' => 'alpha', 'layout' => ['page' => 3]]]);

        $t->same([
            [
                'text' => 'alpha',
                'layout' => ['page' => 3],
                'sourcePdfOutputPage' => 3,
            ],
        ], $result['records']);
    },

    'pdf semantic pipeline rejects an invalid output page during in-place normalization' => static function (TestRunner $t): void {
        $pipeline = new PdfSemanticRecordPipeline([
            new PdfCallableSemanticRecordProcessor('invalid-page', static fn (array $records): array => [[
                'text' => $records[0]['text'],
                'layout' => $records[0]['layout'],
                'sourcePdfOutputPage' => 0,
            ]]),
        ]);

        $t->throws(RuntimeException::class, static fn (): array => $pipeline->run([
            ['text' => 'alpha', 'layout' => null],
        ]));
    },
];
