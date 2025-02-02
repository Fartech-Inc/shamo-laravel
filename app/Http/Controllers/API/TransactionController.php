<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Midtrans\Snap;
use Midtrans\Config;

class TransactionController extends Controller
{
    public function __construct()
    {
        // Set konfigurasi Midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');
    }

    public function all(Request $request)
    {
        $id = $request->input('id');
        $limit = $request->input('limit', 6);
        $status = $request->input('status');

        if($id)
        {
            $transaction = Transaction::with(['items.product'])->find($id);

            if($transaction)
            {
                return ResponseFormatter::success(
                    $transaction,
                    'Data Transaksi berhasil diambil'
                );
            }
            else 
            {
                return ResponseFormatter::error(
                    null,
                    'Data transaksi tidak ada',
                    404
                );
            }
        }

        $transaction = Transaction::with(['items.product'])->where('users_id', Auth::user()->id);

        if($status)
        {
            $transaction->where('status', $status);
        }

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'Data list transaksi berhasil diambil'
        );
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'exists:products,id',
            'total_price' => 'required',
            'shipping_price' => 'required',
            // 'status' => 'required|in:PENDING,SUCCESS,CANCELLED,FAILED,SHIPPING,SHIPPED'
        ]);

        $transaction = Transaction::create([
            'users_id' => Auth::user()->id,
            'address' => $request->address,
            'total_price' => $request->total_price,
            'shipping_price' => $request->shipping_price,
            'status' => 'PENDING',
        ]);

        foreach ($request->items as $product) {
            TransactionItem::create([
                'users_id' => Auth::user()->id,
                'products_id' => $product['id'],
                'transactions_id' => $transaction->id,
                'quantity' => $product['quantity']
            ]);
        }

        // Konfigurasi data untuk Midtrans
        $midtransPayload = [
            'transaction_details' => [
                'order_id' => $transaction->id,
                'gross_amount' => (int) $transaction->total_price,
            ],
            'customer_details' => [
                'first_name' => Auth::user()->name,
                'email' => Auth::user()->email,
            ],
            'item_details' => array_map(function ($item) {
                return [
                    'id' => $item['id'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'name' => $item['name'],
                ];
            }, $request->items),
        ];

        $snapToken = Snap::getSnapToken($midtransPayload);
        $transaction->midtrans_booking_code = $snapToken;
        $transaction->save();

        return ResponseFormatter::success($transaction, 'Transaksi berhasil dibuat, lanjutkan ke pembayaran.');
    }

    public function midtransCallback(Request $request)
    {
        $serverKey = config('services.midtrans.serverKey');
        $signatureKey = hash("sha512", $request->order_id.$request->status_code.$request->gross_amount.$serverKey);

        if ($signatureKey !== $request->signature_key) {
            return ResponseFormatter::error(null, 'Invalid Midtrans Signature Key', 403);
        }

        $transaction = Transaction::where('id', $request->order_id)->first();

        if (!$transaction) {
            return ResponseFormatter::error(null, 'Transaction not found', 404);
        }

        if ($request->transaction_status == 'settlement') {
            $transaction->status = 'SUCCESS';
        } elseif ($request->transaction_status == 'pending') {
            $transaction->status = 'PENDING';
        } elseif ($request->transaction_status == 'deny' || $request->transaction_status == 'cancel' || $request->transaction_status == 'expire') {
            $transaction->status = 'FAILED';
        }

        $transaction->save();
        return ResponseFormatter::success($transaction, 'Transaksi diperbarui berdasarkan status dari Midtrans.');
    }
}
