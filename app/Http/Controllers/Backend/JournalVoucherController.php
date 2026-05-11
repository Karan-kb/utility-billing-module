<?php


namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalVoucher\StoreRequest;
use App\Http\Requests\JournalVoucher\UpdateRequest;
use App\Repositories\Interfaces\JournalVoucherRepositoryInterface;

class JournalVoucherController extends Controller
{
   protected $repository;

    public function __construct(JournalVoucherRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
   public function store(StoreRequest $request)
{
    $data = $request->validated();

    try {
        $tracker = $this->repository->store($data);

        return response()->json([
            'message' => 'Journal voucher created successfully',
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
public function update(UpdateRequest $request, $id)
{
    $data = $request->validated();

    $result = $this->repository->update($id, $data);

    return response()->json([
        'message' => 'Journal Voucher updated successfully',
        'data' => $result
    ]);
}
    public function index()
    {
        return $this->repository->getAll();
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