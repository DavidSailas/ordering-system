<?php
namespace App\Http\Controllers\Cashier;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
class TransactionController extends Controller {
 public function index(Request $request) {
  $date=$request->validate(['date'=>['nullable','date_format:Y-m-d'],'order'=>['nullable','string','max:30']]);
  $day=$date['date']??now()->toDateString();
  $orders=Order::query()->with('items')->when(blank($date['order']??null),fn($q)=>$q->whereDate('created_at',$day))
   ->when(filled($date['order']??null),fn($q)=>$q->where('canteen_number',(int)$date['order']))->latest()->paginate(30)->withQueryString();
  $paid=Order::query()->whereDate('paid_at',$day)->whereNull('refunded_at');
  $summary=['transactions'=>(clone $paid)->count(),'net'=>(clone $paid)->sum('amount_paid'),
   'cash'=>(clone $paid)->where('payment_method','cash')->sum('amount_paid'),
   'gcash'=>(clone $paid)->where('payment_method','gcash')->sum('amount_paid'),
   'maya'=>(clone $paid)->where('payment_method','maya')->sum('amount_paid'),
   'refunds'=>Order::whereDate('refunded_at',$day)->sum('amount_paid')];
  return view('Cashier.transactions',compact('orders','summary','day'));
 }
 public function paymentRecords(Request $request) {
  $filters=$request->validate(['date'=>['nullable','date_format:Y-m-d'],'order'=>['nullable','string','max:30']]);
  $day=$filters['date']??now()->toDateString();
  $orders=Order::query()->with('items')->whereNotNull('paid_at')
   ->when(blank($filters['order']??null),fn($q)=>$q->whereDate('paid_at',$day))
   ->when(filled($filters['order']??null),fn($q)=>$q->where('canteen_number',(int)$filters['order']))
   ->latest('paid_at')->paginate(30)->withQueryString();
  return view('Cashier.payment-records',compact('orders','day'));
 }
}
