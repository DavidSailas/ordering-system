<?php
namespace App\Http\Controllers\Cashier;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class PaymentController extends Controller {
 public function show(Order $order) { Gate::authorize('view',$order); return view('Cashier.payment',['order'=>$order->load('items')]); }
 public function receipt(Request $request, Order $order) { Gate::authorize('view',$order); return view('Cashier.receipt',['order'=>$order->load('items'),'autoprint'=>$request->boolean('print')]); }
 public function store(Request $request, Order $order) {
  $data=$request->validate(['payment_method'=>['required',Rule::in(['cash','gcash','maya'])], 'cash_received'=>['nullable','numeric','decimal:0,2','min:0','max:99999999.99'], 'payment_reference'=>['nullable','string','max:100'], 'discount'=>['nullable','numeric','decimal:0,2','min:0','max:99999999.99']]);
  DB::transaction(function() use($order,$request,$data) {
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if($locked->paid_at || $locked->status===OrderStatus::Canceled) throw ValidationException::withMessages(['payment'=>'Order is already paid or canceled.']);
   $subtotal=(int)round((float)$locked->total*100); $discount=(int)round((float)($data['discount']??0)*100);
   if($discount>$subtotal) throw ValidationException::withMessages(['discount'=>'Discount cannot exceed subtotal.']);
   $due=$subtotal-$discount; $method=$data['payment_method']; $cash=(int)round((float)($data['cash_received']??0)*100);
   if($method==='cash' && $cash<$due) throw ValidationException::withMessages(['cash_received'=>'Cash must cover the discounted total.']);
   if($method!=='cash' && blank($data['payment_reference']??null)) throw ValidationException::withMessages(['payment_reference'=>'Enter the verified transaction reference.']);
   $locked->update(['paid_at'=>now(),'paid_by'=>$request->user()->id,'payment_method'=>$method,'payment_reference'=>$method==='cash'?null:$data['payment_reference'], 'discount'=>$discount/100,'amount_paid'=>$due/100,'cash_received'=>$method==='cash'?$cash/100:null]);
   if($locked->status===OrderStatus::ReadyToServe) $locked->update(['status'=>OrderStatus::Completed]);
  });
  return redirect()->route('orders.payment',$order)->with('success','Payment recorded.');
 }
 public function refund(Request $request,Order $order) {
  abort_unless($request->user()->isManager(),403);
  $data=$request->validate(['reason'=>['required','string','min:5','max:500']]);
  DB::transaction(function()use($order,$request,$data){$locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if(!$locked->paid_at || $locked->refunded_at) throw ValidationException::withMessages(['refund'=>'Order is unpaid or already refunded.']);
   $locked->update(['refunded_at'=>now(),'refunded_by'=>$request->user()->id,'refund_reason'=>$data['reason'],'status'=>OrderStatus::Canceled,'canceled_at'=>now()]);
  });
  return back()->with('success','Refund recorded. Return funds using the original payment channel.');
 }
}
