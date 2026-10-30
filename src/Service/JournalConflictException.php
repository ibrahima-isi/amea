<?php
namespace Amea\Service;

final class JournalConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cette publication a été modifiée. Rechargez-la avant de poursuivre.');
    }
}
