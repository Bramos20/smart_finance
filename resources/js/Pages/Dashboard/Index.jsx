import { Head, usePage, Link } from '@inertiajs/react';

export default function Index(){
    const { accounts, balances, tx } = usePage().props;
        return (
            <div className="p-6 space-y-6">
                <Head title="Dashboard" />
                <section>
                    <h1 className="text-2xl font-bold">Balances</h1>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">{accounts.map(a => (
                        <div key={a.id} className="rounded-2xl shadow p-4">
                            <div className="text-sm text-gray-500">{a.name}</div>
                            <div className="text-2xl font-semibold">
                                KES{Number(balances[a.slug] || 0).toFixed(2)}
                            </div>
                        </div>
                        ))}
                    </div>
                </section>
                <form action="/deposits/initiate" method="POST" className="flex items-endgap-3">
                    <input type="hidden" name="_token" value={document.querySelector('meta[name=csrf-token]').content} />
                    <div className="flex flex-col">
                        <label className="text-sm">Amount (KES)</label>
                        <input name="amount" type="number" min="1" step="1" className="border rounded px-2 py-1" defaultValue={1000} />
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
                                <tr className="text-left text-gray-500 text-sm"><th>When</th><th>Provider</th><th>Status</th><th>Amount</th></tr>
                            </thead>
                            <tbody>
                                {tx.map(t => (
                                <tr key={t.id} className="border-t">
                                    <td>{new Date(t.created_at).toLocaleString()}</td>
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