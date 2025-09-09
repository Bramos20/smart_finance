import { Head, usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Bills() {
    const { bills, balances, categories } = usePage().props;
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [editingBill, setEditingBill] = useState(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        category: 'utilities',
        amount: '',
        frequency: 'monthly',
        due_day: 1,
        merchant_code: '',
        account_number: '',
        auto_pay: false
    });

    const onSubmit = (e) => {
        e.preventDefault();
        if (editingBill) {
            put(`/bills/${editingBill.id}`, {
                onSuccess: () => {
                    setEditingBill(null);
                    reset();
                }
            });
        } else {
            post('/bills', {
                onSuccess: () => {
                    setShowCreateForm(false);
                    reset();
                }
            });
        }
    };

    const startEdit = (bill) => {
        setEditingBill(bill);
        setData({
            name: bill.name,
            category: bill.category,
            amount: bill.amount,
            frequency: bill.frequency,
            due_day: bill.due_day,
            merchant_code: bill.merchant_code || '',
            account_number: bill.account_number || '',
            auto_pay: bill.auto_pay
        });
        setShowCreateForm(true);
    };

    const cancelEdit = () => {
        setEditingBill(null);
        setShowCreateForm(false);
        reset();
    };

    const payNow = (billId) => {
        post(`/bills/${billId}/pay-now`, {
            preserveState: false
        });
    };

    const formatCurrency = (amount) => {
        return `KES ${Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
    };

    const getBillStatusColor = (bill) => {
        if (!bill.active) return 'bg-gray-100 text-gray-600';
        if (bill.is_due) return 'bg-red-100 text-red-800';
        return 'bg-green-100 text-green-800';
    };

    const getBillStatus = (bill) => {
        if (!bill.active) return 'Inactive';
        if (bill.is_due) return 'Due Now';
        return 'Active';
    };

    return (
        <div className="p-6 space-y-6">
            <Head title="Bills Management" />
            
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold">Bills Management</h1>
                    <p className="text-gray-600">Manage your recurring bills and automatic payments</p>
                </div>
                <button
                    onClick={() => setShowCreateForm(true)}
                    className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                >
                    Add New Bill
                </button>
            </div>

            {/* Account Balance Warning */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 className="font-semibold text-blue-800">Account Balances</h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                    <div>
                        <span className="text-sm text-blue-600">Bills Account:</span>
                        <span className="font-semibold ml-2">{formatCurrency(balances.bills || 0)}</span>
                    </div>
                    <div>
                        <span className="text-sm text-blue-600">Main Account:</span>
                        <span className="font-semibold ml-2">{formatCurrency(balances.main || 0)}</span>
                    </div>
                    <div>
                        <span className="text-sm text-blue-600">Total Available:</span>
                        <span className="font-semibold ml-2">
                            {formatCurrency((balances.bills || 0) + (balances.main || 0))}
                        </span>
                    </div>
                </div>
            </div>

            {/* Create/Edit Form Modal */}
            {showCreateForm && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                        <h2 className="text-xl font-bold mb-4">
                            {editingBill ? 'Edit Bill' : 'Add New Bill'}
                        </h2>
                        
                        <form onSubmit={onSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Bill Name
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="w-full border rounded-lg px-3 py-2"
                                    placeholder="e.g., KPLC Bill, Netflix Subscription"
                                    required
                                />
                                {errors.name && <div className="text-red-600 text-sm mt-1">{errors.name}</div>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Category
                                </label>
                                <select
                                    value={data.category}
                                    onChange={e => setData('category', e.target.value)}
                                    className="w-full border rounded-lg px-3 py-2"
                                    required
                                >
                                    {Object.entries(categories).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Amount (KES)
                                </label>
                                <input
                                    type="number"
                                    value={data.amount}
                                    onChange={e => setData('amount', e.target.value)}
                                    className="w-full border rounded-lg px-3 py-2"
                                    step="0.01"
                                    min="1"
                                    required
                                />
                                {errors.amount && <div className="text-red-600 text-sm mt-1">{errors.amount}</div>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Frequency
                                    </label>
                                    <select
                                        value={data.frequency}
                                        onChange={e => setData('frequency', e.target.value)}
                                        className="w-full border rounded-lg px-3 py-2"
                                        required
                                    >
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Due Day
                                    </label>
                                    <input
                                        type="number"
                                        value={data.due_day}
                                        onChange={e => setData('due_day', e.target.value)}
                                        className="w-full border rounded-lg px-3 py-2"
                                        min="1"
                                        max={data.frequency === 'weekly' ? '7' : '31'}
                                        required
                                    />
                                    <div className="text-xs text-gray-500 mt-1">
                                        {data.frequency === 'weekly' ? '1=Monday, 7=Sunday' : 'Day of month'}
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Merchant Code/Phone
                                </label>
                                <input
                                    type="text"
                                    value={data.merchant_code}
                                    onChange={e => setData('merchant_code', e.target.value)}
                                    className="w-full border rounded-lg px-3 py-2"
                                    placeholder="Paybill/Till number or phone"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Account Number
                                </label>
                                <input
                                    type="text"
                                    value={data.account_number}
                                    onChange={e => setData('account_number', e.target.value)}
                                    className="w-full border rounded-lg px-3 py-2"
                                    placeholder="Account/Reference number"
                                />
                            </div>

                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="auto_pay"
                                    checked={data.auto_pay}
                                    onChange={e => setData('auto_pay', e.target.checked)}
                                    className="mr-2"
                                />
                                <label htmlFor="auto_pay" className="text-sm text-gray-700">
                                    Enable automatic payment
                                </label>
                            </div>

                            <div className="flex gap-3 pt-4">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : editingBill ? 'Update Bill' : 'Add Bill'}
                                </button>
                                <button
                                    type="button"
                                    onClick={cancelEdit}
                                    className="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Bills List */}
            <div className="space-y-4">
                {bills.length === 0 ? (
                    <div className="text-center py-12 bg-gray-50 rounded-lg">
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No bills yet</h3>
                        <p className="text-gray-600 mb-4">Add your first bill to get started with automatic payments</p>
                        <button
                            onClick={() => setShowCreateForm(true)}
                            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                        >
                            Add Your First Bill
                        </button>
                    </div>
                ) : (
                    bills.map(bill => (
                        <div key={bill.id} className="bg-white border rounded-lg p-6 shadow-sm">
                            <div className="flex justify-between items-start">
                                <div className="flex-1">
                                    <div className="flex items-center gap-3 mb-2">
                                        <h3 className="text-lg font-semibold">{bill.name}</h3>
                                        <span className={`px-2 py-1 text-xs rounded-full ${getBillStatusColor(bill)}`}>
                                            {getBillStatus(bill)}
                                        </span>
                                        {bill.auto_pay && (
                                            <span className="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                                Auto-pay
                                            </span>
                                        )}
                                    </div>
                                    
                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm text-gray-600">
                                        <div>
                                            <span className="font-medium">Amount:</span>
                                            <div className="text-lg font-semibold text-gray-900">
                                                {formatCurrency(bill.amount)}
                                            </div>
                                        </div>
                                        <div>
                                            <span className="font-medium">Category:</span>
                                            <div className="capitalize">{categories[bill.category]}</div>
                                        </div>
                                        <div>
                                            <span className="font-medium">Frequency:</span>
                                            <div className="capitalize">{bill.frequency}</div>
                                        </div>
                                        <div>
                                            <span className="font-medium">Next Due:</span>
                                            <div className={bill.is_due ? 'text-red-600 font-semibold' : ''}>
                                                {bill.next_due_date ? 
                                                    new Date(bill.next_due_date).toLocaleDateString() : 
                                                    'Not scheduled'
                                                }
                                            </div>
                                        </div>
                                    </div>

                                    {(bill.merchant_code || bill.account_number) && (
                                        <div className="mt-3 text-sm text-gray-600">
                                            {bill.merchant_code && (
                                                <span className="mr-4">
                                                    <span className="font-medium">Merchant:</span> {bill.merchant_code}
                                                </span>
                                            )}
                                            {bill.account_number && (
                                                <span>
                                                    <span className="font-medium">Account:</span> {bill.account_number}
                                                </span>
                                            )}
                                        </div>
                                    )}

                                    {/* Recent Payments */}
                                    {bill.recent_payments && bill.recent_payments.length > 0 && (
                                        <div className="mt-4">
                                            <h4 className="text-sm font-medium text-gray-700 mb-2">Recent Payments</h4>
                                            <div className="space-y-1">
                                                {bill.recent_payments.slice(0, 2).map(payment => (
                                                    <div key={payment.id} className="flex justify-between items-center text-sm">
                                                        <span className="text-gray-600">
                                                            {payment.paid_at ? 
                                                                new Date(payment.paid_at).toLocaleDateString() : 
                                                                'Pending'
                                                            }
                                                        </span>
                                                        <span className={`px-2 py-1 rounded-full text-xs ${
                                                            payment.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                            payment.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                            'bg-yellow-100 text-yellow-800'
                                                        }`}>
                                                            {payment.status}
                                                        </span>
                                                        <span className="font-medium">
                                                            {formatCurrency(payment.amount)}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-col gap-2 ml-4">
                                    {bill.is_due && bill.active && (
                                        <button
                                            onClick={() => payNow(bill.id)}
                                            className="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700"
                                        >
                                            Pay Now
                                        </button>
                                    )}
                                    <button
                                        onClick={() => startEdit(bill)}
                                        className="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700"
                                    >
                                        Edit
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}