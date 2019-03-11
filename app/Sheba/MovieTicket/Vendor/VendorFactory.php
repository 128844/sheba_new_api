<?php namespace Sheba\MovieTicket\Vendor;

use App\Models\MovieTicketVendor;
use ReflectionClass;
use Sheba\MovieTicket\Vendor\BlockBuster\BlockBuster;

class VendorFactory
{
    const BlockBuster = 1;

    private $classes = [
        BlockBuster::class
    ];

    /**
     * @param $id
     * @return Vendor
     * @throws \Exception
     */
    public function getById($id)
    {
        if(!in_array($id, $this->getConstants())) {
            throw new \Exception('Invalid Vendor');
        }
        return app($this->classes[$id - 1])->setModel($this->getModel($id));
    }

    /**
     * @param $name
     * @return Vendor
     * @throws \Exception
     */
    public function getByName($name)
    {
        if(!in_array($name, array_keys($this->getConstants()))) {
            throw new \Exception('Invalid Vendor');
        }
        $id = $this->getConstants()[$name];
        return app($this->classes[$id - 1])->setModel($this->getModel($id));
    }

    /**
     * @param $name
     * @return Vendor
     * @throws \Exception
     */
    public function getIdByName($name)
    {
        if(!in_array($name, array_keys($this->getConstants()))) {
            throw new \Exception('Invalid Vendor');
        }
        return $this->getConstants()[$name];
    }

    /**
     * @param $mobile
     * @return Vendor
     * @throws \Exception
     */
    public function getByMobile($mobile)
    {
        return $this->getById(1);
    }

    public function getModel($id)
    {
        return MovieTicketVendor::find($id);
    }

    private function getConstants()
    {
        $oClass = new ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }
}