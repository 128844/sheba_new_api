<?php namespace App\Http\Controllers\B2b;

use App\Models\Bid;
use App\Models\Procurement;
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

    public function updateFavourite($business, $bid, Request $request, Creator $creator)
    {

        try {
            $this->validate($request, [
                'is_favourite' => 'required|integer:in:1,0',
            ]);
            $bid = Bid::findOrFail((int)$bid);
            if (!$bid) {
                return api_response($request, null, 404);
            } else {
                $creator->setIsFavourite($request->is_favourite)->updateFavourite($bid);
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
}