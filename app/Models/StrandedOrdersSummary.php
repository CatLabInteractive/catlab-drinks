<?php

namespace App\Models;

/**
 * Class StrandedOrdersSummary
 * Simple POPO for Charon serialization of stranded orders count
 * and the number of devices sharing remote orders.
 * @package App\Models
 */
class StrandedOrdersSummary
{
	/**
	 * @var int
	 */
	public $count = 0;

	/**
	 * Number of online devices that share remote orders (round robin when > 1).
	 * @var int
	 */
	public $active_devices = 0;
}
