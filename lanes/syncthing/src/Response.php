<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Response
{
    public const CODE_NO_ERROR = 0;
    public const CODE_GENERIC = 1;
    public const CODE_NO_SUCH_FILE = 2;
    public const CODE_INVALID_FILE = 3;

    public const ERROR_GENERIC = 'generic error';
    public const ERROR_NO_SUCH_FILE = 'no such file';
    public const ERROR_INVALID_FILE = 'file is invalid';
    public const ERROR_CLOSED = 'connection closed';

    public function __construct(
        public readonly int $id = 0,
        public readonly string $data = '',
        public readonly int $code = self::CODE_NO_ERROR,
    ) {
        if ($this->id < 0) {
            throw new \InvalidArgumentException('Response ID must not be negative');
        }
        if (!in_array($this->code, [
            self::CODE_NO_ERROR,
            self::CODE_GENERIC,
            self::CODE_NO_SUCH_FILE,
            self::CODE_INVALID_FILE,
        ], true)) {
            throw new \InvalidArgumentException('Unknown response error code');
        }
    }

    public function successful(): bool
    {
        return $this->code === self::CODE_NO_ERROR;
    }

    public function error(): ?string
    {
        return self::codeToError($this->code);
    }

    public static function codeToError(int $code): ?string
    {
        return match ($code) {
            self::CODE_NO_ERROR => null,
            self::CODE_NO_SUCH_FILE => self::ERROR_NO_SUCH_FILE,
            self::CODE_INVALID_FILE => self::ERROR_INVALID_FILE,
            default => self::ERROR_GENERIC,
        };
    }

    public static function errorToCode(null|string|\Throwable $error): int
    {
        if ($error === null) {
            return self::CODE_NO_ERROR;
        }

        $message = $error instanceof \Throwable ? $error->getMessage() : $error;

        return match ($message) {
            self::ERROR_NO_SUCH_FILE => self::CODE_NO_SUCH_FILE,
            self::ERROR_INVALID_FILE => self::CODE_INVALID_FILE,
            default => self::CODE_GENERIC,
        };
    }

    public static function errorResponse(int $id, null|string|\Throwable $error): self
    {
        return new self($id, '', self::errorToCode($error));
    }
}
