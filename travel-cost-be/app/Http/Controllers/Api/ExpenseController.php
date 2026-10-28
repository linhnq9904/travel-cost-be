<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseParticipantsRequest;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();

        $idempotencyKey = $data['idempotency_key'];
        unset($data['idempotency_key']);

        $data['trip_id'] = $request->route('tripId');
        $userId = $request->user()->id;

        ksort($data);

        $requestHash = hash(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        try {
            $expense = DB::transaction(function () use ($data, $userId, $idempotencyKey, $requestHash) {
                // Đăng ký key trước khi tạo khoản chi.
                $keyRecord = IdempotencyKey::create([
                    'user_id' => $userId,
                    'trip_id' => $data['trip_id'],
                    'key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'expense_id' => null,

                ]);

                $expense = Expense::create($data);

                // Gắn key với khoản chi vừa tạo.
                $keyRecord->update([
                    'expense_id' => $expense->id,
                ]);

                return $expense;
            });
        } catch (QueryException $e) {
            // Chỉ xử lý lỗi trùng đúng unique index của idempotency.
            $isDuplicateKey =
                (int) ($e->errorInfo[1] ?? 0) === 1062
                && str_contains(
                    $e->errorInfo[2] ?? '',
                    'idempotency_user_trip_key_unique'
                );

            if (!$isDuplicateKey) {
                throw $e;
            }

            $existingKey = IdempotencyKey::query()
                ->useWritePdo()
                ->where('user_id', $userId)
                ->where('trip_id', $data['trip_id'])
                ->where('key', $idempotencyKey)
                ->first();

            if (!$existingKey) {
                throw $e;
            }

            if ($existingKey->request_hash !== $requestHash) {
                return response()->json([
                    'message' => 'This Idempotency-Key has already been used with different request data.',
                ], 409);
            }

            $existingExpense = Expense::query()
                ->useWritePdo()
                ->where('trip_id', $data['trip_id'])
                ->where('id', $existingKey->expense_id)
                ->first();

            if (!$existingExpense) {
                return response()->json([
                    'message' => 'The expense associated with this key could not be found.',
                ], 409);
            }
            $existingExpense->load('participants');
            return response()->json([
                'message' => 'This request has already been processed.',
                'data' => $existingExpense,
            ], 200);
        }
        $expense->load('participants');
        return response()->json([
            'message' => 'Expense created successfully',
            'data' => $expense,
        ], 201);
    }
    public function index(Request $request)
    {
        $tripId = $request->route('tripId');

        $expenses = Expense::query()
            ->where('trip_id', $tripId)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'message' => 'Expenses retrieved successfully',
            'data' => $expenses,
        ]);
    }
    public function show(Request $request)
    {
        $tripId = $request->route('tripId');
        $expenseId = $request->route('expenseId');

        $expenses = Expense::query()
            ->where('trip_id', $tripId)
            ->where('id', $expenseId)
            ->with('participants')
            ->firstOrFail();

        return response()->json([
            'message' => 'Expenses retrieved successfully',
            'data' => $expenses,
        ]);
    }
    public function update(UpdateExpenseRequest $request, $tripId, $expenseId)
    {
        $tripId = $request->route('tripId');
        $expenseId = $request->route('expenseId');
        $data = $request->validated();

        $expense = DB::transaction(function () use ($tripId, $expenseId, $data) {
            $expense = Expense::query()
                ->where('trip_id', $tripId)
                ->where('id', $expenseId)
                ->lockForUpdate()
                ->firstOrFail();
            $expense->update($data);
            if ($expense->wasChanged('amount')) {
                $participants = $expense->participants()
                    ->orderBy('user_id')
                    ->get();
                if ($participants->contains('share_type', 'Fixed')) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'amount' => 'The expense amount cannot be changed while using Fixed splitting.',
                    ]);
                }
                if ($participants->isNotEmpty()) {
                    [$whole, $fraction] = array_pad(
                        explode('.', (string) $expense->amount, 2),
                        2,
                        ''
                    );

                    $totalUnits = (int) $whole * 100
                        + (int) str_pad($fraction, 2, '0');

                    $count = $participants->count();
                    $baseShare = intdiv($totalUnits, $count);
                    $remainder = $totalUnits % $count;
                    foreach ($participants as $index => $participant) {
                        $shareUnits = $baseShare
                            + ($index < $remainder ? 1 : 0);

                        $shareAmount = intdiv($shareUnits, 100)
                            . '.'
                            . str_pad(
                                (string) ($shareUnits % 100),
                                2,
                                '0',
                                STR_PAD_LEFT
                            );

                        $participant->update([
                            'share_amount' => $shareAmount,
                        ]);
                    }
                }
            }
            return $expense;
        });

        return response()->json([
            'message' => 'Expense updated successfully',
            'data' => $expense->fresh('participants'),
        ]);
    }
    public function destroy(Request $request, $tripId)
    {
        $tripId = $request->route('tripId');
        $expenseId = $request->route('expenseId');

        $expense = Expense::query()
            ->where('trip_id', $tripId)
            ->where('id', $expenseId)
            ->firstOrFail();

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }
    public function updateParticipants(UpdateExpenseParticipantsRequest $request, $tripId, $expenseId)
    {
        $tripId = $request->route('tripId');
        $expenseId = $request->route('expenseId');

        $data = $request->validated();
        $splitType = $data['split_type'];
        $participants = $data['participants'];

        usort($participants, function ($a, $b) {
            return strcmp($a['user_id'], $b['user_id']);
        });

        $expense = DB::transaction(function () use ($tripId, $expenseId, $splitType, $participants) {
            // Tìm và khóa khoản chi trong lúc chia lại.
            $expense = Expense::query()
                ->where('trip_id', $tripId)
                ->where('id', $expenseId)
                ->lockForUpdate()
                ->firstOrFail();

            // Lấy số tiền đã lưu, chuyển sang đơn vị 0.01.
            [$whole, $fraction] = array_pad(
                explode('.', (string) $expense->amount, 2),
                2,
                ''
            );

            $totalUnits = (int) $whole * 100
                + (int) str_pad($fraction, 2, '0');

            $shares = [];

            if ($splitType === 'Equal') {
                $count = count($participants);
                $baseShare = intdiv($totalUnits, $count);
                $remainder = $totalUnits % $count;

                foreach ($participants as $index => $participant) {
                    $shareUnits = $baseShare
                        + ($index < $remainder ? 1 : 0);

                    $shares[] = [
                        'user_id' => $participant['user_id'],
                        'share_type' => 'Equal',
                        'share_percent' => null,
                        'share_units' => $shareUnits,
                    ];
                }
            } elseif ($splitType === 'Percentage') {
                abort(422, 'Percentage splitting is not implemented yet.');
            } elseif ($splitType === 'Fixed') {
                $sumUnits = 0;

                foreach ($participants as $participant) {
                    [$whole, $fraction] = array_pad(
                        explode('.', (string) $participant['share_amount'], 2),
                        2,
                        ''
                    );

                    $shareUnits = (int) $whole * 100
                        + (int) str_pad($fraction, 2, '0');

                    $sumUnits += $shareUnits;
                    $shares[] = [
                        'user_id' => $participant['user_id'],
                        'share_type' => 'Fixed',
                        'share_percent' => null,
                        'share_units' => $shareUnits,
                    ];
                }
                if ($sumUnits !== $totalUnits) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'participants' => 'The total split amount must equal the expense amount.',
                    ]);
                }
            }
            // Chỉ xóa phần chia của khoản chi đang xử lý.
            $expense->participants()->delete();

            foreach ($shares as $share) {
                $shareUnits = $share['share_units'];

                $shareAmount = intdiv($shareUnits, 100)
                    . '.'
                    . str_pad(
                        (string) ($shareUnits % 100),
                        2,
                        '0',
                        STR_PAD_LEFT
                    );

                $expense->participants()->create([
                    'user_id' => $share['user_id'],
                    'share_type' => $share['share_type'],
                    'share_percent' => $share['share_percent'],
                    'share_amount' => $shareAmount,
                ]);
            }
            return $expense->load('participants');
        });

        return response()->json([
            'message' => 'Participants updated successfully',
            'data' => $expense,
        ], 200);
    }
}