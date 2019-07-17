<?php namespace App\Http\Controllers\PaymentLink;

use GuzzleHttp\Client;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use App\Models\Partner;
use App\Models\Profile;
use Carbon\Carbon;
use DB;

class PaymentLinkController extends Controller
{
    use ModificationFields;

    public function index($partner, Request $request)
    {
        try {
            if (1) {
                $payment_links = [
                    [
                        'id' => 1,
                        'code' => '#123456',
                        'purpose' => 'Mobile home delivery',
                        'status' => 'active',
                        'amount' => 200,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ],
                    [
                        'id' => 2,
                        'code' => '#123456',
                        'purpose' => 'Mobile home delivery',
                        'status' => 'inactive',
                        'amount' => 200,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ],
                    [
                        'id' => 3,
                        'code' => '#123456',
                        'purpose' => 'Mobile home delivery',
                        'status' => 'active',
                        'amount' => 290,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ],
                    [
                        'id' => 4,
                        'code' => '#123456',
                        'purpose' => 'Mobile home delivery',
                        'status' => 'active',
                        'amount' => 200,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ],
                    [
                        'id' => 5,
                        'code' => '#123456',
                        'purpose' => 'Mobile home delivery',
                        'status' => 'inactive',
                        'amount' => 220,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ]
                ];
                return api_response($request, $payment_links, 200, ['payment_links' => $payment_links]);
            } else {
                return api_response($request, 1, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function store($partner, Request $request)
    {
        try {
            $this->validate($request, [
                'amount' => 'required',
                'purpose' => 'required',
            ]);

            $data = [
                'amount' => $request->amount,
                'reason' => $request->purpose,
                'userId' => $request->user->id,
                'userName' => $request->user->name,
                'userType' => $request->type
            ];
            $url = config('sheba.payment_link_url') . '/api/v1/payment-links';
            $client = new Client();
            $result = $client->request('POST', $url, ['form_params' => $data]);
            $result = json_decode($result->getBody());

            if ($result->code == 200) {
                $payment_link = [
                    'reason' => $result->link->reason,
                    'status' => $result->link->status,
                    'amount' => $result->link->amount,
                    'link' => $result->link->link,
                ];
                return api_response($request, $payment_link, 200, ['payment_link' => $payment_link]);
            } else {
                return api_response($request, null, 500);
            }
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function statusChange($partner, $link, Request $request)
    {
        try {
            $this->validate($request, [
                'status' => 'required'
            ]);
            return api_response($request, 1, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getDefaultLink($partner, Request $request)
    {
        try {
            if (1) {
                $default_payment_link = 'https:sheba.xyz@Venus';
                return api_response($request, $default_payment_link, 200, ['default_payment_link' => $default_payment_link]);
            } else {
                return api_response($request, 1, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getPaymentLinkPayments($partner, $link, Request $request)
    {
        try {
            if (1) {
                $all_payment = [
                    [
                        'id' => 1,
                        'code' => '#156412',
                        'name' => 'Shamim Reza',
                        'amount' => 220,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ],
                    [
                        'id' => 2,
                        'code' => '#156412',
                        'name' => 'Ramzaan',
                        'amount' => 220,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ],
                    [
                        'id' => 3,
                        'code' => '#156412',
                        'name' => 'Sabbir',
                        'amount' => 220,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ],
                    [
                        'id' => 4,
                        'code' => '#156412',
                        'name' => 'Sabbir',
                        'amount' => 220,
                        'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    ],

                ];
                $payment_link_payments = [
                    'id' => 1,
                    'code' => '#123456',
                    'purpose' => 'Mobile home delivery',
                    'payment_link' => 'https:sheba.xyz@Venus',
                    'status' => 'active',
                    'amount' => 220,
                    'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    'total_payments' => 4,
                    'payments' => $all_payment
                ];
                return api_response($request, $payment_link_payments, 200, ['payment_link_payments' => $payment_link_payments]);
            } else {
                return api_response($request, 1, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function paymentLinkPaymentDetails($partner, $payment, Request $request)
    {
        try {
            if (1) {
                $payment_details = [
                    'id' => 1,
                    'payment_code' => '#156412',
                    'customer_name' => 'Sabbir',
                    'customer_number' => '01678099565',
                    'link_code' => '#P-123456',
                    'link' => 'https://sheba.xyz/p/@VenusBeauty',
                    'purpose' => 'Mobile home delivery',
                    'payment_type' => 'Bkash',
                    'amount' => 220,
                    'created_at' => Carbon::parse('2019-07-18 18:05:51')->format('Y-m-d h:i a'),
                    'tnx_id' => 24359487,
                ];
                return api_response($request, $payment_details, 200, ['payment_details' => $payment_details]);
            } else {
                return api_response($request, 1, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}
