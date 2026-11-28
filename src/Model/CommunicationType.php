<?php

declare(strict_types=1);

namespace Amea\Model;

enum CommunicationType: string
{
    case BULK = 'BULK';
    case CGU_REMINDER = 'CGU_REMINDER';
}
