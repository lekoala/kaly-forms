<?php

declare(strict_types=1);

namespace Kaly\Forms\Interaction;

/** Metadata only: the application/browser enhancement owns the actual fetch protocol. */
final readonly class RemoteOptions
{
    public function __construct(
        public string $endpoint,
        public int $minChars = 2,
        public string $queryParam = 'q',
        #[\SensitiveParameter]
        public ?string $csrfToken = null,
        public string $csrfHeader = 'X-CSRF-Token',
    ) {}
}
