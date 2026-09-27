<?php

namespace App\Enums;

enum ReadinessSeverity: string
{
    case Blocking = 'blocking';
    case Warning = 'warning';
}
