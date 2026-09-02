<?php

namespace App\Services;

use App\Models\UtilityReport;

readonly class UtilityReportSubmissionResult
{
    public function __construct(
        public UtilityReport $report,
        public bool $duplicate,
    ) {}
}
