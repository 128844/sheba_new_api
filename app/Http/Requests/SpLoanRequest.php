<?php namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Http\Request as HttpRequest;

class SpLoanRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function all()
    {
        $all = parent::all();
        return $all; // TODO: Change the autogenerated stub`
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if (HttpRequest::segment(5) == "personal-info") {
            $rules = [
                'gender' => 'required|string|in:Male,Female,Other,পুরুষ,মহিলা,অন্যান্য',
                'dob' => 'required|date|date_format:Y-m-d|before:' . Carbon::today()->format('Y-m-d'),
                'address' => 'required|string',
                'permanent_address' => 'required|string',
                'father_name' => 'required_without:spouse_name',
                'spouse_name' => 'required_without:father_name',
                'occupation' => 'required|string',
                'monthly_living_cost' => 'required|numeric',
                'total_asset_amount' => 'required|numeric',
                'monthly_loan_installment_amount' => 'required|numeric'
            ];

        }

        if (HttpRequest::segment(5) == "business-info") {
            $rules = [
                'business_type' => 'required|string',
                'location' => 'required|string',
                'establishment_year' => 'required|date|date_format:Y-m-d|before:' . Carbon::today()->format('Y-m-d'),
                'full_time_employee' => 'required|numeric',
                'part_time_employee' => 'required|numeric',
                'sales_information' => 'required',
                'business_additional_information' => 'required'
            ];

        }

        if (HttpRequest::segment(5) == "finance-info") {
            $rules = [
                'acc_name' => 'required|string',
                'acc_no' => 'required|integer',
                'bank_name' => 'required|string',
                'branch_name' => 'required|string',
                'acc_type' => 'required|string|in:savings,current,সেভিংস,কারেন্ট',
                'bkash_no' => 'required|string|mobile:bd',
                'bkash_account_type' => 'required|string|in:personal,agent,merchant,পার্সোনাল,এজেন্ট,মার্চেন্ট'
            ];

        }
        if (HttpRequest::segment(5) == "nominee-info") {
            $rules = [
                'name' => 'required|string',
                'mobile' => 'required|string|mobile:bd',
                'nominee_relation' => 'required|string'
            ];

        }

        if (HttpRequest::segment(5) == "grantor-info") {
            $rules = [
                'name' => 'required|string',
                'mobile' => 'required|string|mobile:bd',
                'nominee_relation' => 'required|string'
            ];

        }

        if (HttpRequest::segment(4) == "loans" && HttpRequest::segment(5) == null) {
            $rules = [
                'bank_name' => 'required|string',
                'loan_amount' => 'required|numeric',
                'duration' => 'required|integer',
                'monthly_installment' => 'required|numeric',
                'status' => 'required|string',
            ];

        }
        return $rules;
    }

    public function messages()
    {
        $messages = parent::messages(); // TODO: Change the autogenerated stub
        return $messages;
    }
}
