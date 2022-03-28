<?php namespace App\Http\Controllers\Mtb;


use App\Http\Controllers\Controller;
use App\Sheba\MtbOnboarding\MtbSavePrimaryInformation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class MtbController extends Controller
{
    /**
     * @var MtbSavePrimaryInformation
     */
    private $mtbSavePrimaryInformation;

    public function __construct(MtbSavePrimaryInformation $mtbSavePrimaryInformation)
    {
        $this->mtbSavePrimaryInformation = $mtbSavePrimaryInformation;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function apply(Request $request): JsonResponse
    {
        $partner = $request->auth_user->getPartner();
        return $this->mtbSavePrimaryInformation->setPartner($partner)->storePrimaryInformationToMtb($request);

    }
}
