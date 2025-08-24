📘 Smart Automated Finance Platform — Technical Documentation
________________________________________
0) Product Summary
A personal-finance automation app that:
•	Accepts deposits through Pesapal (M-Pesa, Airtel, Visa/MasterCard) and Flutterwave (PayPal, cards, bank transfers).
•	Automatically splits deposits into virtual sub-accounts (Bills, Savings, Goals, etc.) based on user rules.
•	Automatically pays bills from the Bills bucket on user-defined schedules.
•	Supports round-up savings, goal-based accounts, group savings (Chamas), and an insights dashboard.
•	Optionally supports loans (up to 50% of savings) via licensed lending partners.
•	Adds a small service charge (e.g., KES 2) per transaction.
•	Allows multiple phone numbers to deposit into the same account.
•	No custody of funds: all money is stored by Pesapal/Flutterwave; your app orchestrates logic + maintains a virtual ledger.
________________________________________
1) High-Level Architecture
1.1 Components
•	Web/Mobile App (Inertia + React): UI for deposits, rules, bills, savings, goals, chamas, insights.
•	Laravel Backend:
o	Providers Layer: Adapters for Pesapal + Flutterwave.
o	Ledger & Rules Engine: Sub-accounts, allocations, fees, round-ups.
o	Schedulers: Bill payments, chama collections, retries.
o	Webhooks: Provider callbacks for deposits/payments.
•	Database: Users, accounts, rules, ledger, bills, webhook logs.
•	Queue (Redis): Webhook jobs, reconciliations, bill runs.
1.2 Money Flow (No Custody)
1.	User/depositor initiates payment → Pesapal/Flutterwave handles collection.
2.	Provider webhook notifies backend → Laravel updates ledger + splits funds.
3.	Scheduled jobs (e.g., bill payment) → Laravel instructs Pesapal/Flutterwave to pay out.
4.	Withdrawals (if enabled) → provider disburses to user’s M-Pesa.
________________________________________
2) Key User Stories
•	US1: Define allocation rules (e.g., 40% Bills, 40% Savings, 20% Main).
•	US2: Add multiple phone numbers authorized to deposit.
•	US3: Auto-split deposits into sub-accounts.
•	US4: Bills auto-paid from Bills bucket (fallback from Main if needed).
•	US5: Create goals with targets + deadlines.
•	US6: Round-up savings on payments.
•	US7: Join group savings (Chamas).
•	US8: View insights and forecasts.
•	US9: Borrow up to 50% of Savings (later phase).
________________________________________
3) Provider Strategy
•	Pesapal → Local focus (M-Pesa, Airtel, cards).
•	Flutterwave → Global focus (PayPal, bank transfers, international cards).
•	Abstraction Layer → Unified PaymentProvider interface:
interface PaymentProvider {
    public function initiateDeposit(User $user, Money $amount, array $meta = []): ProviderIntent;
    public function handleWebhook(Request $request): ProviderEvent;
    public function payMerchant(User $user, Money $amount, array $merchantMeta): PaymentResult;
    public function disburse(User $user, Money $amount, string $destination): PayoutResult;
}
________________________________________
4) Database Design
(Same as the doc you pasted, but updated to drop Daraja-only assumptions and keep Pesapal + Flutterwave as providers.)
Key tables:
•	users, authorized_depositors, accounts, allocation_rules, transactions, ledger_entries, user_bills, goals, chamas, provider_links, webhook_events, etc.
•	Ledger enforces double-entry bookkeeping.
________________________________________
5) Core Flows
5.1 Deposit Flow
•	Depositor initiates via Pesapal/Flutterwave.
•	Provider webhook → Laravel verifies + records deposit.
•	Ledger splits amount into buckets (Bills, Savings, Main, Goals).
•	Notify user → show balances.
5.2 Bill Payment
•	Scheduler runs daily.
•	If bill is due → debit Bills bucket → call payMerchant() via Pesapal/Flutterwave.
•	Update ledger + notify user.
5.3 Withdrawal (if enabled)
•	User requests → call provider disburse() → M-Pesa or bank.
•	Webhook confirms → ledger updated.
5.4 Chama
•	Scheduler collects contributions from members (via Pesapal STK push or Flutterwave).
•	Funds credited into Chama pool.
________________________________________
6) Allocation & Round-Up Logic
•	Allocation: percentages must total 100%.
•	Round-up: rounds to nearest 10/50/100 → extra credited to Savings.
________________________________________
7) Laravel Implementation Guide
•	app/Domain/Providers/ → PesapalProvider.php, FlutterwaveProvider.php.
•	Jobs/ProcessWebhook.php, Jobs/RunBill.php.
•	Events/DepositSucceeded.php.
•	Console/Kernel.php → schedules for bills, chamas, reconciliation.
•	Middleware: VerifyWebhookSignature.php.
________________________________________
8) Inertia + React (Frontend)
•	Pages: Dashboard, Deposits, Rules, Bills, Goals, Chamas, Transactions, Settings.
•	Components: AllocationEditor, BillForm, GoalProgress, ChamaMembers, RoundUpToggle.
•	UI Kit: shadcn/ui, charts via Recharts.
•	UX: enforce 100% allocation rule, Savings account locked, real-time updates via webhooks.
________________________________________
9) API Endpoints
•	/deposits/initiate → start deposit.
•	/webhooks/pesapal & /webhooks/flutterwave.
•	/allocation → CRUD rules.
•	/bills → CRUD + run.
•	/goals, /chamas, /withdrawals.
________________________________________
10) Security & Compliance
•	No custody of funds: all deposits/withdrawals go through providers.
•	Encrypt MSISDN, provider references.
•	Verify webhook signatures.
•	KYC → rely on provider; extend if needed.
•	Maintain audit trail + reconciliation with provider balances.
________________________________________
11) MVP Phasing
•	Phase 1: Deposits + allocation + dashboard.
•	Phase 2: Bills, round-ups, insights.
•	Phase 3: Goals, Chamas.
•	Phase 4: Withdrawals + loan partner integration.
________________________________________
✅ With this setup, you’re not handling funds directly. Pesapal and Flutterwave act as custodians/processors. You just maintain a virtual ledger + smart rules.
....................


From Scratch — Step‑by‑Step (Phase 1)
We’ll stand up Laravel + Breeze (Inertia React), wire Phase 1 (Deposits → Webhook →
Allocation → Balances + simple Dashboard), using provider stubs for now. Follow each task in
order.
✅ Task 1 — New Project + Breeze
# Create app (Laravel 11+)
composer create-project laravel/laravel smart-finance
cd smart-finance
# Auth scaffolding (Breeze + Inertia React)
composer require laravel/breeze --dev
php artisan breeze:install react
npm install
npm run dev
# Generate app key and run default auth migrations
php artisan key:generate
php artisan migrate
.env basics (edit these now):
APP_NAME="Smart Finance"
APP_URL=http://localhost:8000
APP_CURRENCY=KES
SERVICE_FEE_FLAT=2
# Choose default provider for dev
PAYMENT_DEFAULT=pesapal
# Pesapal (fill later with real keys)
PESAPAL_BASE_URL=https://pay.pesapal.com
PESAPAL_CONSUMER_KEY=
PESAPAL_CONSUMER_SECRET=
PESAPAL_IPN_ID=
PESAPAL_WEBHOOK_SECRET=
# Flutterwave (fill later with real keys)
FLW_BASE_URL=https://api.flutterwave.com
1
FLW_SECRET_KEY=
FLW_PUBLIC_KEY=
FLW_ENCRYPTION_KEY=
FLW_WEBHOOK_SECRET=
Tip: keep QUEUE_CONNECTION=sync for the very first boot. We’ll switch to Redis later.
✅ Task 2 — Domain Models + Migrations
Run these to generate files (we’ll paste code in the next task):
php artisan make:model Account -m
php artisan make:model AllocationRule -m
php artisan make:model Transaction -m
php artisan make:model LedgerEntry -m
php artisan make:model ProviderLink -m
php artisan make:model WebhookEvent -m
php artisan make:model AuthorizedDepositor -m
Now replace each migration with the following:
database/migrations/*_create_accounts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void {
Schema::create('accounts', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name');
$table->string('slug');
$table->string('type'); // user_bucket | system
$table->string('currency', 3)->default('KES');
$table->boolean('archived')->default(false);
$table->timestamps();
$table->unique(['user_id','slug']);
});
}
2
public function down(): void { Schema::dropIfExists('accounts'); }
};
database/migrations/*_create_allocation_rules_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void {
Schema::create('allocation_rules', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('account_id')->constrained('accounts')-
>cascadeOnDelete();
$table->unsignedDecimal('percent', 5, 2);
$table->boolean('active')->default(true);
$table->unsignedInteger('priority')->default(100);
$table->timestamps();
});
}
public function down(): void { Schema::dropIfExists('allocation_rules'); }
};
database/migrations/*_create_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void {
Schema::create('transactions', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('provider'); // pesapal | flutterwave
$table->string('direction'); // in | out
$table->string('status'); // pending | succeeded | failed
$table->unsignedDecimal('amount', 18, 2);
$table->string('currency', 3)->default('KES');
$table->string('provider_ref')->nullable();
$table->json('meta')->nullable();
$table->timestamps();
3
$table->index(['user_id','provider','direction','status']);
});
}
public function down(): void { Schema::dropIfExists('transactions'); }
};
database/migrations/*_create_ledger_entries_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void {
Schema::create('ledger_entries', function (Blueprint $table) {
$table->id();
$table->foreignId('transaction_id')->constrained()-
>cascadeOnDelete();
$table->foreignId('account_id')->constrained('accounts')-
>cascadeOnDelete();
$table->enum('entry_type', ['debit','credit']);
$table->unsignedDecimal('amount', 18, 2);
$table->string('description')->nullable();
$table->timestamps();
$table->index(['account_id','entry_type']);
});
}
public function down(): void { Schema::dropIfExists('ledger_entries'); }
};
database/migrations/*_create_provider_links_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void {
Schema::create('provider_links', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('provider');
$table->string('external_customer_id')->nullable();
$table->json('meta')->nullable();
4
$table->timestamps();
$table->unique(['user_id','provider']);
});
}
public function down(): void { Schema::dropIfExists('provider_links'); }
};
database/migrations/*_create_webhook_events_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void {
Schema::create('webhook_events', function (Blueprint $table) {
$table->id();
$table->string('provider');
$table->string('event_type')->nullable();
$table->string('signature')->nullable();
$table->json('headers');
$table->json('payload');
$table->string('status')->default('received');
$table->timestamp('processed_at')->nullable();
$table->timestamps();
});
}
public function down(): void { Schema::dropIfExists('webhook_events'); }
};
database/migrations/*_create_authorized_depositors_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void {
Schema::create('authorized_depositors', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('msisdn');
$table->boolean('active')->default(true);
$table->timestamps();
5
$table->unique(['user_id','msisdn']);
});
}
public function down(): void
{ Schema::dropIfExists('authorized_depositors'); }
};
Run them:
php artisan migrate
✅ Task 3 — Eloquent Relations
app/Models/User.php (append relations):
public function accounts(){ return $this->hasMany(\App\Models\Account::class); }
public function allocationRules(){ return $this-
>hasMany(\App\Models\AllocationRule::class); }
public function transactions(){ return $this-
>hasMany(\App\Models\Transaction::class); }
app/Models/Account.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Account extends Model {
protected $guarded = [];
public function user(){ return $this->belongsTo(User::class); }
}
app/Models/AllocationRule.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AllocationRule extends Model {
protected $guarded = [];
public function user(){ return $this->belongsTo(User::class); }
6
public function account(){ return $this->belongsTo(Account::class); }
}
app/Models/Transaction.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Transaction extends Model {
protected $guarded = [];
public function user(){ return $this->belongsTo(User::class); }
public function ledger(){ return $this->hasMany(LedgerEntry::class); }
}
app/Models/LedgerEntry.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LedgerEntry extends Model {
protected $guarded = [];
public function transaction(){ return $this-
>belongsTo(Transaction::class); }
public function account(){ return $this->belongsTo(Account::class); }
}
Create minimal models for ProviderLink , WebhookEvent , AuthorizedDepositor with
$guarded = []; and their belongsTo(User::class) where applicable.
✅ Task 4 — Seed Default Accounts + Default Allocation
php artisan make:seeder DefaultAccountsSeeder
php artisan make:listener CreateDefaultAccounts --
event=Illuminate\Auth\Events\Registered
database/seeders/DefaultAccountsSeeder.php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
7
class DefaultAccountsSeeder extends Seeder {
public function run(): void {
User::all()->each(function($user){
$defs = [
['name'=>'Main','slug'=>'main','type'=>'user_bucket'],
['name'=>'Bills','slug'=>'bills','type'=>'user_bucket'],
['name'=>'Savings','slug'=>'savings','type'=>'user_bucket'],
['name'=>'Clearing','slug'=>'clearing','type'=>'system'],
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
});
}
}
app/Listeners/CreateDefaultAccounts.php
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
];
8
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
app/Providers/EventServiceProvider.php (register listener):
protected $listen = [
\Illuminate\Auth\Events\Registered::class => [
\App\Listeners\CreateDefaultAccounts::class,
],
];
Seed any existing users (optional):
php artisan db:seed --class=DefaultAccountsSeeder
✅ Task 5 — Payments Domain (Stubs) + Services
# Folders
mkdir -p app/Domain/Payments
app/Support/Money.php
9
namespace App\Support;
final class Money {
public function __construct(public string $currency, public string $amount)
{}
}
app/Domain/Payments/DTOs.php
namespace App\Domain\Payments;
use App\Support\Money;
final class ProviderIntent {
public function __construct(public string $provider, public string $type,
public string $link){}
}
final class ProviderEvent {
public function __construct(
public string $provider,
public string $status,
public Money $amount,
public string $reference,
public array $meta = []
){}
}
app/Domain/Payments/PaymentProvider.php
namespace App\Domain\Payments;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
interface PaymentProvider {
public function initiateDeposit(User $user, Money $amount, array $meta =
[]): ProviderIntent;
public function handleWebhook(Request $request): ProviderEvent;
}
app/Domain/Payments/ProviderFactory.php
namespace App\Domain\Payments;
10
class ProviderFactory {
public function __construct(private PesapalProvider $pesapal, private
FlutterwaveProvider $flutterwave){}
public function for(string $provider): PaymentProvider {
return match($provider){
'pesapal' => $this->pesapal,
'flutterwave' => $this->flutterwave,
default => throw new \InvalidArgumentException('Unknown provider: '.
$provider),
};
}
}
app/Domain/Payments/PesapalProvider.php
namespace App\Domain\Payments;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
class PesapalProvider implements PaymentProvider {
public function initiateDeposit(User $user, Money $amount, array $meta =
[]): ProviderIntent {
// TODO real API call — dev only redirect
return new ProviderIntent('pesapal','redirect', url('/dev-payment?
ok=1'));
}
public function handleWebhook(Request $request): ProviderEvent {
// TODO signature verification & real parsing
return new ProviderEvent(
provider: 'pesapal',
status: 'succeeded',
amount: new Money(config('app.currency','KES'), (string)($request-
>input('amount') ?? '0')),
reference: $request->input('reference','dev-ref'),
meta: $request->input('meta',[])
);
}
}
app/Domain/Payments/FlutterwaveProvider.php (mirror the Pesapal stub for now)
app/Services/DepositService.php
11
namespace App\Services;
use App\Domain\Payments\{ProviderFactory, ProviderEvent};
use App\Support\Money;
use App\Models\{Transaction, LedgerEntry, Account, User};
use Illuminate\Support\Facades\DB;
class DepositService {
public function __construct(private ProviderFactory $factory){}
public function initiate(User $user, string $provider, string $amount){
$money = new Money(currency: config('app.currency','KES'), amount:
$amount);
return $this->factory->for($provider)->initiateDeposit($user, $money);
}
public function recordSuccessfulDeposit(User $user, ProviderEvent $event):
Transaction {
return \DB::transaction(function() use($user,$event){
$tx = Transaction::create([
'user_id'=>$user->id,
'provider'=>$event->provider,
'direction'=>'in',
'status'=>'succeeded',
'amount'=>$event->amount->amount,
'currency'=>$event->amount->currency,
'provider_ref'=>$event->reference,
'meta'=>$event->meta,
]);
$clearing = $user->accounts()->where('slug','clearing')-
>firstOrFail();
$rules = $user->allocationRules()->where('active',true)-
>orderBy('priority')->get();
$total = (float)$event->amount->amount;
$sum = (float)$rules->sum('percent');
if (abs($sum - 100.0) > 0.001) throw new
\RuntimeException('Allocation must sum to 100%');
LedgerEntry::create([
'transaction_id'=>$tx->id,
'account_id'=>$clearing->id,
'entry_type'=>'debit',
'amount'=>$total,
'description'=>'Deposit received',
]);
foreach ($rules as $rule) {
12
$portion = round($total * ((float)$rule->percent/100), 2);
if ($portion <= 0) continue;
LedgerEntry::create([
'transaction_id'=>$tx->id,
'account_id'=>$rule->account_id,
'entry_type'=>'credit',
'amount'=>$portion,
'description'=>"Allocation {$rule->percent}%",
]);
}
return $tx;
});
}
}
app/Services/BalanceService.php
namespace App\Services;
use App\Models\{Account, LedgerEntry, User};
class BalanceService {
public function accountBalance(Account $account): float {
$credits = (float) LedgerEntry::where('account_id',$account->id)-
>where('entry_type','credit')->sum('amount');
$debits = (float) LedgerEntry::where('account_id',$account->id)-
>where('entry_type','debit')->sum('amount');
return round($credits - $debits, 2);
}
public function userBalances(User $user): array {
return $user->accounts()->get()->mapWithKeys(fn($a)=> [ $a->slug =>
$this->accountBalance($a) ])->toArray();
}
}
✅ Task 6 — Webhooks + Job + HTTP Controllers
php artisan make:job ProcessWebhook
php artisan make:controller WebhookController
php artisan make:controller DepositController
php artisan make:controller DashboardController
php artisan make:controller AllocationController
13
app/Jobs/ProcessWebhook.php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\{WebhookEvent, User};
use App\Domain\Payments\ProviderFactory;
use App\Services\DepositService;
use Illuminate\Http\Request;
class ProcessWebhook implements ShouldQueue {
use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
public function __construct(public int $webhookId){}
public function handle(DepositService $deposits, ProviderFactory $factory):
void {
$event = WebhookEvent::findOrFail($this->webhookId);
$provider = $factory->for($event->provider);
$req = new Request(json_decode($event->payload, true) ?? []);
$pe = $provider->handleWebhook($req);
$userId = data_get($pe->meta,'user_id');
$user = User::findOrFail($userId);
$deposits->recordSuccessfulDeposit($user, $pe);
$event->update(['status'=>'processed','processed_at'=>now()]);
}
}
app/Http/Controllers/WebhookController.php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\WebhookEvent;
use App\Jobs\ProcessWebhook;
class WebhookController extends Controller {
public function pesapal(Request $request){ return $this->ingest('pesapal',
$request); }
public function flutterwave(Request $request){ return $this-
>ingest('flutterwave', $request); }
private function ingest(string $provider, Request $request){
$event = WebhookEvent::create([
'provider'=>$provider,
'event_type'=>$request->input('event','deposit'),
14
'signature'=>$request->header('X-Signature'),
'headers'=>json_encode($request->headers->all()),
'payload'=>json_encode($request->all()),
]);
// For first run, process inline to avoid queue setup
dispatch_sync(new ProcessWebhook($event->id));
return response()->json(['ok'=>true]);
}
}
app/Http/Controllers/DepositController.php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\DepositService;
class DepositController extends Controller {
public function __construct(private DepositService $deposits){}
public function initiate(Request $request){
$data = $request->validate([
'amount'=>['required','numeric','min:1'],
'provider'=>['required','in:pesapal,flutterwave']
]);
$intent = $this->deposits->initiate($request->user(), $data['provider'],
(string)$data['amount']);
return Inertia::location($intent->link); // redirect to provider (dev
stub link)
}
}
app/Http/Controllers/DashboardController.php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\BalanceService;
class DashboardController extends Controller {
public function __construct(private BalanceService $balances){}
public function index(Request $request){
$user = $request->user();
$accounts = $user->accounts()->get(['id','name','slug']);
$balances = $this->balances->userBalances($user);
15
$tx = $user->transactions()->latest()->limit(10)-
>get(['id','amount','currency','status','provider','created_at']);
return Inertia::render('Dashboard/Index',
compact('accounts','balances','tx'));
}
}
app/Http/Controllers/AllocationController.php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class AllocationController extends Controller {
public function index(Request $request){
$user = $request->user();
return Inertia::render('Rules/Index', [
'accounts' => $user->accounts()->where('type','user_bucket')-
>get(['id','name','slug']),
'rules' => $user->allocationRules()-
>with('account:id,name,slug')-
>get(['id','account_id','percent','active','priority'])
]);
}
public function store(Request $request){
$data = $request->validate([
'rules' => ['required','array','min:1'],
'rules.*.account_id' => ['required','exists:accounts,id'],
'rules.*.percent' => ['required','numeric','min:0','max:100']
]);
$sum = collect($data['rules'])->sum('percent');
if (abs($sum - 100.0) > 0.001) return back()-
>withErrors(['rules'=>"Allocation must total 100%, got {$sum}%"]);
\DB::transaction(function() use($request,$data){
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
16
}
}
routes/web.php
use App\Http\Controllers\{DashboardController, DepositController,
AllocationController};
use Illuminate\Support\Facades\Route;
Route::middleware(['auth'])->group(function(){
Route::get('/', [DashboardController::class,'index'])->name('dashboard');
Route::post('/deposits/initiate', [DepositController::class,'initiate'])-
>name('deposits.initiate');
Route::get('/rules', [AllocationController::class,'index'])-
>name('rules.index');
Route::post('/rules', [AllocationController::class,'store'])-
>name('rules.store');
});
routes/api.php
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
Route::post('/webhooks/pesapal', [WebhookController::class,'pesapal']);
Route::post('/webhooks/flutterwave', [WebhookController::class,'flutterwave']);
✅ Task 7 — Inertia React Pages
Create files:
resources/js/Pages/Dashboard/Index.jsx
resources/js/Pages/Rules/Index.jsx
resources/js/Pages/Dashboard/Index.jsx
import { Head, usePage, Link } from '@inertiajs/react';
export default function Index(){
const { accounts, balances, tx } = usePage().props;
17
return (
<div className="p-6 space-y-6">
<Head title="Dashboard" />
<section>
<h1 className="text-2xl font-bold">Balances</h1>
<div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
{accounts.map(a => (
<div key={a.id} className="rounded-2xl shadow p-4">
<div className="text-sm text-gray-500">{a.name}</div>
<div className="text-2xl font-semibold">KES
{Number(balances[a.slug] || 0).toFixed(2)}</div>
</div>
))}
</div>
</section>
<form action="/deposits/initiate" method="POST" className="flex items-end
gap-3">
<input type="hidden" name="_token"
value={document.querySelector('meta[name=csrf-token]').content} />
<div className="flex flex-col">
<label className="text-sm">Amount (KES)</label>
<input name="amount" type="number" min="1" step="1" className="border
rounded px-2 py-1" defaultValue={1000} />
</div>
<div className="flex flex-col">
<label className="text-sm">Provider</label>
<select name="provider" className="border rounded px-2 py-1">
<option value="pesapal">Pesapal</option>
<option value="flutterwave">Flutterwave</option>
</select>
</div>
<button className="rounded-xl shadow px-4 py-2">Deposit</button>
<Link href="/rules" className="underline">Edit Allocation</Link>
</form>
<section>
<h2 className="text-xl font-semibold">Recent Transactions</h2>
<div className="mt-3 overflow-x-auto">
<table className="w-full">
<thead>
<tr className="text-left text-gray-500 text-sm"><th>When</
th><th>Provider</th><th>Status</th><th>Amount</th></tr>
</thead>
<tbody>
{tx.map(t => (
<tr key={t.id} className="border-t">
<td>{new Date(t.created_at).toLocaleString()}</td>
18
<td className="uppercase">{t.provider}</td>
<td>{t.status}</td>
<td>KES {Number(t.amount).toFixed(2)}</td>
</tr>
))}
</tbody>
</table>
</div>
</section>
</div>
);
}
resources/js/Pages/Rules/Index.jsx
import { Head, useForm, usePage } from '@inertiajs/react';
export default function Rules(){
const { accounts, rules } = usePage().props;
const { data, setData, post, processing, errors } = useForm({
rules: rules.map(r => ({ account_id: r.account_id, percent: r.percent }))
});
const total = data.rules.reduce((s,r)=> s + Number(r.percent||0), 0);
const onSubmit = e => { e.preventDefault(); post('/rules'); };
return (
<div className="p-6 space-y-6">
<Head title="Allocation Rules" />
<h1 className="text-2xl font-bold">Allocation</h1>
<form onSubmit={onSubmit} className="space-y-4 max-w-xl">
{accounts.filter(a=>['main','bills','savings'].includes(a.slug)).map((a,idx)=>{
const value = data.rules[idx]?.percent ?? 0;
return (
<div key={a.id} className="flex items-center gap-3">
<div className="w-32">{a.name}</div>
<input type="number" min="0" max="100" step="0.01"
className="border rounded px-2 py-1"
value={value}
onChange={e => setData('rules', data.rules.map((r,i)=> i===idx?
{...r, account_id:a.id, percent:e.target.value}: r))}
/>
<span>%</span>
</div>
);
})}
<div className={`font-semibold ${total!==100? 'text-red-600':'textgreen-
600'}`}>Total: {total}%</div>
19
{errors.rules && <div className="text-red-600 text-sm">{errors.rules}</
div>}
<button disabled={processing || total!==100} className="rounded-xl
shadow px-4 py-2 disabled:opacity-50">Save</button>
</form>
</div>
);
}
Rebuild assets if Vite dev server isn’t running:
npm run dev
✅ Task 8 — Dev Demo Webhook (simulate a successful deposit)
1) Register/login so default accounts & rules are created.
2) From Dashboard, click Deposit (it will ‘redirect’ to the dev link).
3) Simulate a webhook (replace user_id with your ID):
curl -X POST http://localhost:8000/api/webhooks/pesapal \
-H 'Content-Type: application/json' \
-d '{
"event": "deposit.succeeded",
"amount": 1000,
"reference": "DEV-REF-001",
"meta": {"user_id": 1}
}'
4) Refresh Dashboard — balances should reflect allocations.
✅ Task 9 — Next (Still Phase 1)
Add unique index on transactions(provider_ref) to make webhooks idempotent.
Move job dispatching to real queue (Redis) and switch back from dispatch_sync .
Add service fee booking (KES 2) as an extra debit(Clearing) / credit(System Revenue) entry.
Replace provider stubs with real API calls + signature verification.
•
•
•
•
20
You’re set. Follow Task 1 → Task 8 now, and we’ll enhance from there.
21