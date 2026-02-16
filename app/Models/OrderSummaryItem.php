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
     * @var string
     */
    public $name;

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

    /**
     * @var float
     */
    public $vat_percentage = null;

    public $net_total = null;

    public $vat_total = null;
}
