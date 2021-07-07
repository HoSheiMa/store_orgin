<?php


namespace App\Http\Controllers;

use App\Utility\PayfastUtility;
use Illuminate\Http\Request;
use Auth;
use App\Category;
use App\Cart;
use App\Http\Controllers\PaypalController;
use App\Http\Controllers\InstamojoController;
use App\Http\Controllers\ClubPointController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\PublicSslCommerzPaymentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\PaytmController;
use App\Order;
use App\CommissionHistory;
use App\BusinessSetting;
use App\Coupon;
use App\CouponUsage;
use App\User;
use App\Address;
use Illuminate\Support\Facades\URL;
use Session;
use App\Utility\PayhereUtility;

class fawaterk extends Controller
{
    public function checkout_done(Request $request)
    {

        $order = Order::findOrFail($request->session()->get('order_id'));
        $code = $order->code;

        $checkoutController = new CheckoutController;
        $checkoutController->checkout_done($request->session()->get('order_id'), 'fawaterk');
        return redirect('/track-your-order?order_code=' . $code);

    }

    public function init($request)
    {

        $order = Order::findOrFail(Session::get('order_id'));


        $carts = Cart::where('user_id', Auth::user()->id)
            ->get();
        $shipping_info = Address::where('id', $carts[0]['address_id'])->first();
        $total = 0;
        $tax = 0;
        $shipping = 0;
        $subtotal = 0;
        $FawatercartItems = [];

        if ($carts && count($carts) > 0) {
            foreach ($carts as $key => $cartItem) {
                $_items_ = [];
                $product = \App\Product::find($cartItem['product_id']);
                $tax += $cartItem['tax'] * $cartItem['quantity'];
                $subtotal += $cartItem['price'] * $cartItem['quantity'];

                $_items_['name'] = $product->name;
                $_items_['price'] = $cartItem['price'];
                $_items_['quantity'] = $cartItem['quantity'];
                array_push($FawatercartItems, $_items_);

                if ($request['shipping_type_' . $request->owner_id] == 'pickup_point') {
                    $cartItem['shipping_type'] = 'pickup_point';
                    $cartItem['pickup_point'] = $request['pickup_point_id_' . $request->owner_id];
                } else {
                    $cartItem['shipping_type'] = 'home_delivery';
                }
                $cartItem['shipping_cost'] = 0;
                if ($cartItem['shipping_type'] == 'home_delivery') {
                    $cartItem['shipping_cost'] = getShippingCost($carts, $key);
                }

                if (isset($cartItem['shipping_cost']) && is_array(json_decode($cartItem['shipping_cost'], true))) {

                    foreach (json_decode($cartItem['shipping_cost'], true) as $shipping_region => $val) {
                        if ($shipping_info['city'] == $shipping_region) {
                            $cartItem['shipping_cost'] = (double)($val);
                            break;
                        } else {
                            $cartItem['shipping_cost'] = 0;
                        }
                    }
                } else {
                    if (!$cartItem['shipping_cost'] ||
                        $cartItem['shipping_cost'] == null ||
                        $cartItem['shipping_cost'] == 'null') {

                        $cartItem['shipping_cost'] = 0;
                    }
                }

                if ($product->is_quantity_multiplied == 1 && get_setting('shipping_type') == 'product_wise_shipping') {
                    $cartItem['shipping_cost'] = $cartItem['shipping_cost'] * $cartItem['quantity'];
                }

                $shipping += $cartItem['shipping_cost'];
                $cartItem->save();

            }
        }
        $total = $subtotal + $tax + $shipping;


        $curl = curl_init();

        $data = array(
            'vendorKey' => env('FAWATERK_KEY'),
            'cartItems' => $FawatercartItems,
            'cartTotal' => $total,
            'shipping' => $shipping,
            'customer' => [
                'first_name' => explode(' ', json_decode($order->shipping_address)->name)[0],
                'last_name' => explode(' ', json_decode($order->shipping_address)->name)[1],
                'email' => json_decode($order->shipping_address)->email,
                'phone' => json_decode($order->shipping_address)->phone,
                'address' => json_decode($order->shipping_address)->email,
            ],
            'redirectUrl' => URL::to('/') . '/fawaterk/done',
            'currency' => 'EGP');


        $data = http_build_query($data);

        //var_dump($data);

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.fawaterk.com/api/invoice',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return redirect(json_decode($response)->url);

    }
}
