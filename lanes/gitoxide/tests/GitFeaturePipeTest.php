<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitFeaturePipe;
use PortLibs\Gitoxide\GitFeaturePipeException;

$catch = static function (string $class, callable $callback): Throwable {
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $class) {
            return $throwable;
        }

        $actualClass = $throwable::class;
        throw new RuntimeException("Expected {$class}, got {$actualClass}: {$throwable->getMessage()}");
    }

    throw new RuntimeException("Expected {$class} was not thrown");
};

return [
    'upstream pipe.rs threaded_read_to_end' => static function (TestRunner $t): void {
        [$writer, $reader] = GitFeaturePipe::unidirectional(0);

        $message = 'Hello, world!';
        $writer->writeAll($message);
        $writer->close();

        $t->same($message, $reader->readToString());
    },
    'upstream pipe.rs lack_of_reader_fails_with_broken_pipe' => static function (TestRunner $t) use ($catch): void {
        [$writer, $reader] = GitFeaturePipe::unidirectional(0);
        $reader->close();

        $err = $catch(GitFeaturePipeException::class, static fn () => $writer->writeAll('must fail'));
        $t->same(GitFeaturePipeException::BROKEN_PIPE, $err->kind());
    },
    'upstream pipe.rs line_reading_one_by_one' => static function (TestRunner $t): void {
        [$writer, $reader] = GitFeaturePipe::unidirectional(2);
        $writer->writeAll("a\n");
        $writer->writeAll("b\nc");
        $writer->close();

        foreach (["a\n", "b\n", 'c'] as $expected) {
            $line = $reader->readLine();
            $t->same(strlen($expected), strlen($line));
            $t->same($expected, $line);
        }
    },
    'upstream pipe.rs line_reading' => static function (TestRunner $t): void {
        [$writer, $reader] = GitFeaturePipe::unidirectional(2);
        $writer->writeAll("a\n");
        $writer->writeAll("b\nc\n");
        $writer->close();

        $t->same(['a', 'b', 'c'], $reader->lines());
    },
    'upstream pipe.rs writer_can_inject_errors' => static function (TestRunner $t) use ($catch): void {
        [$writer, $reader] = GitFeaturePipe::unidirectional(1);
        $writer->injectReadError('the error');
        $err = $catch(RuntimeException::class, static fn () => $reader->read(1));
        $t->same('the error', $err->getMessage(), 'using Read trait, errors are propagated');

        $writer->injectReadError('the error');
        $err = $catch(RuntimeException::class, static fn () => $reader->fillBuffer());
        $t->same('the error', $err->getMessage(), 'using BufRead trait, errors are propagated');
    },
    'upstream pipe.rs continue_on_empty_writes' => static function (TestRunner $t): void {
        [$writer, $reader] = GitFeaturePipe::unidirectional(2);
        $writer->writeAll('');
        $input = 'hello';
        $writer->writeAll($input);

        $buf = $reader->read(strlen($input));
        $t->same(strlen($input), strlen($buf));
        $t->same($input, $buf);
    },
    'upstream pipe.rs small_reads' => static function (TestRunner $t): void {
        $blockSize = 20;
        $blockCount = 20;
        [$writer, $reader] = GitFeaturePipe::unidirectional(4);
        for ($i = 0; $i < $blockCount; $i++) {
            $writer->writeAll(str_repeat("\0", $blockSize));
        }
        $writer->close();

        $smallReadSize = intdiv($blockSize, 2);
        $bytesRead = 0;
        while (true) {
            $chunk = $reader->read($smallReadSize);
            if ($chunk === '') {
                break;
            }
            $bytesRead += strlen($chunk);
        }

        $t->same($blockCount * $blockSize, $bytesRead);
    },
];
