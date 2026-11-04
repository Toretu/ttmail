<?php
declare(strict_types=1);

namespace Mailings\Graph;

final class GraphException extends \RuntimeException
{
    /** @param int $httpStatus HTTP status from Microsoft, or 0 when Microsoft could not be reached */
    public function __construct(string $message, public readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }
}
