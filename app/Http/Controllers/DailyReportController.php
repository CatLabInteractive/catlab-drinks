<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Transaction;

/**
 *
 */
class DailyReportController extends Controller
{
    public function dailyReport($organisationId, $dateString)
    {
        $date = \DateTime::createFromFormat('Ymd', $dateString);
        if (!$date) {
            return 'Date could not be parsed: ' . $date;
        }

        $organisation = Organisation::findOrFail($organisationId);
        $this->authorize('financialOverview', $organisation);

        $transactions = Transaction::select('card_transactions.*')
            ->leftJoin('cards', 'cards.id', '=', 'card_transactions.card_id')
            ->whereDate('card_transactions.created_at', '=', $date->format('Y-m-d'))
            ->where('card_transactions.transaction_type', '=', 'topup')
            ->get();

        $totalsByType = [];

        foreach ($transactions as $transaction) {
            $type = $transaction->getTypeDescription();
            if (!isset($totalsByType[$type])) {
                $totalsByType[$type] = 0;
            }
            $totalsByType[$type] += $transaction->value;
        }

        return \Response::view('reports/daily', [
            'date' => $date,
            'transactions' => $transactions,
            'totals' => $totalsByType
        ]);
    }
}
