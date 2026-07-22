<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Domain;

use InvalidArgumentException;

final class ValidationException extends InvalidArgumentException
{
    /** @param array<string, string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Lead submission is invalid.');
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
