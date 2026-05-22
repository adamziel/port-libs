<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CommitTrailer
{
    public function __construct(
        public readonly string $token,
        public readonly string $value,
    ) {
        if ($token === '') {
            throw new \InvalidArgumentException('Commit trailer token cannot be empty');
        }
    }

    public function isSignedOffBy(): bool
    {
        return strcasecmp($this->token, 'Signed-off-by') === 0;
    }

    public function isCoAuthoredBy(): bool
    {
        return strcasecmp($this->token, 'Co-authored-by') === 0;
    }

    public function isAckedBy(): bool
    {
        return strcasecmp($this->token, 'Acked-by') === 0;
    }

    public function isReviewedBy(): bool
    {
        return strcasecmp($this->token, 'Reviewed-by') === 0;
    }

    public function isTestedBy(): bool
    {
        return strcasecmp($this->token, 'Tested-by') === 0;
    }

    public function isAuthorAttribution(): bool
    {
        return $this->isSignedOffBy() || $this->isCoAuthoredBy();
    }

    public function isAttribution(): bool
    {
        return $this->isAuthorAttribution()
            || $this->isAckedBy()
            || $this->isReviewedBy()
            || $this->isTestedBy();
    }
}
