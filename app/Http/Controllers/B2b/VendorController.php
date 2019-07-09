<?php namespace App\Http\Controllers\B2b;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Excel;
use Sheba\Business\Vendor\BulkUploadExcel;
use Sheba\Business\Vendor\CreateRequest;
use Sheba\Business\Vendor\Creator;
use Sheba\ModificationFields;
use Throwable;

class VendorController extends Controller
{
    use ModificationFields;

    /**
     * @param Request $request
     * @param CreateRequest $create_request
     * @param Creator $creator
     * @return JsonResponse
     */
    public function store(Request $request, CreateRequest $create_request, Creator $creator)
    {
        try {
            $this->validate($request, [
                'vendor_name' => 'required',
                'vendor_mobile' => 'required|string|mobile:bd',
                'vendor_image' => 'sometimes|required|mimes:jpeg,png',
                'resource_name' => 'required',
                'resource_mobile' => 'required|string|mobile:bd'
            ]);
            $business = $request->business;
            $member = $request->member;
            $this->setModifier($member);

            /** @var CreateRequest $request */
            $create_request = $create_request->setBusiness($business)
                ->setVendorName($request->vendor_name)
                ->setVendorMobile($request->vendor_mobile)
                ->setVendorEmail($request->vendor_email)
                ->setVendorImage($request->vendor_image)
                ->setVendorAddress($request->vendor_address)
                ->setVendorMasterCategories($request->vendor_master_categories)
                ->setTradeLicenseNumber($request->trade_license_number)
                ->setTradeLicenseDocument($request->trade_license_document)
                ->setVatRegistrationNumber($request->vat_registration_number)
                ->setVatRegistrationDocument($request->vat_registration_document)
                ->setResourceName($request->resource_name)
                ->setResourceMobile($request->resource_mobile)
                ->setResourceNidNumber($request->resource_nid_number)
                ->setResourceNidDocument($request->resource_nid_document);

            $creator->setVendorCreateRequest($create_request);
            if ($error = $creator->hasError()) {
                return api_response($request, null, 400, ['message' => 'Error']);
            }

            $vendor = $creator->create();

            return api_response($request, null, 200, ['vendor_id' => $vendor->id, 'message' => 'Vendor Created Successfully']);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @param CreateRequest $create_request
     * @param Creator $creator
     * @return JsonResponse
     */
    public function bulkStore(Request $request, CreateRequest $create_request, Creator $creator)
    {
        try {
            $this->validate($request, ['file' => 'required|file']);

            $valid_extensions = ["xls", "xlsx", "xlm", "xla", "xlc", "xlt", "xlw"];
            $extension = $request->file('file')->getClientOriginalExtension();

            if (!in_array($extension, $valid_extensions)) {
                return api_response($request, null, 400, ['message' => 'File type not support']);
            }

            $admin_member = $request->member;
            $business = $request->business;
            $this->setModifier($admin_member);

            $file = Excel::selectSheets(BulkUploadExcel::SHEET)->load($request->file)->save();
            $file_path = $file->storagePath . DIRECTORY_SEPARATOR . $file->getFileName() . '.' . $file->ext;

            $data = Excel::selectSheets(BulkUploadExcel::SHEET)->load($file_path)->get();

            $error_count = 0;
            $vendor_name = BulkUploadExcel::VENDOR_NAME_COLUMN_TITLE;
            $phone_number = BulkUploadExcel::PHONE_NUMBER_COLUMN_TITLE;
            $contact_person_name = BulkUploadExcel::CONTACT_PERSON_NAME_COLUMN_TITLE;
            $contact_person_mobile = BulkUploadExcel::CONTACT_PERSON_MOBILE_COLUMN_TITLE;
            $address = BulkUploadExcel::ADDRESS_COLUMN_TITLE;
            $email = BulkUploadExcel::EMAIL_COLUMN_TITLE;

            $data->each(function ($value) use (
                $create_request, $creator, $admin_member, &$error_count, $business,
                $vendor_name, $phone_number, $contact_person_name, $contact_person_mobile, $address, $email
            ) {
                if (is_null($value->$vendor_name) && is_null($value->$phone_number)) return false;

                /** @var CreateRequest $request */
                $create_request = $create_request->setBusiness($business)
                    ->setVendorName($value->$vendor_name)
                    ->setVendorMobile($value->$phone_number)
                    ->setVendorEmail($value->$email)
                    ->setVendorAddress($value->$address)
                    ->setResourceName($value->$contact_person_name)
                    ->setResourceMobile($value->$contact_person_mobile);

                $creator->setVendorCreateRequest($create_request);
                if ($error = $creator->hasError()) {
                    $error_count++;
                    return false;
                }

                $creator->create();
            });

            return api_response($request, null, 200, ['message' => "Vendor's Created Successfully, Error on: {$error_count} driver"]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}
