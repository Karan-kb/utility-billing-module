<?php



namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;

use App\Http\Requests\OpeningBalanceEntry\StoreRequest;
use App\Http\Requests\OpeningBalanceEntry\UpdateRequest;

use Illuminate\Http\Request;
use App\Repositories\Interfaces\OpeningBalanceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;

class OpeningBalanceController extends Controller
{
    protected $repository;

    public function __construct(OpeningBalanceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function store(StoreRequest $request)
    {
        try {
            $data = $request->validated();
            $result = $this->repository->store($data);

            return response()->json([
                'message' => 'Opening Balance Entry created successfully',
                'data' => $result
            ], 201);
        } catch (Exception $e) {
            Log::error('OpeningBalance store error: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to create Opening Balance Entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 public function change(UpdateRequest $request)
    {
        try {
           
            $validatedData = $request->validated();
            
            $result = $this->repository->changeAll($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Opening balance updated successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function index()
    {
        try {
            $result = $this->repository->getAll();
            return response()->json($result);
        } catch (Exception $e) {
            Log::error('OpeningBalance index error: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to fetch Opening Balance Entries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  public function show()
{
    try {
        $result = $this->repository->getByFiscalYear();
        return response()->json($result);
    } catch (Exception $e) {
        Log::error('OpeningBalance show error: '.$e->getMessage());

        return response()->json([
            'message' => 'Failed to fetch Opening Balance',
            'error' => $e->getMessage()
        ], 500);
    }
}
   public function destroy()
{
    try {
        $this->repository->deleteAll();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    } catch (Exception $e) {
        Log::error('OpeningBalance delete error: '.$e->getMessage());

        return response()->json([
            'message' => 'Failed to delete Opening Balance',
            'error' => $e->getMessage()
        ], 500);
    }
}
}