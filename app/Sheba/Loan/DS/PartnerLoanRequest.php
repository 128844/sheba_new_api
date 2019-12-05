<?php

namespace Sheba\Loan\DS;

use App\Models\PartnerBankLoan;
use App\Models\PartnerBankLoanChangeLog;
use Illuminate\Contracts\Support\Arrayable;
use Sheba\ModificationFields;

class PartnerLoanRequest implements Arrayable
{
    use ModificationFields;
    public $partnerBankLoan;
    public $partner;
    public $bank;
    public $loan_amount;
    public $status;
    public $duration;
    public $monthly_installment;
    public $interest_rate;
    /** @var LoanRequestDetails $final_details */
    public $final_details;
    public $created_by;
    public $updated_by;
    public $created;
    public $updated;

    public function __construct(PartnerBankLoan $request = null)
    {
        $this->partnerBankLoan = $request;
        if ($this->partnerBankLoan) {
            $this->setPartner($this->partnerBankLoan->partner);
            $this->setDetails();
        }
    }

    public function setDetails()
    {
        $this->final_details = new LoanRequestDetails($this);
    }

    /**
     * @return mixed
     */
    public function getPartner()
    {
        return $this->partner;
    }

    /**
     * @param mixed $partner
     * @return PartnerLoanRequest
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @param PartnerBankLoan $partnerBankLoan
     * @return PartnerLoanRequest
     */
    public function setPartnerBankLoan($partnerBankLoan)
    {
        $this->partnerBankLoan = $partnerBankLoan;
        return $this;
    }

    public function create($data)
    {
        $data['partner_id']          = $this->partner->id;
        $data['status']              = constants('LOAN_STATUS')['applied'];
        $data['interest_rate']       = constants('LOAN_CONFIG')['interest'];
        $data['monthly_installment'] = ((double)$data['loan_amount'] + ((double)$data['loan_amount'] * ($data['interest_rate'] / 100))) / ((int)$data['duration'] * 12);
        $this->setModifier($this->partner);
        $this->partnerBankLoan = new PartnerBankLoan($this->withCreateModificationField($data));
        $this->setDetails();
        $this->partnerBankLoan->save();
        return $this->partnerBankLoan;
    }

    public function __get($name)
    {
        if ($this->partnerBankLoan) {
            return $this->partnerBankLoan->{$name};
        } else {
            return $this->{$name};
        }
    }

    public function history()
    {
        return [
            'id'      => $this->partnerBankLoan->id,
            'details' => (new LoanHistory($this->partnerBankLoan))->toArray()
        ];

    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function details()
    {
        return $this->toArray();
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     * @throws \ReflectionException
     */
    public function toArray()
    {
        $bank   = $this->partnerBankLoan->bank()->select('name', 'id', 'logo')->first();
        $output = $this->getNextStatus($this->partnerBankLoan->id);
        $generated_id = $bank->id.'-'.str_pad($this->partnerBankLoan->id,8-strlen($this->partnerBankLoan->id),'0',STR_PAD_LEFT);
        return [
            'id'                         => $this->partnerBankLoan->id,
            'generated_id'               => $generated_id,
            'partner'                    => [
                'id'      => $this->partner->id,
                'name'    => $this->partner->name,
                'logo'    => $this->partner->logo,
                'profile' => [
                    'name'   => $this->partner->getContactPerson(),
                    'mobile' => $this->partner->getContactNumber(),
                    'is_nid_verified' => $this->partner->isNIDVerified(),
                ]
            ],
            'credit_score'               => $this->partnerBankLoan->credit_score,
            'purpose'                    => $this->partnerBankLoan->purpose,
            'bank'                       => $bank ? $bank->toArray() : null,
            'duration'                   => $this->partnerBankLoan->duration,
            'interest_rate'              => $this->partnerBankLoan->interest_rate,
            'status'                     => [
                                                'name' => ucfirst(preg_replace('/_/', ' ', $this->partnerBankLoan->status)),
                                                'status' => $this->partnerBankLoan->status
                                            ],
            'monthly_installment'        => $this->partnerBankLoan->monthly_installment,
            'loan_amount'                => $this->partnerBankLoan->loan_amount,
            'total_installment'          => (int)$this->partnerBankLoan->duration * 12,
            'status_'                    => constants('LOAN_STATUS_BN')[$this->partnerBankLoan->status],
            'final_information_for_loan' => $this->final_details->toArray(),
            'next_status'                => $output
        ];
    }

    private function getNextStatus($loan_id)
    {
        $status_res = [
            'applied'         => 'submitted',
            'submitted'       => 'verified',
            'verified'        => 'approved',
            'approved'        => 'sanction_issued',
            'sanction_issued' => 'disbursed',
            'disbursed'       => 'closed',
            'considerable'    => 'verified',
            'rejected'        => 'closed',
        ];
        $all        = [
            'declined',
            'hold',
            'withdrawal'
        ];

        if ($this->partnerBankLoan->status == 'declined')
            $new_status = ['declined'];
        else if ($this->partnerBankLoan->status == 'withdrawal')
            $new_status = ['withdrawal'];
        else if ($this->partnerBankLoan->status == 'disbursed')
            $new_status = ['closed'];
        else if ($this->partnerBankLoan->status == 'closed')
            $new_status = ['closed'];
        else if ($this->partnerBankLoan->status == 'hold'){
            $change_log = PartnerBankLoanChangeLog::where('loan_id',$loan_id)->orderby('id','desc')->first();
            $status_before_hold = $change_log['from'];
            $new_status = array_merge([$status_res[$status_before_hold]], ['declined','withdrawal']);
        }
        else
            $new_status = array_merge([$status_res[$this->partnerBankLoan->status]], $all);
        $output = [];
        foreach ($new_status as $status) {
            $output[] = [
                'name' => ucfirst(preg_replace('/_/', ' ', $status)),
                'status' => $status,
                'extras' => constants('LOAN_STATUS_BN')[$status]
            ];
        }
        return $output;

    }

    public function listItem()
    {
        $bank = $this->partnerBankLoan->bank()->select('name', 'id', 'logo')->first();
        return [
            'id'              => $this->partnerBankLoan->id,
            'generated_id'    => $bank->id.'-'.str_pad($this->partnerBankLoan->id,8-strlen($this->partnerBankLoan->id),'0',STR_PAD_LEFT),
            'created_at'      => $this->partnerBankLoan->created_at->format('d M, Y'),
            'name'            => $this->partnerBankLoan->partner->getContactPerson(),
            'phone'           => $this->partnerBankLoan->partner->getContactNumber(),
            'partner'         => $this->partnerBankLoan->partner->name,
            'status'          => ucfirst(preg_replace('/_/', ' ', $this->partnerBankLoan->status)),
            'status_'         => constants('LOAN_STATUS_BN')[$this->partnerBankLoan->status],
            'created_by'      => $this->partnerBankLoan->created_by,
            'updated_by'      => $this->partnerBankLoan->updated_by,
            'created_by_name' => $this->partnerBankLoan->created_by_name,
            'updated_by_name' => $this->partnerBankLoan->updated_by_name,
            'updated'         => $this->partnerBankLoan->updated_at->format('d M, Y'),
            'bank'            => $bank ? $bank->toArray() : null
        ];
    }

    public function storeChangeLog($user, $title, $from, $to, $description)
    {
        $this->setModifier($user);
        return $this->partnerBankLoan->changeLogs()->create($this->withCreateModificationField([
            'title'       => $title,
            'from'        => $from,
            'to'          => $to,
            'description' => $description
        ]));
    }
}
