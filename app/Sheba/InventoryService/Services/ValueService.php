<?php namespace App\Sheba\InventoryService\Services;


use App\Sheba\InventoryService\InventoryServerClient;

class ValueService
{
    public $partnerId;
    public $modifier;
    public $name;
    public $client;
    public $optionId;
    public $valueId;

    /**
     * ValueService constructor.
     * @param $client
     */
    public function __construct(InventoryServerClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param mixed $partnerId
     * @return ValueService
     */
    public function setPartnerId($partnerId)
    {
        $this->partnerId = $partnerId;
        return $this;
    }

    /**
     * @param mixed $modifier
     * @return ValueService
     */
    public function setModifier($modifier)
    {
        $this->modifier = $modifier;
        return $this;
    }

    /**
     * @param mixed $name
     * @return ValueService
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param mixed $valueId
     * @return ValueService
     */
    public function setValueId($valueId)
    {
        $this->valueId = $valueId;
        return $this;
    }

    /**
     * @param mixed $optionId
     * @return ValueService
     */
    public function setOptionId($optionId)
    {
        $this->optionId = $optionId;
        return $this;
    }

    private function makeData()
    {
        $data = [];
        $data['name'] = $this->name;
        $data['modifier']  = $this->modifier;
        $data['partner_id'] = $this->partnerId;
        return $data;
    }

    public function store()
    {
        $data = $this->makeData();
        return $this->client->post('api/v1/partners/'.$this->partnerId.'/options/'.$this->optionId.'/values', $data);
    }

    public function update()
    {
        $data = $this->makeData();
        return $this->client->put('api/v1/partners/'.$this->partnerId.'/values/'.$this->valueId, $data);
    }


}