<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseAndReceivableTracker\StoreRequest;
use App\Http\Requests\ExpenseAndReceivableTracker\UpdateRequest;
use App\Repositories\Interfaces\ExpenseAndReceivableTrackerRepositoryInterface;
use Illuminate\Http\Request;

class ExpenseAndReceivableTrackerController extends Controller
{
    protected $repository;

    public function __construct(ExpenseAndReceivableTrackerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function indexExpenses(Request $request)
    {
        return $this->getTransactionsByType($request, 0);
    }

    public function storeExpense(StoreRequest $request)
    {
        return $this->storeWithType($request, 0);
    }
    public function updateExpense(UpdateRequest $request, $id)
{
    return $this->updateWithType($request, $id, 0); 
}
    // Receivables (type 1)
    public function indexReceivables(Request $request)
    {
        return $this->getTransactionsByType($request, 1);
    }

    public function storeReceivable(StoreRequest $request)
    {
        return $this->storeWithType($request, 1);
    }
public function updateReceivable(UpdateRequest $request, $id)
{
    return $this->updateWithType($request, $id, 1); 
}
    private function storeWithType(StoreRequest $request, int $type)
    {
        $data = $request->validated();
        $data['type'] = $type;

        try {
            $tracker = $this->repository->store($data);

            return response()->json([
                'message' => 'Tracker created successfully',
                'data' => $tracker
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            throw $e;
        }
    }
  
    public function updateWithType(UpdateRequest $request, $id, int $type)
{
    $data = $request->validated();
     $data['type'] = $type;

    try {
        $tracker = $this->repository->update($id, $data);

        return response()->json([
            'message' => 'Expense voucher updated successfully',
            'data' => $tracker
        ]);
    } catch (\Throwable $e) {
        if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }

        throw $e;
    }
}
    // public function index()
    // {
    //     return $this->repository->getAll();
    // }

    private function getTransactionsByType(Request $request, int $type)
    {
        $search = $request->query('search');

        $query = $this->repository->getQueryByType($type);

        if (!empty($search)) {
            $query->whereHas('items.accountHead', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhere('voucher_no', 'like', "%{$search}%");
        }

        $transactions = $query->latest()->paginate(10);

        $transactions->getCollection()->transform(function ($tracker) {
            $items = $tracker->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'account_head_id' => $item->account_head_id,
                    'account_head_name' => $item->accountHead?->name ?? null,
                    'amount' => $item->amount,
                    'bank_id' => $item->bank_id,
                    'ref_bill_no' => $item->ref_bill_no,
                    'particular' => $item->particular,
                ];
            });

            return [
                'id' => $tracker->id,
                'voucher_no' => $tracker->voucher_no,
                'type' => $tracker->type,
                'date_in_bs' => $tracker->date_in_bs,
                'date_in_ad' => $tracker->date_in_ad ?? null,
                'fiscal_year_id' => $tracker->fiscal_year_id,
                'items' => $items,
                'created_at' => $tracker->created_at,
                'updated_at' => $tracker->updated_at,
            ];
        });

        return response()->json($transactions);
    }

    public function show($id)
    {
        return $this->repository->find($id);
    }

    public function destroy($id)
    {
        $this->repository->delete($id);

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }
}