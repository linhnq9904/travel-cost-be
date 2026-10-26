<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Nette\Schema\Message;

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
                    'message' => 'Idempotency-Key đã được dùng với dữ liệu khác.',
                ], 409);
            }

            $existingExpense = Expense::query()
                ->useWritePdo()
                ->where('trip_id', $data['trip_id'])
                ->where('id', $existingKey->expense_id)
                ->first();

            if (!$existingExpense) {
                return response()->json([
                    'message' => 'Không tìm thấy khoản chi gắn với key này.',
                ], 409);
            }

            return response()->json([
                'message' => 'Yêu cầu đã được xử lý trước đó.',
                'data' => $existingExpense,
            ], 200);
        }
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
            'Message' => 'Expenses retrieved successfully',
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
            ->firstOrFail();

        return response()->json([
            'Message' => 'Expenses retrieved successfully',
            'data' => $expenses,
        ]);
    }
}
