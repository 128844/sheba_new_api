<?php namespace App\Http\Controllers\B2b;

use App\Models\Bid;
use App\Models\Procurement;
use App\Sheba\Business\Bid\Updater;
use Illuminate\Validation\ValidationException;
use Sheba\Business\Bid\Creator;
use Sheba\ModificationFields;
use Sheba\Repositories\Interfaces\BidRepositoryInterface;
use App\Sheba\Business\ACL\AccessControl;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BidController extends Controller
{
    use ModificationFields;

    public function index($business, $procurement, Request $request, AccessControl $access_control)
    {
        try {
            $access_control->setBusinessMember($request->business_member);
            if (!($access_control->hasAccess('procurement.r') || $access_control->hasAccess('procurement.rw'))) return api_response($request, null, 403);
            $business = $request->business;
            $procurement = Procurement::findOrFail((int)$procurement);

            $bids = $procurement->bids();
            $bid_lists = [];
            foreach ($bids->get() as $bid) {
                $model = $bid->bidder_type;
                $bidder = $model::findOrFail((int)$bid->bidder_id);
                $reviews = $bidder->reviews;

                $bid_items = $bid->bidItems;
                $item_type = [];
                foreach ($bid_items as $item) {
                    $item_fields = [];
                    $fields = $item->fields;
                    foreach ($fields as $field) {
                        array_push($item_fields, [
                            'question' => $field->title,
                            'answer' => $field->result
                        ]);
                    }
                    array_push($item_type, [
                        'item_type' => $item->type,
                        'fields' => $item_fields
                    ]);
                }
                array_push($bid_lists, [
                    'id' => $bid->id,
                    'status' => $bid->status,
                    'bidder_name' => $bidder->name,
                    'bidder_logo' => $bidder->logo,
                    'bidder_avg_rating' => round($reviews->avg('rating'), 2),
                    'item' => $item_type
                ]);
            }
            if (count($bid_lists) > 0) return api_response($request, $bid_lists, 200, ['bid_lists' => $bid_lists]);
            else return api_response($request, null, 404);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function updateFavourite($business, $bid, Request $request, Updater $updater)
    {

        try {
            $this->validate($request, [
                'is_favourite' => 'required|integer:in:1,0',
            ]);
            $bid = Bid::findOrFail((int)$bid);
            if (!$bid) {
                return api_response($request, null, 404);
            } else {
                $updater->setIsFavourite($request->is_favourite)->updateFavourite($bid);
                return api_response($request, null, 200);
            }
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getBidHistory($business, $procurement, Request $request, AccessControl $access_control)
    {
        try {
            $access_control->setBusinessMember($request->business_member);
            if (!($access_control->hasAccess('procurement.r') || $access_control->hasAccess('procurement.rw'))) return api_response($request, null, 403);
            $business = $request->business;
            $procurement = Procurement::findOrFail((int)$procurement);
            list($offset, $limit) = calculatePagination($request);
            $bids = $procurement->bids()->orderBy('created_at', 'desc')->skip($offset)->limit($limit);
            $bid_histories = [];
            $bids->each(function ($bid) use (&$bid_histories) {
                array_push($bid_histories, [
                    'id' => $bid->id,
                    'service_provider' => $bid->bidder->name,
                    'status' => $bid->status,
                    'price' => $bid->price,
                    'created_at' => $bid->created_at->format('h:i a,d M Y'),
                ]);
            });
            if (count($bid_histories) > 0) return api_response($request, $bid_histories, 200, ['bid_histories' => $bid_histories]);
            else return api_response($request, null, 404);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function sendHireRequest($bid, Request $request, BidRepositoryInterface $bid_repository, Updater $updater)
    {
        try {
            $this->validate($request, [
                'terms' => 'required|string',
                'policies' => 'required|string',
                'items' => 'required|string'
            ]);
            $bid = $bid_repository->find($bid);
            $bid_price_quotation_item = $bid->items->where('type', 'price_quotation')->first();
            $items = collect(json_decode($request->items));
            $price_quotation_item = $items->where('id', $bid_price_quotation_item->id)->first();
            $fields = collect($price_quotation_item->fields);
            $updater->setBid($bid)->setTerms($request->terms)->setPolicies($request->policies)->setItems($items)->hire();
            return api_response($request, $bid, 200);
            $field_results = [];
            foreach ($bid_price_quotation_item->fields as $field) {
                $field = $fields->where('id', $field->id)->first();
            }
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}