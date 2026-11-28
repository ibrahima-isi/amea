<?php

declare(strict_types=1);

namespace Amea\Model;

enum CommunicationStatus: string
{
    case QUEUED = 'QUEUED';
    case SENDING = 'SENDING';
    case PAUSED_LIMIT = 'PAUSED_LIMIT';
    case COMPLETED = 'COMPLETED';
    case COMPLETED_WITH_ERRORS = 'COMPLETED_WITH_ERRORS';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'En attente',
            self::SENDING => 'En cours',
            self::PAUSED_LIMIT => 'En pause (limite quotidienne)',
            self::COMPLETED => 'Terminé',
            self::COMPLETED_WITH_ERRORS => 'Terminé avec erreurs',
            self::CANCELLED => 'Annulé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::QUEUED, self::CANCELLED => 'bg-secondary',
            self::SENDING => 'bg-info text-dark',
            self::PAUSED_LIMIT => 'bg-warning text-dark',
            self::COMPLETED => 'bg-success',
            self::COMPLETED_WITH_ERRORS => 'bg-danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labelsMap(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->label();
        }
        return $map;
    }

    /**
     * @return array<string, string>
     */
    public static function badgesMap(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->badgeClass();
        }
        return $map;
    }
}
