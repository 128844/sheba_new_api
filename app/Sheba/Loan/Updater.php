<?php

namespace Sheba\Loan;

use App\Models\PartnerBankLoan;
use Illuminate\Http\Request;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Sheba\Loan\DS\PartnerLoanRequest;
use Sheba\ModificationFields;

class Updater
{
    private $old, $new, $difference;
    use ModificationFields;

    public function __construct($old, $new)
    {
        $this->old        = $old;
        $this->new        = $new;
        $this->difference = [];
    }

    public function getDifference()
    {
        return $this->difference;
    }

    public function findDifference()
    {
        $flat_old = $this->flatData($this->old);
        $flat_new = $this->flatData($this->new);
        $old      = [];
        $new      = [];
        foreach ($flat_old as $key => $value) {
            $old[$key] = $value;
        }
        foreach ($flat_new as $key => $value) {
            $new[$key] = $value;
        }
        $this->evaluateDifference($old, $new);
        return $this;
    }

    private function flatData($data)
    {
        return new RecursiveIteratorIterator(new RecursiveArrayIterator($data));
    }

    private function evaluateDifference($old, $new)
    {
        foreach ($new as $key => $value) {
            if (array_key_exists($key, $old)) {
                if ($new[$key] != $old[$key])
                    array_push($this->difference, [
                        'title'   => 'change ' . $key,
                        'old'     => $old[$key],
                        'new'     => $new[$key],
                        'details' => null
                    ]);
            } else {
                array_push($this->difference, [
                    'title'   => 'add ' . $key,
                    'old'     => 'none',
                    'new'     => $new[$key],
                    'details' => null
                ]);
            }
        }
    }

    public function update(PartnerLoanRequest $loan, Request $request)
    {
        foreach (self::updateFields() as $key) {
            $loan->partnerBankLoan->{$$key} = array_key_exists($key, $this->new) ? $this->new[$key] : $loan->partnerBankLoan->{$key};
        }
        $loan->partnerBankLoan->final_information_for_loan = json_encode($this->new['final_information_for_loan']);
        $loan->partnerBankLoan->save();
        $this->setModifier($request->user);
        $loan->partnerBankLoan->update($this->withUpdateModificationField([]));
    }

    public static function updateFields()
    {
        return [
            'credit_score',
            'duration',
            'purpose',
            'interest_rate',
            'loan_amount'
        ];
    }

    private function loanRequestUpdate(PartnerBankLoan $loan, $field)
    {

        return $loan;
    }
}
