<?php

namespace App\Models;

use DateTime;

/**
 * Class OrderSummary
 * @package App\Models
 */
class OrderSummary
{
    /**
     * @var DateTime
     */
    public $startDate;

    /**
     * @var DateTime
     */
    public $endDate;

    /**
     * @var double
     */
    public $amount;

    /**
     * @var double
     */
    public $totalSales;

    /**
     * @var OrderItem[]
     */
    public $items = [];
}