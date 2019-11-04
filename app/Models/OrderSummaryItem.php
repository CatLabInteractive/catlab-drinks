<?php

namespace App\Models;

use DateTime;

/**
 * Class OrderSummary
 * @package App\Models
 */
class OrderSummaryItem
{
    /**
     * @var MenuItem
     */
    public $menuItem;

    /**
     * @var int
     */
    public $amount;

    /**
     * @var float
     */
    public $totalSales;

    /**
     * @var DateTime
     */
    public $startDate;

    /**
     * @var DateTime
     */
    public $endDate;

    /**
     * @var float
     */
    public $price;
}
