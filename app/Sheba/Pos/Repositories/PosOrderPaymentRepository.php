<?php namespace Sheba\Pos\Repositories;

use App\Models\PosOrderPayment;
use Sheba\Repositories\BaseRepository;

class PosOrderPaymentRepository extends BaseRepository
{
    /**
     * @param array $data
     * @return PosOrderPayment
     */
    public function save(array $data)
    {
        return PosOrderPayment::create($this->withCreateModificationField($data));
    }
}