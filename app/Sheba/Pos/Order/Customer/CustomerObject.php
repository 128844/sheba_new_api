<?php namespace App\Sheba\Pos\Order\Customer;


class CustomerObject
{
    private $id;
    private $name;
    private $mobile;

    /**
     * @param mixed $id
     * @return CustomerObject
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param mixed $name
     * @return CustomerObject
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param mixed $mobile
     * @return CustomerObject
     */
    public function setMobile($mobile)
    {
        $this->mobile = $mobile;
        return $this;
    }

    public function __get($value)
    {
        return $this->{$value};
    }

}