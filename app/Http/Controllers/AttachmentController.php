<?php namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Bid;
use App\Models\Business;
use App\Models\Partner;
use App\Sheba\Attachments\Attachments;
use Illuminate\Validation\ValidationException;
use Sheba\ModificationFields;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    use ModificationFields;

    public function storeAttachment($avatar, $bid, Request $request, Attachments $attachments)
    {
        try {
            $this->validate($request, [
                'file' => 'required'
            ]);
            $bid = Bid::findOrFail((int)$bid);

            if ($request->segment(2) == 'businesses') {
                $avatar = Business::findOrFail((int)$avatar);
            } elseif ($request->segment(2) == 'partners') {
                $avatar = Partner::findOrFail((int)$avatar);
            }
            if ($attachments->hasError($request))
                return redirect()->back();

            $attachments = $attachments->setAttachableModel($bid)->setRequestData($request)->setFile($request->file)->formatData();
            $this->setModifier($avatar);
            $attachment = $attachments->store();
            return api_response($request, $attachment, 200, ['attachment' => $attachment->file]);
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

    public function getAttachments($avatar, $bid, Request $request)
    {
        try {
            $bid = Bid::find((int)$bid);
            if (!$bid) return api_response($request, null, 404);
            list($offset, $limit) = calculatePagination($request);
            $attaches = Attachment::where('attachable_type', get_class($bid))->where('attachable_id', $bid->id)
                ->select('id', 'title', 'file', 'file_type')->orderBy('id', 'DESC')->skip($offset)->limit($limit)->get();
            $attach_lists = [];
            foreach ($attaches as $attach) {
                array_push($attach_lists, [
                    'id' => $attach->id,
                    'title' => $attach->title,
                    'file' => $attach->file,
                    'file_type' => $attach->file_type,
                ]);
            }
            if (count($attach_lists) > 0) return api_response($request, $attach_lists, 200, ['attach_lists' => $attach_lists]);
            else  return api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

}