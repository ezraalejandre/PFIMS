<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function store(Request $request)
    {

        $request->validate([

            'item_id'=>'required|integer',
            'project_id'=>'nullable|integer',
            'transaction_type'=>'required|string',
            'quantity'=>'required|numeric',
            'transaction_date'=>'required|date',

        ]);


        $id = DB::table('inventory_transaction_tbl')
        ->insertGetId([

            'item_id'=>$request->item_id,

            'project_id'=>$request->project_id,

            'transaction_type'=>$request->transaction_type,

            'quantity'=>$request->quantity,

            'transaction_date'=>$request->transaction_date,


        ]);


        return response()->json([

            'message'=>'Transaction saved',

            'inventory_transaction_id'=>$id

        ],201);

    }
}
