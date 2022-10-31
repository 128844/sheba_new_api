<?php

namespace Sheba\TopUp\Gateway\Statuses;

use Sheba\Helpers\ConstGetter;

class PayStationStatuses
{
    use ConstGetter;

    const SUCCESS = 'Success';
    const FAILED = 'Failed';
    const PROCESSING = 'Processing';
}