<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class AllocationController extends Controller {
    public function index(Request $request){
        $user = $request->user();
        return Inertia::render('Rules/Index', [
            'accounts' => $user->accounts()->where('type','user_bucket')->get(['id','name','slug']),
            'rules' => $user->allocationRules()->with('account:id,name,slug')->get(['id','account_id','percent','active','priority'])
        ]);
    }
    public function store(Request $request){
        $data = $request->validate([
            'rules' => ['required','array','min:1'],
            'rules.*.account_id' => ['required','exists:accounts,id'],
            'rules.*.percent' => ['required','numeric','min:0','max:100']
        ]);
        $sum = collect($data['rules'])->sum('percent');
        if (abs($sum - 100.0) > 0.001) return back()->withErrors(['rules'=>"Allocation must total 100%, got {$sum}%"]);
        DB::transaction(function() use($request,$data){
            $user = $request->user();
            $user->allocationRules()->update(['active'=>false]);
            foreach ($data['rules'] as $i=>$r){
                $user->allocationRules()->updateOrCreate(
                    ['account_id'=>$r['account_id']],
                    ['percent'=>$r['percent'],'active'=>true,'priority'=>$i+1]
                );
            }
        });
        return back()->with('success','Allocation updated');
    }
}
