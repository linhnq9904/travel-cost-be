<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategory\StoreExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategory\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\ExpenseCategoryResource;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function store(StoreExpenseCategoryRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData['trip_id'] = $request->route('tripId');
        $category = ExpenseCategory::create($validatedData);
        return response()->json(['message' => 'Expense category created successfully', 'data' => new ExpenseCategoryResource($category)], 201);
    }

    public function index(Request $request)
    {
        $categories = ExpenseCategory::orderBy('name')->where('trip_id', $request->route('tripId'))->get();
        return response()->json(['data' => ExpenseCategoryResource::collection($categories)], 200);
    }
    public function update(UpdateExpenseCategoryRequest $request)
    {
        $category = ExpenseCategory::where('id', $request->route('categoryId'))
            ->where('trip_id', $request->route('tripId'))
            ->firstOrFail();

        $validated = $request->validated();
        $category->update($validated);
        return response()->json(['data' => new ExpenseCategoryResource($category)], 200);
    }
    public function destroy(Request $request)
    {
        $category = ExpenseCategory::where('id', $request->route('categoryId'))
            ->where('trip_id', $request->route('tripId'))
            ->firstOrFail();
        Gate::authorize('delete', $category);
        $category->expenses()->exists();
        $category->delete();
        return response()->json(['message' => 'Expense category deleted successfully'], 200);
    }
}
