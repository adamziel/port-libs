<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitFeaturePipe
{
    /**
     * Returns the write and read ends of an in-memory byte pipe.
     *
     * @return array{0: GitFeaturePipeWriter, 1: GitFeaturePipeReader}
     */
    public static function unidirectional(int $inFlightWrites): array
    {
        if ($inFlightWrites < 0) {
            throw new \InvalidArgumentException('in-flight writes must be zero or greater');
        }

        $state = new GitFeaturePipeState($inFlightWrites);

        return [
            new GitFeaturePipeWriter($state),
            new GitFeaturePipeReader($state),
        ];
    }

    private function __construct()
    {
    }
}

final class GitFeaturePipeException extends \RuntimeException
{
    public const BROKEN_PIPE = 'BrokenPipe';

    public function __construct(
        private readonly string $kind,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function brokenPipe(): self
    {
        return new self(self::BROKEN_PIPE, 'reader end of pipe is closed');
    }

    public function kind(): string
    {
        return $this->kind;
    }
}

final class GitFeaturePipeWriter
{
    private bool $open = true;

    public function __construct(private readonly GitFeaturePipeState $state)
    {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function write(string $bytes): int
    {
        $this->ensureOpen();
        $this->state->sendBytes($bytes);

        return strlen($bytes);
    }

    public function writeAll(string $bytes): void
    {
        $written = $this->write($bytes);
        if ($written !== strlen($bytes)) {
            throw new \RuntimeException('short write to pipe');
        }
    }

    public function injectReadError(string|\Throwable $error): void
    {
        $this->ensureOpen();
        $this->state->sendError(is_string($error) ? new \RuntimeException($error) : $error);
    }

    public function close(): void
    {
        if (!$this->open) {
            return;
        }

        $this->open = false;
        $this->state->closeWriter();
    }

    private function ensureOpen(): void
    {
        if (!$this->open) {
            throw new \RuntimeException('writer end of pipe is closed');
        }
    }
}

final class GitFeaturePipeReader
{
    private string $buffer = '';
    private bool $open = true;

    public function __construct(private readonly GitFeaturePipeState $state)
    {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function read(int $bytes): string
    {
        $this->ensureOpen();
        if ($bytes < 0) {
            throw new \InvalidArgumentException('read length must be zero or greater');
        }

        $out = '';
        while (strlen($out) < $bytes) {
            if ($this->buffer === '') {
                $item = $this->state->receive();
                if ($item === null) {
                    break;
                }
                if ($item['type'] === 'error') {
                    throw $item['error'];
                }

                $this->buffer = $item['bytes'];
                if ($this->buffer === '') {
                    continue;
                }
            }

            $remaining = $bytes - strlen($out);
            $take = min($remaining, strlen($this->buffer));
            $out .= substr($this->buffer, 0, $take);
            $this->buffer = substr($this->buffer, $take);
        }

        return $out;
    }

    public function readToString(): string
    {
        $out = '';
        while (true) {
            $chunk = $this->read(8192);
            if ($chunk === '') {
                return $out;
            }

            $out .= $chunk;
        }
    }

    public function fillBuffer(): string
    {
        $this->ensureOpen();
        if ($this->buffer === '') {
            $item = $this->state->receive();
            if ($item === null) {
                return '';
            }
            if ($item['type'] === 'error') {
                throw $item['error'];
            }

            $this->buffer = $item['bytes'];
        }

        return $this->buffer;
    }

    public function consume(int $bytes): void
    {
        $this->ensureOpen();
        if ($bytes < 0) {
            throw new \InvalidArgumentException('consume length must be zero or greater');
        }

        $this->buffer = substr($this->buffer, min($bytes, strlen($this->buffer)));
    }

    public function readLine(): string
    {
        $line = '';
        while (true) {
            $available = $this->fillBuffer();
            if ($available === '') {
                return $line;
            }

            $newlinePosition = strpos($available, "\n");
            if ($newlinePosition !== false) {
                $take = $newlinePosition + 1;
                $line .= substr($available, 0, $take);
                $this->consume($take);

                return $line;
            }

            $line .= $available;
            $this->consume(strlen($available));
        }
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = [];
        while (true) {
            $line = $this->readLine();
            if ($line === '') {
                return $lines;
            }

            if (str_ends_with($line, "\n")) {
                $line = substr($line, 0, -1);
                if (str_ends_with($line, "\r")) {
                    $line = substr($line, 0, -1);
                }
            }
            $lines[] = $line;
        }
    }

    public function close(): void
    {
        if (!$this->open) {
            return;
        }

        $this->open = false;
        $this->buffer = '';
        $this->state->closeReader();
    }

    private function ensureOpen(): void
    {
        if (!$this->open) {
            throw new \RuntimeException('reader end of pipe is closed');
        }
    }
}

/**
 * @internal
 */
final class GitFeaturePipeState
{
    private bool $readerOpen = true;
    private bool $writerOpen = true;

    /**
     * @var list<array{type: 'bytes', bytes: string}|array{type: 'error', error: \Throwable}>
     */
    private array $queue = [];

    public function __construct(private readonly int $inFlightWrites)
    {
    }

    public function sendBytes(string $bytes): void
    {
        $this->ensureReaderOpen();
        $this->queue[] = ['type' => 'bytes', 'bytes' => $bytes];
    }

    public function sendError(\Throwable $error): void
    {
        $this->ensureReaderOpen();
        $this->queue[] = ['type' => 'error', 'error' => $error];
    }

    /**
     * @return array{type: 'bytes', bytes: string}|array{type: 'error', error: \Throwable}|null
     */
    public function receive(): ?array
    {
        if ($this->queue === []) {
            return null;
        }

        return array_shift($this->queue);
    }

    public function closeReader(): void
    {
        $this->readerOpen = false;
        $this->queue = [];
    }

    public function closeWriter(): void
    {
        $this->writerOpen = false;
    }

    public function inFlightWrites(): int
    {
        return $this->inFlightWrites;
    }

    private function ensureReaderOpen(): void
    {
        if (!$this->readerOpen) {
            throw GitFeaturePipeException::brokenPipe();
        }
    }
}
