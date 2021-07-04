<?php namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\BankUser;
use App\Models\Comment;
use App\Models\PartnerBankInformation;
use App\Models\PartnerBankLoan;
use App\Models\Profile;
use App\Models\User;
use App\Repositories\CommentRepository;
use App\Repositories\FileRepository;
use App\Sheba\Loan\DLSV2\LoanAccount;
use App\Sheba\Loan\Exceptions\LoanNotFoundException;
use Sheba\Sms\BusinessType;
use Sheba\Sms\FeatureType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sheba\Dal\PartnerBankLoan\LoanTypes;
use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;
use Sheba\Loan\DocumentDeleter;
use Sheba\Loan\DS\FinanceInfo;
use Sheba\Loan\DS\NomineeGranterInfo;
use Sheba\Loan\DS\PersonalInfo;
use Sheba\Loan\Exceptions\AlreadyAssignToBank;
use Sheba\Loan\Exceptions\NotAllowedToAccess;
use Sheba\Loan\Loan;
use Sheba\Loan\Statics\BusinessStatics;
use Sheba\Loan\Statics\GeneralStatics;
use Sheba\Loan\Validators\RequestValidator;
use Sheba\ModificationFields;
use Sheba\Reports\PdfHandler;
use Sheba\Sms\Sms;
use Throwable;

class LoanController extends Controller
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
        $output = $loan->all($request);
        return api_response($request, $output, 200, ['data' => $output]);
    }

    public function getDashboardData(Request $request, Loan $loan)
    {
        $data = $loan->microLoanData($request);
        return api_response($request, $data, 200, ['data' => $data]);
    }

    /**
     * @param Request $request
     * @param         $loan_id
     * @param Loan    $loan
     * @return JsonResponse
     */
    public function show(Request $request, $loan_id, Loan $loan)
    {
        $data = $loan->show($loan_id);
        return api_response($request, $data, 200, ['data' => $data]);
    }

    public function update(Request $request, $loan_id, Loan $loan)
    {
        $loan->update($loan_id, $request);
        return api_response($request, true, 200);
    }

    public function statusChange(Request $request, $loan_id, Loan $loan)
    {
        $this->validate($request, [
            'new_status'  => 'required',
            'description' => 'required_if:new_status,declined'
        ]);
        $loan->statusChange($loan_id, $request);
        return api_response($request, true, 200);
    }

    public function getHomepage($partner, Request $request, Loan $loan)
    {
        $partner  = $request->partner;
        $resource = $request->manager_resource;
        $new      = $request->new;
        $homepage = $loan->setPartner($partner)->setResource($resource)->homepage();
        if (empty($new))
            $homepage['duration'] = $homepage['duration'] / 12;
        return api_response($request, $homepage, 200, ['homepage' => $homepage]);
    }

    public function getBankInterest($partner, Request $request)
    {
        $interest_rate           = constants('LOAN_CONFIG')['interest'];
        $amount                  = $request->has('amount') ? (double)$request->amount : 0;
        $duration                = $request->has('duration') ? (int)$request->duration : 1;
        $month                   = $request->has('month') ? (int)$request->month : 0;
        $duration                = $month ? $duration : $duration * 12;
        $interest_per_month      = emi_calculator($interest_rate, $amount, $duration);
        $total_instalment_amount = $interest_per_month * $duration;
        $bank_lists              = [
            [
                'interest'           => $interest_rate,
                'total_amount'       => $total_instalment_amount,
                'installment_number' => $duration,
                'interest_per_month' => $interest_per_month
            ],
        ];
        return api_response($request, $bank_lists, 200, ['bank_lists' => $bank_lists]);
    }

    public function store($partner, Request $request, Loan $loan)
    {
        $this->validate($request, GeneralStatics::validator(1));
        $partner  = $request->partner;
        $resource = $request->manager_resource;
        $data     = [
            'loan_amount' => $request->loan_amount,
            'duration'    => $request->duration,
            'month'       => $request->month ?: 0,
            'type'        => $request->type ?: LoanTypes::TERM
        ];
        $info     = $loan->setPartner($partner)->setResource($resource)->setData($data)->apply();
        return api_response($request, 1, 200, ['data' => $info]);
    }

    public function storeFromPortals(Request $request, Loan $loan)
    {
        $this->validate($request, ['mobile' => 'required|mobile:bd']);
        $partnerBankLoan = $loan->createNew($request)->toArray();
        unset($partnerBankLoan['final_information_for_loan']);
        return api_response($request, $partnerBankLoan, 200, ['data' => $partnerBankLoan]);
    }

    public function getPersonalInformation($partner, Request $request)
    {
        $partner          = $request->partner;
        $manager_resource = $request->manager_resource;
        $info             = (new Loan())->setPartner($partner)->setResource($manager_resource)->personalInfo();
        return api_response($request, $info, 200, [
            'info'       => $info->toArray(),
            'completion' => $info->completion()
        ]);
    }

    public function updatePersonalInformation($partner, Request $request)
    {
        $this->validate($request, PersonalInfo::getValidators());
        $partner          = $request->partner;
        $manager_resource = $request->manager_resource;
        (new Loan())->setPartner($partner)->setResource($manager_resource)->personalInfo()->update($request);
        return api_response($request, 1, 200);
    }

    public function getBusinessInformation($partner, Request $request)
    {
        $partner          = $request->partner;
        $manager_resource = $request->manager_resource;
        $info             = (new Loan())->setPartner($partner)->setResource($manager_resource)->businessInfo();
        return api_response($request, $info, 200, [
            'info'       => $info->toArray(),
            'completion' => $info->completion()
        ]);
    }

    public function updateBusinessInformation($partner, Request $request)
    {
        $this->validate($request, BusinessStatics::validator(1));
        $partner  = $request->partner;
        $resource = $request->manager_resource;
        (new Loan())->setPartner($partner)->setResource($resource)->businessInfo()->update($request);
        return api_response($request, 1, 200);
    }

    public function getFinanceInformation($partner, Request $request)
    {
        $partner          = $request->partner;
        $manager_resource = $request->manager_resource;
        $info             = (new Loan())->setPartner($partner)->setResource($manager_resource)->financeInfo();
        return api_response($request, $info, 200, [
            'info'       => $info->toArray(),
            'completion' => $info->completion()
        ]);
    }

    public function updateFinanceInformation($partner, Request $request)
    {
        $this->validate($request, FinanceInfo::getValidators());
        $partner  = $request->partner;
        $resource = $request->manager_resource;
        (new Loan())->setPartner($partner)->setResource($resource)->financeInfo()->update($request);
        return api_response($request, 1, 200);
    }

    public function getNomineeInformation($partner, Request $request, Loan $loan)
    {
        $resource = $request->manager_resource;
        $partner  = $request->partner;
        $info     = $loan->setPartner($partner)->setResource($resource)->nomineeGranter();
        return api_response($request, $info, 200, [
            'info'       => $info->toArray(),
            'completion' => $info->completion()
        ]);
    }

    public function updateNomineeGranterInformation($partner, Request $request, Loan $loan)
    {
        $this->validate($request, NomineeGranterInfo::getValidator());
        $partner  = $request->partner;
        $resource = $request->manager_resource;
        $loan->setPartner($partner)->setResource($resource)->nomineeGranter()->update($request);
        return api_response($request, 1, 200);
    }

    public function getDocuments($partner, Request $request, Loan $loan)
    {
        $partner  = $request->partner;
        $resource = $request->manager_resource;
        $info     = $loan->setPartner($partner)->setResource($resource)->documents();
        return api_response($request, $info, 200, [
            'info'       => $info->toArray(),
            'completion' => $info->completion()
        ]);
    }

    public function updateProfilePictures($partner, Request $request)
    {
        $this->validate($request, ['picture' => 'required|mimes:jpeg,png,jpg']);
        $manager_resource = $request->manager_resource;
        $profile          = $manager_resource->profile;
        $image_for        = $request->image_for;
        $nominee          = (bool)$request->nominee;
        $grantor          = (bool)$request->grantor;
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
        if ($picture_link == false) return api_response($request, null, 500);

        $data[$image_for] = $picture_link;
        $profile->update($this->withUpdateModificationField($data));
        return api_response($request, $profile, 200, ['picture' => $profile->{$image_for}]);
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
        $this->validate($request, ['picture' => 'required|mimes:jpeg,png']);
        $partner           = $request->partner;
        $bank_informations = $partner->bankInformations ? $partner->bankInformations->first() : null;
        if (!$bank_informations)
            $bank_informations = $this->createBankInformation($partner);
        $file_name = $request->picture;
        if ($bank_informations->statement != getBankStatementDefaultImage()) {
            $old_statement = substr($bank_informations->statement, strlen(config('s3.url')));
            $this->deleteImageFromCDN($old_statement);
        }
        $bank_statement = $this->saveBankStatement($file_name);
        if ($bank_statement == false) return api_response($request, null, 500);

        $data['statement'] = $bank_statement;
        $bank_informations->update($this->withUpdateModificationField($data));
        return api_response($request, $bank_statement, 200, ['picture' => $bank_informations->statement]);
    }

    private function createBankInformation($partner)
    {
        $this->setModifier($partner);
        $bank_information              = new PartnerBankInformation();
        $bank_information->partner_id  = $partner->id;
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
        $this->validate($request, ['picture' => 'required|mimes:jpeg,png']);
        $partner            = $request->partner;
        $basic_informations = $partner->basicInformations;
        $file_name          = $request->picture;
        if ($basic_informations->trade_license_attachment != getTradeLicenseDefaultImage()) {
            $old_statement = substr($basic_informations->trade_license_attachment, strlen(config('s3.url')));
            $this->deleteImageFromCDN($old_statement);
        }
        $trade_license = $this->saveTradeLicense($file_name);
        if ($trade_license == false) return api_response($request, null, 500);

        $data['trade_license_attachment'] = $trade_license;
        $basic_informations->update($this->withUpdateModificationField($data));
        return api_response($request, $trade_license, 200, ['picture' => $basic_informations->trade_license_attachment]);
    }

    private function saveTradeLicense($image_file)
    {
        list($trade_license, $trade_license_filename) = $this->makeTradeLicense($image_file, 'trade_license_attachment');
        return $this->saveImageToCDN($trade_license, getTradeLicenceImagesFolder(), $trade_license_filename);
    }

    public function getChangeLogs(Request $request, PartnerBankLoan $partner_bank_loan)
    {
        $user = $request->user;
        if (!empty($user) && (!($user instanceof User) && ($user instanceof BankUser && $user->bank->id != $partner_bank_loan->bank_id))) {
            throw new NotAllowedToAccess();
        }
        list($offset, $limit) = calculatePagination($request);
        $partner_bank_loan_logs = $partner_bank_loan->changeLogs->slice($offset)->take($limit);
        $output                 = $partner_bank_loan_logs->sortByDesc('id')->values();
        return api_response($request, null, 200, ['logs' => $output]);
    }

    public function sendSMS(PartnerBankLoan $partner_bank_loan, Request $request)
    {
        $this->validate($request, [
            'message' => 'required|string',
        ]);
        $mobile  = $partner_bank_loan->partner->getContactNumber();
        $message = $request->message;
        (new Sms())->msg($message)
            ->setFeatureType(FeatureType::LOAN)
            ->setBusinessType(BusinessType::SMANAGER)
            ->to($mobile)
            ->shoot();
        return api_response($request, null, 200, ['message' => 'SMS has been sent successfully']);
    }

    public function history(Request $request, Loan $loan)
    {
        $partner  = $request->partner;
        $resource = $request->manager_resource;
        $data     = $loan->setPartner($partner)->setResource($resource)->history();
        return api_response($request, $data, 200, ['data' => $data]);
    }

    public function storeComment(PartnerBankLoan $partner_bank_loan, Request $request)
    {
        $this->validate($request, [
            'comment' => 'required'
        ]);
        $bank_user         = $request->user;
        $comment           = (new CommentRepository('PartnerBankLoan', $partner_bank_loan->id, $bank_user))->store($request->comment);
        $formatted_comment = [
            'id'         => $comment->id,
            'comment'    => $comment->comment,
            'user'       => [
                'name'  => $comment->commentator->profile ? $comment->commentator->profile->name : $comment->commentator->name,
                'image' => $comment->commentator->profile ? $comment->commentator->profile->pro_pic : $comment->commentator->pro_pic
            ],
            'created_at' => (Carbon::parse($comment->created_at))->format('j F, Y h:i A')
        ];
        return $comment ? api_response($request, $comment, 200, ['comment' => $formatted_comment]) : api_response($request, $comment, 500);
    }

    /**
     * @param PartnerBankLoan $partner_bank_loan
     * @param Request $request
     * @return JsonResponse
     */
    public function getComments(PartnerBankLoan $partner_bank_loan, Request $request)
    {
        list($offset, $limit) = calculatePagination($request);
        $comments      = Comment::where('commentable_type', get_class($partner_bank_loan))->where('commentable_id', $partner_bank_loan->id)->orderBy('id', 'DESC')->skip($offset)->limit($limit)->get();
        $comment_lists = [];
        foreach ($comments as $comment) {
            array_push($comment_lists, [
                'id'         => $comment->id,
                'comment'    => $comment->comment,
                'user'       => [
                    'name'  => $comment->commentator->profile ? $comment->commentator->profile->name : $comment->commentator->name,
                    'image' => $comment->commentator->profile ? $comment->commentator->profile->pro_pic : $comment->commentator->pro_pic
                ],
                'created_at' => (Carbon::parse($comment->created_at))->format('j F, Y h:i A')
            ]);
        }
        if (count($comment_lists) == 0) return api_response($request, null, 404);

        return api_response($request, $comment_lists, 200, ['comment_lists' => $comment_lists]);
    }

    /**
     * @param Request $request
     * @param $loan_id
     * @param $bank_id
     * @param Loan $loan
     * @return JsonResponse
     * @throws AlreadyAssignToBank
     */
    public function assignBank(Request $request, $loan_id, $bank_id, Loan $loan)
    {
        $loan->assignBank($loan_id, $bank_id);
        return api_response($request, true, 200);
    }

    /**
     * @param Request $request
     * @param $loan_id
     * @param Loan $loan
     * @return JsonResponse
     * @throws NotAllowedToAccess
     * @throws \ReflectionException
     */
    public function uploadDocuments(Request $request, $loan_id, Loan $loan)
    {
        $this->validate($request, [
            'picture' => 'required|mimes:jpg,jpeg,png,pdf',
            'name'    => 'required',
            'for'     => 'required|in:profile,nominee_document,grantor_document,business_document,extras,retailer_document,proof_of_photograph'
        ]);
        $loan->uploadDocument($loan_id, $request);
        return api_response($request, true, 200);
    }

    /**
     * @param Request $request
     * @param $loan_id
     * @param Loan $loan
     * @return JsonResponse|\Illuminate\Http\Response|string
     * @throws NotAllowedToAccess
     * @throws Throwable
     * @throws \Mpdf\MpdfException
     * @throws \ReflectionException
     * @throws \Sheba\Reports\Exceptions\NotAssociativeArray
     */
    public function generateApplication(Request $request, $loan_id, Loan $loan)
    {
        $data                   = $loan->show($loan_id);
        $ownership_type         = $data['final_information_for_loan']['business']['ownership_type'];
        $data['ownership_type'] = config('constants.ownership_type_en.' . $ownership_type);
        $pdf_handler            = new PdfHandler();
        $loan_application_name  = 'loan_application_' . $loan_id;
        if ($request->has('pdf_type') && $request->pdf_type == constants('BANK_LOAN_PDF_TYPES')['SanctionLetter']) {
            $loan_application_name       = 'sanction_letter_' . $loan_id;
            $data['sanction_issue_date'] = $loan->getSanctionIssueDate($loan_id);
            return $pdf_handler->setData($data)->setName($loan_application_name)->setViewFile('partner_loan_sanction_letter_form')->download();
        }
        if ($request->has('pdf_type') && $request->pdf_type == constants('BANK_LOAN_PDF_TYPES')['ProposalLetter']) {
            $loan_application_name = 'proposal_letter_' . $loan_id;
            return $pdf_handler->setData($data)->setName($loan_application_name)->setViewFile('partner_loan_proposal_letter')->download();
        }
        if ($data["loan_type"] === LoanTypes::MICRO) {
            $loan_application_name = 'dana_classic_' . $loan_id;
            if( $request->has("from_admin")) {
                $pdf = $pdf_handler->setData($data)->setName($loan_application_name)->setViewFile('dana_classic_application_form')->save(true);
                return api_response($request, null, 200, ['data' => $pdf]);
            }
            $pdf_handler->setData($data)->setName($loan_application_name)->setViewFile('dana_classic_application_form')->download(true);
        }
        if( $request->has("from_admin")) {
            $pdf = $pdf_handler->setData($data)->setName($loan_application_name)->setViewFile('partner_loan_application_form')->save();
            return api_response($request, null, 200, ['data' => $pdf]);
        }
        return $pdf_handler->setData($data)->setName($loan_application_name)->setViewFile('partner_loan_application_form')->download();
    }

    /**
     * @param Request $request
     * @param $loan_id
     * @param Loan $loan
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws NotAllowedToAccess
     */
    public function downloadDocuments(Request $request, $loan_id, Loan $loan)
    {
        if ($request->has('url')) {
            $file = $loan->downloadFromUrl($request->get('url'));
            if (!$file) {
                return api_response($request, null, 404);
            }
            return response()->download($file);
        }
        $doc = $loan->downloadDocuments($loan_id);
        if (!$doc) return api_response($request, null, 500);

        return api_response($request, $doc, 200, ['link' => $doc]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatus(Request $request)
    {
        $statuses = constants('LOAN_STATUS');
        $statuses = array_map(function ($status) {
            return ucfirst(preg_replace('/_/', ' ', $status));
        }, $statuses);
        return api_response($request, $statuses, 200, ['data' => $statuses]);
    }

    /**
     * @param Request $request
     * @param         $loan_id
     * @param Loan $loan
     * @return JsonResponse
     * @throws LoanNotFoundException
     * @throws NotAllowedToAccess
     * @throws \ReflectionException
     */
    public function showForAgent(Request $request, $loan_id, Loan $loan)
    {
        $data = $loan->showForAgent($loan_id);
        return api_response($request, $data, 200, ['data' => $data]);
    }

    public function getChangeLogsForAgent(Request $request, PartnerBankLoan $partner_bank_loan)
    {
        (new RequestValidator($partner_bank_loan))->validate();
        list($offset, $limit) = calculatePagination($request);
        $partner_bank_loan_logs = $partner_bank_loan->changeLogs()
                                                    ->where('title', 'like', '%retailer_document%')->orderBy('created_at', 'DESC')->limit($limit)->offset($offset)->get();
        $output                 = $partner_bank_loan_logs->sortByDesc('id')->values();
        return api_response($request, null, 200, ['logs' => $output]);
    }

    public function deleteDocument(Request $request, PartnerBankLoan $partner_bank_loan, Loan $loan)
    {
        $this->validate($request, ['for' => 'required', 'key' => 'required']);
        (new RequestValidator($partner_bank_loan))->validate();
        (new DocumentDeleter($partner_bank_loan))->setUser($request->user)->setFor($request->for)->setKey($request->key)->delete();
        return api_response($request, null, 200);
    }

    public function uploadRetailerList(Request $request,Loan $loan)
    {
        $this->validate($request, [
             'retailers' => 'required|mimes:csv,txt',
             'strategic_partner_id' => 'required'
        ]);
        $loan->uploadRetailerList($request);
        return api_response($request, null, 200);
    }

    public function strategicPartnerDashboard(Request $request,Loan $loan)
    {
        $this->validate($request, [
            'strategic_partner_id' => 'required'
        ]);
        return api_response($request, null, 200);
    }

    /**
     * @param Request $request
     * @param $partner
     * @param $loan_id
     * @param LoanAccount $loan_account
     * @param Loan $loan
     * @return JsonResponse
     * @throws NotAllowedToAccess
     */
    public function accountInfo(Request $request, $partner, $loan_id, LoanAccount $loan_account,Loan $loan)
    {
        $request->merge(['loan_id' => $loan_id]);
        $loan->validateRequest($request);
        $data = $loan_account->accountInfo($request);
        return api_response($request, null, 200, ['data' => $data]);
    }
}
