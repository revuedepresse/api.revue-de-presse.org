<?php
declare(strict_types=1);

namespace App\Domain\Membership\Exception;

use Exception;

class MembershipException extends Exception
{
    /**
     * @param string $message
     * @param int    $errorCode
     *
     * @throws MembershipException
     */
    public function throws(string $message, int $errorCode): void
    {
        throw new self($message, $errorCode);
    }
}