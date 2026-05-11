<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankVoucher\StoreRequest;
use App\Http\Requests\BankVoucher\UpdateRequest;
use App\Repositories\Interfaces\BankVoucherRepositoryInterface;
use Illuminate\Http\Request;
use App\Services\CashBankSummaryService;
use Exception;
use Illuminate\Support\Facades\Log;

class BankVoucherController extends Controller
{
    protected $repository;

    public function __construct(BankVoucherRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function store(StoreRequest $request)
    {
        try {
            $data = $request->validated();
            $result = $this->repository->store($data);

            return response()->json([
                'message' => 'Bank Voucher created successfully',
                'data' => $result
            ], 201);
        } catch (Exception $e) {
            // Log the exception if needed
            Log::error('BankVoucher store error: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to create Bank Voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $result = $this->repository->update($id, $data);

            return response()->json([
                'message' => 'Bank Voucher updated successfully',
                'data' => $result
            ]);
        } catch (Exception $e) {
            Log::error("BankVoucher update error (ID: $id): ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to update Bank Voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            $result = $this->repository->getAll();
            return response()->json($result);
        } catch (Exception $e) {
            Log::error('BankVoucher index error: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to fetch Bank Vouchers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $result = $this->repository->find($id);
            return response()->json($result);
        } catch (Exception $e) {
            Log::error("BankVoucher show error (ID: $id): ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to fetch Bank Voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->repository->delete($id);

            return response()->json([
                'message' => 'Deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error("BankVoucher delete error (ID: $id): ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to delete Bank Voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAccountBalance($account_head_id)
    {
        try {
            $service = new CashBankSummaryService();
            $result = $service->getByAccountIds([$account_head_id]);

            return response()->json([
                'balance' => $result[0]['balance'] ?? 0
            ]);
        } catch (Exception $e) {
            Log::error("GetAccountBalance error (Account ID: $account_head_id): ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to get account balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}