<?php

namespace App\Enums;

enum PrinterStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Disabled = 'disabled';
}
