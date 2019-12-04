<?php namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Partner;
use App\Models\PartnerBankInformation;
use App\Models\PartnerBankLoan;
use App\Models\Profile;
use App\Repositories\CommentRepository;
use App\Repositories\FileRepository;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;
use Sheba\Loan\DS\BusinessInfo;
use Sheba\Loan\DS\FinanceInfo;
use Sheba\Loan\DS\NomineeGranterInfo;
use Sheba\Loan\DS\PersonalInfo;
use Sheba\Loan\Exceptions\AlreadyAssignToBank;
use Sheba\Loan\Exceptions\AlreadyRequestedForLoan;
use Sheba\Loan\Exceptions\EmailUsed;
use Sheba\Loan\Exceptions\InvalidStatusTransaction;
use Sheba\Loan\Exceptions\NotApplicableForLoan;
use Sheba\Loan\Loan;
use Sheba\ModificationFields;
use Sheba\Sms\Sms;

class SpLoanController extends Controller
{
    use CdnFileManager, FileManager, ModificationFields;

    /** @var FileRepository $fileRepository */
    private $fileRepository;

    public function __construct(FileRepository $file_repository)
    {
        $this->fileRepository = $file_repository;
    }

    public function index(Request $request, Loan $loan)
    {
        try {
            $output = $loan->all($request);
            return api_response($request, $output, 200, ['data' => $output]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }

    public function show(Request $request, $loan_id, Loan $loan)
    {
        try {
            $data = $loan->show($loan_id);
            return api_response($request, $data, 200, ['data' => $data]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }

    public function update(Request $request, $loan_id)
    {
    }

    public function statusChange(Request $request, $loan_id, Loan $loan)
    {
        try {
            $this->validate($request, [
                'new_status' => 'required',
                'description' => 'required_if:new_status,declined'
            ]);
            $loan->statusChange($loan_id, $request);
            return api_response($request, true, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (InvalidStatusTransaction $e) {
            return api_response($request, null, 400, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getHomepage($partner, Request $request, Loan $loan)
    {
        try {
            $partner = $request->partner;
            $resource = $request->manager_resource;
            $homepage = $loan->setPartner($partner)->setResource($resource)->homepage();
            return api_response($request, $homepage, 200, ['homepage' => $homepage]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getBankInterest($partner, Request $request)
    {
        try {
            $interest_rate = constants('LOAN_CONFIG')['interest'];
            $amount = $request->has('amount') ? (double)$request->amount : 0;
            $duration = $request->has('duration') ? (int)$request->duration * 12 : 1;
            $total_interest = ($interest_rate / 100) * $amount;
            $total_instalment_amount = $amount + $total_interest;
            $interest_per_month = $total_instalment_amount / $duration;
            $bank_lists = [
                [
                    'interest' => $interest_rate,
                    'total_amount' => $total_instalment_amount,
                    'installment_number' => $duration,
                    'interest_per_month' => $interest_per_month
                ],
            ];
            return api_response($request, $bank_lists, 200, ['bank_lists' => $bank_lists]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function store($partner, Request $request, Loan $loan)
    {
        try {
            $this->validate($request, [
                'loan_amount' => 'required|numeric',
                'duration' => 'required|integer',
            ]);
            $partner = $request->partner;
            $resource = $request->manager_resource;
            $data = [
                'loan_amount' => $request->loan_amount,
                'duration' => $request->duration,
            ];
            $info = $loan->setPartner($partner)->setResource($resource)->setData($data)->apply();
            return api_response($request, 1, 200, ['data' => $info]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (AlreadyRequestedForLoan $e) {
            return api_response($request, $e->getMessage(), 400, ['message' => $e->getMessage()]);
        } catch (NotApplicableForLoan $e) {
            return api_response($request, $e->getMessage(), 400, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getPersonalInformation($partner, Request $request)
    {
        try {
            $partner = $request->partner;
            $manager_resource = $request->manager_resource;
            $info = (new Loan())->setPartner($partner)->setResource($manager_resource)->personalInfo();
            return api_response($request, $info, 200, [
                'info' => $info->toArray(),
                'completion' => $info->completion()
            ]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function updatePersonalInformation($partner, Request $request)
    {
        try {
            $this->validate($request, PersonalInfo::getValidators());
            $partner = $request->partner;
            $manager_resource = $request->manager_resource;
            (new Loan())->setPartner($partner)->setResource($manager_resource)->personalInfo()->update($request);
            return api_response($request, 1, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (EmailUsed $e) {
            return api_response($request, $e->getMessage(), 400, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getBusinessInformation($partner, Request $request)
    {
        try {
            $partner = $request->partner;
            $manager_resource = $request->manager_resource;
            $info = (new Loan())->setPartner($partner)->setResource($manager_resource)->businessInfo();
            return api_response($request, $info, 200, [
                'info' => $info->toArray(),
                'completion' => $info->completion()
            ]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function updateBusinessInformation($partner, Request $request)
    {
        try {
            $this->validate($request, BusinessInfo::getValidator());
            $partner = $request->partner;
            $resource = $request->manager_resource;
            (new Loan())->setPartner($partner)->setResource($resource)->businessInfo()->update($request);
            return api_response($request, 1, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getFinanceInformation($partner, Request $request)
    {
        try {
            $partner = $request->partner;
            $manager_resource = $request->manager_resource;
            $info = (new Loan())->setPartner($partner)->setResource($manager_resource)->financeInfo();
            return api_response($request, $info, 200, [
                'info' => $info->toArray(),
                'completion' => $info->completion()
            ]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function updateFinanceInformation($partner, Request $request)
    {
        try {
            $this->validate($request, FinanceInfo::getValidators());
            $partner = $request->partner;
            $resource = $request->manager_resource;
            (new Loan())->setPartner($partner)->setResource($resource)->financeInfo()->update($request);
            return api_response($request, 1, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getNomineeInformation($partner, Request $request, Loan $loan)
    {
        try {
            $resource = $request->manager_resource;
            $partner = $request->partner;
            $info = $loan->setPartner($partner)->setResource($resource)->nomineeGranter();
            return api_response($request, $info, 200, [
                'info' => $info->toArray(),
                'completion' => $info->completion()
            ]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function updateNomineeGranterInformation($partner, Request $request, Loan $loan)
    {
        try {
            $this->validate($request, NomineeGranterInfo::getValidator());
            $partner = $request->partner;
            $resource = $request->manager_resource;
            $loan->setPartner($partner)->setResource($resource)->nomineeGranter()->update($request);
            return api_response($request, 1, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getDocuments($partner, Request $request, Loan $loan)
    {
        try {
            $partner = $request->partner;
            $resource = $request->manager_resource;
            $info = $loan->setPartner($partner)->setResource($resource)->documents();
            return api_response($request, $info, 200, [
                'info' => $info->toArray(),
                'completion' => $info->completion()
            ]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function updateProfilePictures($partner, Request $request)
    {
        try {
            $this->validate($request, ['picture' => 'required|mimes:jpeg,png,jpg']);
            $manager_resource = $request->manager_resource;
            $profile = $manager_resource->profile;
            $image_for = $request->image_for;
            $nominee = (bool)$request->nominee;
            $grantor = (bool)$request->grantor;
            if ($nominee) {
                if (!$profile->nominee_id) {
                    return api_response($request, null, 401, ['message' => 'Create Nominee First']);
                } else {
                    $profile = Profile::find($profile->nominee_id);
                }
            }
            if ($grantor) {
                if (!$profile->grantor_id) {
                    return api_response($request, null, 401, ['message' => 'Create Grantor First']);
                } else {
                    $profile = Profile::find($profile->grantor_id);
                }
            }
            $photo = $request->file('picture');
            if (basename($profile->{$image_for}) != 'default.jpg') {
                $filename = substr($profile->{$image_for}, strlen(config('sheba.s3_url')));
                $this->deleteOldImage($filename);
            }
            $picture_link = $this->fileRepository->uploadToCDN($this->makePicName($profile, $photo, $image_for), $photo, 'images/profiles/' . $image_for . '_');
            if ($picture_link != false) {
                $data[$image_for] = $picture_link;
                $profile->update($this->withUpdateModificationField($data));
                return api_response($request, $profile, 200, ['picture' => $profile->{$image_for}]);
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

    private function deleteOldImage($filename)
    {
        $old_image = substr($filename, strlen(config('sheba.s3_url')));
        $this->fileRepository->deleteFileFromCDN($old_image);
    }

    private function makePicName($profile, $photo, $image_for = 'profile')
    {
        return $filename = Carbon::now()->timestamp . '_' . $image_for . '_image_' . $profile->id . '.' . $photo->extension();
    }

    public function updateBankStatement($partner, Request $request)
    {
        try {
            $this->validate($request, ['picture' => 'required|mimes:jpeg,png']);
            $partner = $request->partner;
            $bank_informations = $partner->bankInformations;
            if (!$bank_informations)
                $bank_informations = $this->createBankInformation($partner);
            $file_name = $request->picture;
            if ($bank_informations->statement != getBankStatementDefaultImage()) {
                $old_statement = substr($bank_informations->statement, strlen(config('s3.url')));
                $this->deleteImageFromCDN($old_statement);
            }
            $bank_statement = $this->saveBankStatement($file_name);
            if ($bank_statement != false) {
                $data['statement'] = $bank_statement;
                $bank_informations->update($this->withUpdateModificationField($data));
                return api_response($request, $bank_statement, 200, ['picture' => $bank_informations->statement]);
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

    private function createBankInformation($partner)
    {
        $this->setModifier($partner);
        $bank_information = new PartnerBankInformation();
        $bank_information->partner_id = $partner->id;
        $bank_information->is_verified = $partner->status == 'Verified' ? 1 : 0;
        $this->withCreateModificationField($bank_information);
        $bank_information->save();
        return $bank_information;
    }

    private function saveBankStatement($image_file)
    {
        list($bank_statement, $statement_filename) = $this->makeBankStatement($image_file, 'bank_statement');
        return $this->saveImageToCDN($bank_statement, getBankStatementImagesFolder(), $statement_filename);
    }

    public function updateTradeLicense($partner, Request $request)
    {
        try {
            $this->validate($request, ['picture' => 'required|mimes:jpeg,png']);
            $partner = $request->partner;
            $basic_informations = $partner->basicInformations;
            $file_name = $request->picture;
            if ($basic_informations->trade_license_attachment != getTradeLicenseDefaultImage()) {
                $old_statement = substr($basic_informations->trade_license_attachment, strlen(config('s3.url')));
                $this->deleteImageFromCDN($old_statement);
            }
            $trade_license = $this->saveTradeLicense($file_name);
            if ($trade_license != false) {
                $data['trade_license_attachment'] = $trade_license;
                $basic_informations->update($this->withUpdateModificationField($data));
                return api_response($request, $trade_license, 200, ['picture' => $basic_informations->trade_license_attachment]);
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

    private function saveTradeLicense($image_file)
    {
        list($trade_license, $trade_license_filename) = $this->makeTradeLicense($image_file, 'trade_license_attachment');
        return $this->saveImageToCDN($trade_license, getTradeLicenceImagesFolder(), $trade_license_filename);
    }

    public function getChangeLogs(Request $request, PartnerBankLoan $partner_bank_loan)
    {

        try {
            list($offset, $limit) = calculatePagination($request);
            $partner_bank_loan_logs = $partner_bank_loan->changeLogs->slice($offset)->take($limit);
            return api_response($request, null, 200, ['logs' => $partner_bank_loan_logs]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function sendSMS(PartnerBankLoan $partner_bank_loan,Request $request)
    {
        try {
            $this->validate($request, [
                'message' => 'required|string',
            ]);
            $mobile = $partner_bank_loan->partner->getContactNumber();
            $message = $request->message;
            (new Sms())->msg($message)->to($mobile)->shoot();
            return api_response($request, null, 200, ['message' => 'SMS has been sent successfully']);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }

    public function history(Request $request, Loan $loan)
    {
        try {
            $partner = $request->partner;
            $resource = $request->manager_resource;
            $data = $loan->setPartner($partner)->setResource($resource)->history();
            return api_response($request, $data, 200, ['data' => $data]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function storeComment(PartnerBankLoan $partner_bank_loan, Request $request)
    {
        try {
            $this->validate($request, [
                'comment' => 'required'
            ]);
            $bank_user = $request->user;
            $comment = (new CommentRepository('PartnerBankLoan', $partner_bank_loan->id, $bank_user))->store($request->comment);
            $formatted_comment = [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'user' => [
                    'name' => $comment->commentator->profile->name,
                    'image' => $comment->commentator->profile->pro_pic
                ],
                'created_at' => (Carbon::parse($comment->created_at))->format('j F, Y h:i A')
            ];
            return $comment ? api_response($request, $comment, 200, ['comment' => $formatted_comment]) : api_response($request, $comment, 500);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context([
                'request' => $request->all(),
                'message' => $message
            ]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getComments(PartnerBankLoan $partner_bank_loan, Request $request)
    {
        try {
            list($offset, $limit) = calculatePagination($request);
            $comments = Comment::where('commentable_type', get_class($partner_bank_loan))->where('commentable_id', $partner_bank_loan->id)->orderBy('id', 'DESC')->skip($offset)->limit($limit)->get();
            $comment_lists = [];
            foreach ($comments as $comment) {
                array_push($comment_lists, [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'user' => [
                        'name' => $comment->commentator->profile->name,
                        'image' => $comment->commentator->profile->pro_pic
                    ],
                    'created_at' => (Carbon::parse($comment->created_at))->format('j F, Y h:i A')
                ]);
            }
            if (count($comment_lists) > 0)
                return api_response($request, $comment_lists, 200, ['comment_lists' => $comment_lists]); else  return api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function assignBank(Request $request, $loan_id, $bank_id, Loan $loan)
    {
        try {
            $loan->assignBank($loan_id, $bank_id);
            return api_response($request, true, 200);
        } catch (AlreadyAssignToBank $e) {
            return api_response($request, null, 400, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }

    public function uploadDocuments(Request $request, $loan_id, Loan $loan)
    {
        try {
            $this->validate($request, ['picture' => 'required|mimes:jpg,jpeg,png,pdf', 'name' => 'required']);
            $loan->uploadDocument($loan_id, $request, $request->user);
            return api_response($request, true, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}
