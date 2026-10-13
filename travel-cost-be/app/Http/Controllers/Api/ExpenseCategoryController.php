<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategory\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function store(StoreExpenseCategoryRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData['trip_id'] = $request->route('tripId');
        $category = ExpenseCategory::create($validatedData);
        return response()->json(['message' => 'Expense category created successfully', 'data' => $category], 201);
    }

    public function index(Request $request)
    {
        $categories = ExpenseCategory::orderBy('name')->where('trip_id', $request->route('tripId'))->get();
        return response()->json(['data' => $categories], 200);
    }
}
