<?php namespace App\Http\Controllers\Accounting;


use App\Http\Controllers\Controller;
use App\Sheba\AccountingEntry\Constants\EntryTypes;
use App\Sheba\AccountingEntry\Repository\AccountingRepository;
use Illuminate\Http\Request;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\ModificationFields;

class IncomeExpenseController extends Controller
{
    use ModificationFields;

    /** @var AccountingRepository */
    private $accountingRepo;

    public function __construct(AccountingRepository $accountingRepo) {
        $this->accountingRepo = $accountingRepo;
    }

    public function storeIncomeEntry(Request $request) {
        try {
            $this->validate($request, [
                'amount' => 'required|numeric',
                'from_account_key' => 'required',
                'to_account_key' => 'required',
                'date' => 'required|date_format:Y-m-d H:i:s',
                'amount_cleared' => 'sometimes|required|numeric'
            ]);
            if($request->amount_cleared && $request->amount > $request->amount_cleared) {
                $this->validate($request, ['customer_id' => 'required']);
            }
            $response = $this->accountingRepo->storeEntry($request, EntryTypes::INCOME);
            return api_response($request, $response, 200, ['data' => $response]);
        } catch (AccountingEntryServerError $e) {
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        }
    }

    public function storeExpenseEntry(Request $request) {
        try {
            $this->validate($request, [
                'amount' => 'required|numeric',
                'from_account_key' => 'required',
                'to_account_key' => 'required',
                'date' => 'required|date_format:Y-m-d H:i:s',
                'amount_cleared' => 'sometimes|required|numeric'
            ]);
            if($request->amount_cleared && $request->amount > $request->amount_cleared) {
                $this->validate($request, ['customer_id' => 'required']);
            }
//            $product = (json_decode($request->inventory_products, true));
            $type = count(json_decode($request->inventory_products, true)) ? EntryTypes::INVENTORY : EntryTypes::EXPENSE;
            $response = $this->accountingRepo->storeEntry($request, $type);
            return api_response($request, $response, 200, ['data' => $response]);
        } catch (AccountingEntryServerError $e) {
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        }
    }

}