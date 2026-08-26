<?php

namespace App\Http\Controllers;

use App\Models\SalesCopilotPlaybook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SalesCopilotPlaybookController extends Controller
{
    public function index(Request $request): View
    {
        $query = SalesCopilotPlaybook::query();
        if ($request->filled('search')) $query->where(fn($q)=>$q->where('title','like','%'.$request->search.'%')->orWhere('recommended_response','like','%'.$request->search.'%'));
        return view('sales-copilot.playbooks.index',['playbooks'=>$query->orderByDesc('owner_authored')->orderByDesc('priority')->paginate(25)->withQueryString()]);
    }
    public function create(Request $request): View { $this->manage($request); return view('sales-copilot.playbooks.form',['playbook'=>new SalesCopilotPlaybook]); }
    public function edit(Request $request, SalesCopilotPlaybook $playbook): View { $this->manage($request); return view('sales-copilot.playbooks.form',compact('playbook')); }
    public function store(Request $request): RedirectResponse { $this->manage($request); $data=$this->validated($request); $playbook=SalesCopilotPlaybook::query()->create([...$data,'created_by'=>$request->user()->id,'updated_by'=>$request->user()->id]); return redirect()->route('sales-copilot.playbooks.edit',$playbook)->with('success','Playbook created.'); }
    public function update(Request $request, SalesCopilotPlaybook $playbook): RedirectResponse { $this->manage($request); $playbook->update([...$this->validated($request,$playbook),'updated_by'=>$request->user()->id]); return back()->with('success','Playbook updated.'); }
    private function manage(Request $request): void { abort_unless($request->user()->canManageArmory(),403); }
    private function validated(Request $request, ?SalesCopilotPlaybook $playbook=null): array
    {
        $data=$request->validate(['title'=>['required','string','max:255'],'category'=>['required','string','max:80'],'scenario'=>['nullable','string','max:255'],'stage'=>['required','string','max:80'],'recommended_response'=>['required','string','max:4000'],'objective'=>['required','string','max:2000'],'trigger_phrases_text'=>['required','string','max:4000'],'tones_text'=>['required','string','max:500'],'listen_for_text'=>['nullable','string','max:4000'],'branches_text'=>['nullable','string','max:4000'],'notes'=>['nullable','string','max:4000'],'priority'=>['required','integer','min:0','max:1000'],'active'=>['nullable','boolean'],'vvr_approved'=>['nullable','boolean'],'owner_authored'=>['nullable','boolean']]);
        foreach (['trigger_phrases','tones','listen_for','branches'] as $field) { $text=$data[$field.'_text']??''; $data[$field]=array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n|,/', $text) ?: []))); unset($data[$field.'_text']); }
        $data['slug']=Str::slug($data['title']).($playbook ? '-'.$playbook->id : '-'.Str::lower(Str::random(6)));
        foreach(['active','vvr_approved','owner_authored'] as $flag) $data[$flag]=$request->boolean($flag);
        return $data;
    }
}
