<?php

namespace App\Enums;

enum ExportFormat: string
{
    case Pdf = 'pdf';
    case Csv = 'csv';
    case Xlsx = 'xlsx';
}
