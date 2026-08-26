<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesCopilotFeedbackRequest;
use App\Models\SalesCopilotTurn;
use Illuminate\Http\RedirectResponse;

class SalesCopilotFeedbackController extends Controller
{
    public function store(StoreSalesCopilotFeedbackRequest $request, SalesCopilotTurn $turn): RedirectResponse
    {
        abort_unless($turn->session()->where('user_id',$request->user()->id)->exists() || $request->user()->canManageArmory(),403);
        $response = $turn->response;
        $turn->feedback()->updateOrCreate(['user_id'=>$request->user()->id],[...$request->validated(),'original_response'=>$response['recommended_response']]);
        return back()->with('success','Coaching feedback saved.');
    }
}
