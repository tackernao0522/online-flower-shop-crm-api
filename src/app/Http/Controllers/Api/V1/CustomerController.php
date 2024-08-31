<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index()
    {
        $customers = Customer::paginate(20);
        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'currentPage' => $customers->currentPage(),
                'totalPages' => $customers->lastPage(),
                'totalCount' => $customers->total()
            ]
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());
        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        $updatedCustomer = $customer->fresh();
        return response()->json([
            'id' => $updatedCustomer->id,
            'name' => $updatedCustomer->name,
            'email' => $updatedCustomer->email,
            'phoneNumber' => $updatedCustomer->phoneNumber,
            'address' => $updatedCustomer->address,
            'birthDate' => $updatedCustomer->birthDate->format('Y-m-d H:i:s'),
            'updatedAt' => $updatedCustomer->updated_at->toIso8601String(),
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(null, 204);
    }
}
