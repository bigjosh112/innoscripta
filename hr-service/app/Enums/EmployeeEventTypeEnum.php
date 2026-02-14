<?php

namespace App\Enums;

enum EmployeeEventTypeEnum: string
{
    case CREATED = 'EmployeeCreated';
    case UPDATED = 'EmployeeUpdated';
    case DELETED = 'EmployeeDeleted';
}

