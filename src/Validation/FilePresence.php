<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

/**
 * Answers whether an upload was submitted for a field.
 *
 * kaly-forms never reads uploaded files itself (no PSR-7/Symfony
 * coupling); the application provides this adapter from its request.
 */
interface FilePresence
{
    public function has(string $field): bool;
}
