<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

use RuntimeException;
use Throwable;

final class GitError extends RuntimeException
{
    private const AUTO_CHAIN_ERROR_RS = 'gix-error/tests/auto_chain_error.rs';

    /**
     * @param list<self> $children
     */
    private function __construct(
        string $message,
        private readonly ?string $location = null,
        private readonly array $children = [],
        private readonly ?self $source = null,
    ) {
        parent::__construct($message, 0, $source);
    }

    public static function message(string $message, ?string $location = null): self
    {
        return new self($message, $location);
    }

    public static function fromExnMessage(string $message, ?string $location = null): self
    {
        return self::fromExn(self::message($message, $location ?? self::fixtureLocation(9)));
    }

    public static function fromAnyError(string $message, ?string $location = null): self
    {
        return self::fromExn(self::message($message, $location ?? self::fixtureLocation(56)));
    }

    public static function fromThrowable(Throwable $throwable, ?string $location = null): self
    {
        return self::fromExn(self::fromThrowableTree($throwable, $location));
    }

    public static function fromError(string|Throwable|self $error, ?string $location = null): self
    {
        if ($error instanceof self) {
            return self::fromExn($error);
        }
        if ($error instanceof Throwable) {
            return self::fromThrowable($error, $location);
        }

        return self::fromAnyError($error, $location);
    }

    public static function fromExn(self $error): self
    {
        $frames = self::breadthFirstFrames($error);
        $source = null;

        for ($i = count($frames) - 1; $i >= 0; $i--) {
            $frame = $frames[$i];
            $source = new self($frame->getMessage(), $frame->location, [], $source);
        }

        return $source ?? new self('');
    }

    public function raise(string $message, ?string $location = null): self
    {
        return new self($message, $location, [$this]);
    }

    /**
     * @param list<self> $children
     */
    public static function raiseAll(array $children, string $message, ?string $location = null): self
    {
        foreach ($children as $child) {
            if (!$child instanceof self) {
                throw new RuntimeException('GitError::raiseAll() expects GitError children');
            }
        }

        return new self($message, $location, array_values($children));
    }

    public static function newTreeError(): self
    {
        $e1 = self::message('E1', self::fixtureLocation(70));
        $e3 = $e1->raise('E3', self::fixtureLocation(71));

        $e9 = self::message('E9', self::fixtureLocation(73));
        $e10 = $e9->raise('E10', self::fixtureLocation(74));

        $e11 = self::message('E11', self::fixtureLocation(76));
        $e12 = $e11->raise('E12', self::fixtureLocation(77));

        $e5 = self::raiseAll([$e3, $e10, $e12], 'E5', self::fixtureLocation(79));

        $e2 = self::message('E2', self::fixtureLocation(81));
        $e4 = $e2->raise('E4', self::fixtureLocation(82));

        $e7 = self::message('E7', self::fixtureLocation(84));
        $e8 = $e7->raise('E8', self::fixtureLocation(85));

        return self::raiseAll([$e5, $e4, $e8], 'E6', self::fixtureLocation(87));
    }

    public static function fromNewTreeError(string $message = 'topmost'): self
    {
        return self::fromExn(self::newTreeError()->raise($message, self::fixtureLocation(23)));
    }

    public function display(): string
    {
        return $this->getMessage();
    }

    public function displayWithLocation(): string
    {
        if ($this->location === null) {
            return $this->display();
        }

        return $this->display() . ', at ' . $this->location;
    }

    public function debugString(): string
    {
        return 'Message(' . self::rustStringLiteral($this->getMessage()) . ')';
    }

    public function debugPrettyString(): string
    {
        return "Message(\n    " . self::rustStringLiteral($this->getMessage()) . ",\n)";
    }

    public function source(): ?self
    {
        return $this->source;
    }

    /**
     * @return list<self>
     */
    public function sources(): array
    {
        $sources = [];
        for ($error = $this; $error !== null; $error = $error->source()) {
            $sources[] = $error;
        }

        return $sources;
    }

    public function probableCause(): self
    {
        return $this->source ?? $this;
    }

    /**
     * @return list<self>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    /**
     * @return list<self>
     */
    private static function breadthFirstFrames(self $root): array
    {
        $frames = [$root];
        $queue = $root->children;

        while ($queue !== []) {
            $frame = array_shift($queue);
            $frames[] = $frame;

            foreach ($frame->children as $child) {
                $queue[] = $child;
            }
        }

        return $frames;
    }

    private static function fromThrowableTree(Throwable $throwable, ?string $location): self
    {
        $children = [];
        $previous = $throwable->getPrevious();
        if ($previous !== null) {
            $children[] = self::fromThrowableTree($previous, $location);
        }

        return new self($throwable->getMessage(), $location, $children);
    }

    private static function fixtureLocation(int $line): string
    {
        return self::AUTO_CHAIN_ERROR_RS . ':' . $line;
    }

    private static function rustStringLiteral(string $value): string
    {
        $literal = '"';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            $ordinal = ord($char);

            $literal .= match ($char) {
                '\\' => '\\\\',
                '"' => '\\"',
                "\n" => '\\n',
                "\r" => '\\r',
                "\t" => '\\t',
                "\0" => '\\0',
                default => ($ordinal < 32 || $ordinal === 127)
                    ? '\\u{' . strtolower(dechex($ordinal)) . '}'
                    : $char,
            };
        }

        return $literal . '"';
    }
}
