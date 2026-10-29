<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class TripBalanceController extends Controller
{
    public function index(Request $request)
    {
        $tripId = $request->route('tripId');
        $expenses = Expense::query()
            ->where('trip_id', $tripId)
            ->whereHas('participants')
            ->with('participants')
            ->get();

        $balances = [];
        foreach ($expenses as $expense) {
            $payerId = strtolower($expense->paid_by);
            $payerId = $expense->paid_by;
            $balances[$payerId] ??= 0;
            [$whole, $fraction] = array_pad(
                explode('.', (string) $expense->amount, 2),
                2,
                ''
            );

            $totalUnits = (int) $whole * 100
                + (int) str_pad($fraction, 2, '0');
            $balances[$payerId] += $totalUnits;
            foreach ($expense->participants as $participant) {
                $userId = strtolower($participant->user_id);
                $userId = $participant->user_id;
                $balances[$userId] ??= 0;
                [$whole, $fraction] = array_pad(
                    explode('.', (string) $participant->share_amount, 2),
                    2,
                    ''
                );

                $shareUnits = (int) $whole * 100
                    + (int) str_pad($fraction, 2, '0');
                $balances[$userId] -= $shareUnits;
            }
        }
        $result = [];
        foreach ($balances as $userId => $balanceUnits) {
            $sign = $balanceUnits < 0 ? '-' : '';
            $absoluteUnits = abs($balanceUnits);
            $amount = $sign
                . intdiv($absoluteUnits, 100)
                . '.'
                . str_pad((string) ($absoluteUnits % 100), 2, '0', STR_PAD_LEFT);
            $result[] = [
                'user_id' => $userId,
                'balance' => $amount,
            ];
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
