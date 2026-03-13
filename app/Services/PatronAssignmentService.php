<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Patron;
use App\Models\Table;

/**
 * Service to handle patron assignment when new orders arrive.
 */
class PatronAssignmentService
{
    /**
     * Default duration (hours) to look back for patron matching by name.
     */
    const PATRON_MATCH_HOURS = 24;

    /**
     * Resolve or create a Patron for an incoming order based on the assignment algorithm.
     *
     * @param Event $event
     * @param string|null $name The requester name (e.g., from quiz app)
     * @param Table|null $table The table associated with the order
     * @return Patron|null Returns null if no patron assignment is needed
     */
    public function resolvePatron(Event $event, ?string $name = null, ?Table $table = null): ?Patron
    {
        // 1. Named Orders (Quiz App)
        if ($name && trim($name) !== '') {
            return $this->resolveNamedPatron($event, trim($name));
        }

        // 2. Anonymous Orders (Table QR Scan)
        if ($table) {
            return $this->resolveTablePatron($event, $table);
        }

        return null;
    }

    /**
     * Resolve patron by name. If a patron with the same name has placed an order
     * within the configured time window, reuse them.
     *
     * @param Event $event
     * @param string $name
     * @return Patron
     */
    protected function resolveNamedPatron(Event $event, string $name): Patron
    {
        $cutoff = now()->subHours(self::PATRON_MATCH_HOURS);

        // Look for an existing patron with this name who has orders recently
        $patron = $event->patrons()
            ->where('name', $name)
            ->whereHas('orders', function ($query) use ($cutoff) {
                $query->where('created_at', '>=', $cutoff);
            })
            ->latest()
            ->first();

        if ($patron) {
            return $patron;
        }

        // Create a new patron
        $patron = new Patron();
        $patron->name = $name;
        $patron->event()->associate($event);
        $patron->save();

        return $patron;
    }

    /**
     * Resolve patron for anonymous table orders.
     * If the last patron at this table has unpaid orders, reuse them.
     * Otherwise, create a new patron.
     *
     * @param Event $event
     * @param Table $table
     * @return Patron
     */
    protected function resolveTablePatron(Event $event, Table $table): Patron
    {
        // Check the last patron assigned to this table
        $lastPatron = $table->getLatestPatron();

        if ($lastPatron && $lastPatron->hasUnpaidOrders()) {
            return $lastPatron;
        }

        // Create a new patron for this table
        $patron = new Patron();
        $patron->event()->associate($event);
        $patron->table()->associate($table);
        $patron->save();

        return $patron;
    }

    /**
     * Find or create a table by table_number for an event.
     * Used when remote orders arrive with an unknown table number.
     *
     * @param Event $event
     * @param int $tableNumber
     * @return Table
     */
    public function findOrCreateTable(Event $event, int $tableNumber): Table
    {
        $table = $event->tables()
            ->withoutTrashed()
            ->where('table_number', $tableNumber)
            ->first();

        if ($table) {
            return $table;
        }

        // Create a new table
        $table = new Table();
        $table->table_number = $tableNumber;
        $table->name = 'Table ' . $tableNumber;
        $table->event()->associate($event);
        $table->save();

        return $table;
    }
}
