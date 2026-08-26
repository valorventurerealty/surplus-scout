<?php

namespace App\Http\Controllers;

use App\Http\Requests\CoachSalesCopilotRequest;
use App\Http\Requests\StartSalesCopilotSessionRequest;
use App\Models\SalesCopilotPlaybook;
use App\Models\SalesCopilotSession;
use App\Services\SalesCopilot\SalesCopilotEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesCopilotController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = SalesCopilotSession::query()->where('user_id',$request->user()->id)->latest('last_coached_at')->latest()->limit(12)->get();
        $playbooks = SalesCopilotPlaybook::query()->where('active',true)->where('vvr_approved',true)->orderByDesc('priority')->limit(9)->get();
        return view('sales-copilot.index', compact('sessions','playbooks'));
    }

    public function objections(): View
    {
        return view('sales-copilot.objections',['playbooks'=>SalesCopilotPlaybook::query()->where('active',true)->where('vvr_approved',true)->orderByDesc('priority')->get()]);
    }

    public function practice(): View
    {
        return view('sales-copilot.practice',['playbooks'=>SalesCopilotPlaybook::query()->where('active',true)->where('vvr_approved',true)->orderByDesc('priority')->get()]);
    }

    public function store(StartSalesCopilotSessionRequest $request, SalesCopilotEngine $engine): RedirectResponse
    {
        $data = $request->safe()->except(['prospect_statement','salesperson_previous']);
        $session = SalesCopilotSession::query()->create([...$data,'user_id'=>$request->user()->id,'status'=>'active','state'=>['concerns_raised'=>[]]]);
        $engine->coach($session,$request->string('prospect_statement')->toString(),$request->input('salesperson_previous'));
        return redirect()->route('sales-copilot.sessions.show',$session)->with('success','Live coaching session started.');
    }

    public function show(Request $request, SalesCopilotSession $session): View
    {
        $this->authorizeSession($request,$session);
        $session->load(['turns.playbook','turns.feedback','contact','surplusCase']);
        return view('sales-copilot.show', compact('session'));
    }

    public function coach(CoachSalesCopilotRequest $request, SalesCopilotSession $session, SalesCopilotEngine $engine): RedirectResponse
    {
        $this->authorizeSession($request,$session);
        abort_if($session->status !== 'active',422,'This coaching session is closed.');
        $engine->coach($session,$request->string('prospect_statement')->toString(),$request->input('salesperson_previous'));
        return redirect()->route('sales-copilot.sessions.show',$session)->with('success','Recommendation prepared.');
    }

    public function complete(Request $request, SalesCopilotSession $session): RedirectResponse
    {
        $this->authorizeSession($request,$session);
        $session->update(['status'=>'completed','completed_at'=>now()]);
        return redirect()->route('sales-copilot.index')->with('success','Coaching session completed.');
    }

    private function authorizeSession(Request $request, SalesCopilotSession $session): void
    {
        abort_unless($session->user_id === $request->user()->id || $request->user()->canManageArmory(),403);
    }
}
