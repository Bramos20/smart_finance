import { Head, usePage, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

export default function Index() {
    const { accounts, balances, tx, errors } = usePage().props;
    const { data, setData, post, processing } = useForm({
        amount: 1000,
        provider: 'pesapal'
    });

    const onSubmit = (e) => {
        e.preventDefault();
        post('/deposits/initiate', {
            onError: (errors) => {
                console.log('Deposit errors:', errors);
            }
        });
    };

    const formatCurrency = (amount) => {
        return `KES ${Number(amount || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
    };

    return (
        <AuthenticatedLayout>
            <div className="p-6 space-y-6">
                <Head title="Dashboard" />
                
                {/* Header with Quick Actions */}
                <section className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold">Dashboard</h1>
                        <p className="text-gray-600">Manage your finances in one place</p>
                    </div>
                    <div className="flex gap-2">
                        <Link 
                            href="/bills" 
                            className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
                        >
                            Manage Bills
                        </Link>
                        <Link 
                            href="/rules" 
                            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                        >
                            Edit Allocation
                        </Link>
                    </div>
                </section>
                
                {/* Account Balances */}
                <section>
                    <h2 className="text-xl font-semibold mb-4">Account Balances</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {accounts.map(a => (
                            <div key={a.id} className="bg-white rounded-2xl shadow-sm border p-6">
                                <div className="text-sm text-gray-500 mb-1">{a.name}</div>
                                <div className="text-2xl font-semibold text-gray-900">
                                    {formatCurrency(balances[a.slug])}
                                </div>
                                {a.slug === 'bills' && (
                                    <div className="mt-2">
                                        <Link 
                                            href="/bills" 
                                            className="text-sm text-blue-600 hover:text-blue-800"
                                        >
                                            Manage bills →
                                        </Link>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </section>

                {/* Deposit Section */}
                <section className="bg-white rounded-lg shadow-sm border p-6">
                    <h2 className="text-xl font-semibold mb-4">Make a Deposit</h2>
                    
                    {/* Show errors */}
                    {errors.deposit && (
                        <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                            {errors.deposit}
                        </div>
                    )}
                    
                    <form onSubmit={onSubmit} className="flex flex-wrap items-end gap-4">
                        <div className="flex flex-col min-w-0 flex-1 sm:flex-none sm:w-40">
                            <label className="text-sm font-medium text-gray-700 mb-1">Amount (KES)</label>
                            <input 
                                name="amount" 
                                type="number" 
                                min="1" 
                                step="1" 
                                className="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                value={data.amount}
                                onChange={e => setData('amount', e.target.value)}
                            />
                        </div>
                        
                        <div className="flex flex-col min-w-0 flex-1 sm:flex-none sm:w-40">
                            <label className="text-sm font-medium text-gray-700 mb-1">Provider</label>
                            <select 
                                name="provider" 
                                className="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                value={data.provider}
                                onChange={e => setData('provider', e.target.value)}
                            >
                                <option value="pesapal">Pesapal (M-Pesa/Cards)</option>
                                <option value="flutterwave">Flutterwave (PayPal/International)</option>
                            </select>
                        </div>
                        
                        <button 
                            type="submit"
                            disabled={processing}
                            className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            {processing ? (
                                <>
                                    <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing...
                                </>
                            ) : (
                                'Deposit Now'
                            )}
                        </button>
                    </form>
                    
                    <div className="mt-4 p-4 bg-blue-50 rounded-lg">
                        <div className="text-sm text-blue-800">
                            <strong>How it works:</strong> Your deposit will be automatically split according to your allocation rules. 
                            A service fee of KES 2 will be deducted from each deposit.
                        </div>
                    </div>
                </section>

                {/* Recent Transactions */}
                <section className="bg-white rounded-lg shadow-sm border p-6">
                    <h2 className="text-xl font-semibold mb-4">Recent Transactions</h2>
                    {tx.length === 0 ? (
                        <div className="text-center py-8 text-gray-500">
                            <p>No transactions yet. Make your first deposit to get started!</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="text-left text-gray-500 text-sm border-b">
                                        <th className="pb-2">Date</th>
                                        <th className="pb-2">Provider</th>
                                        <th className="pb-2">Status</th>
                                        <th className="pb-2 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tx.map(t => (
                                        <tr key={t.id} className="border-b border-gray-100">
                                            <td className="py-3 text-sm text-gray-600">
                                                {new Date(t.created_at).toLocaleDateString('en-KE', {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit'
                                                })}
                                            </td>
                                            <td className="py-3">
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 uppercase">
                                                    {t.provider}
                                                </span>
                                            </td>
                                            <td className="py-3">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    t.status === 'succeeded' ? 'bg-green-100 text-green-800' :
                                                    t.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                    'bg-yellow-100 text-yellow-800'
                                                }`}>
                                                    {t.status}
                                                </span>
                                            </td>
                                            <td className="py-3 text-right font-semibold text-gray-900">
                                                {formatCurrency(t.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
        
    );
}