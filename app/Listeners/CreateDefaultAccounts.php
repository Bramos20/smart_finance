<?php

namespace App\Listeners;
use Illuminate\Auth\Events\Registered;

class CreateDefaultAccounts {
    public function handle(Registered $event): void {
        $user = $event->user;
        $defs = [
            ['name'=>'Main','slug'=>'main','type'=>'user_bucket'],
            ['name'=>'Bills','slug'=>'bills','type'=>'user_bucket'],
            ['name'=>'Savings','slug'=>'savings','type'=>'user_bucket'],
            ['name'=>'Clearing','slug'=>'clearing','type'=>'system'],
            ['name'=>'System Revenue','slug'=>'system_revenue','type'=>'system'],
        ];
        foreach ($defs as $d) {
            $user->accounts()->firstOrCreate(
            ['slug'=>$d['slug']],
            $d + ['currency'=>config('app.currency','KES')]
            );
        }
        $alloc = [
            ['slug'=>'bills','percent'=>40],
            ['slug'=>'savings','percent'=>40],
            ['slug'=>'main','percent'=>20],
        ];
        foreach ($alloc as $a) {
            $acct = $user->accounts()->where('slug',$a['slug'])->first();
            $user->allocationRules()->updateOrCreate(
                ['account_id'=>$acct->id],
                ['percent'=>$a['percent'],'active'=>true]
            );
        }
    }
}
