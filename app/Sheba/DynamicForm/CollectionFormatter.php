<?php

namespace App\Sheba\DynamicForm;

class CollectionFormatter
{

    private $data;

    public function setData($data): CollectionFormatter
    {
        $this->data = $data;
        return $this;
    }

    public function formatCollection()
    {
        for ($i = 0; $i < count($this->data); $i++) {
            $this->data[$i]['key'] = $this->data[$i]['id'];
            unset($this->data[$i]['id']);
            $this->data[$i]['value'] = $this->data[$i]['bn_name'];
            unset($this->data[$i]['bn_name']);
        }
        return $this->data;
    }

    public function formatCollectionUpdated()
    {
        for ($i = 0; $i < count($this->data); $i++) {
            $this->data[$i]->key = ucfirst(strtolower($this->data[$i]->division));
            unset($this->data[$i]->id);
            $this->data[$i]->value = ucfirst(strtolower($this->data[$i]->division));
            $this->data[$i]->name = ucfirst(strtolower($this->data[$i]->division));
            unset($this->data[$i]->bn_name);
            unset($this->data[$i]->thana);
            unset($this->data[$i]->district);
            unset($this->data[$i]->branch_name);
            unset($this->data[$i]->branch_code);
            unset($this->data[$i]->division);
        }
        return $this->data;
    }

    public function formatCollectionDistrict()
    {
        for ($i = 0; $i < count($this->data); $i++) {
            $this->data[$i]->key = $this->data[$i]->district;
            $this->data[$i]->value = $this->data[$i]->district;
            $this->data[$i]->name = $this->data[$i]->district;
            unset($this->data[$i]->thana);
            unset($this->data[$i]->district);
            unset($this->data[$i]->division);
            unset($this->data[$i]->branch_name);
            unset($this->data[$i]->branch_code);
        }
        return $this->data;
    }

    public function formatCollectionThana()
    {
        for ($i = 0; $i < count($this->data); $i++) {
            $this->data[$i]->key = $this->data[$i]->thana;
            $this->data[$i]->value = $this->data[$i]->thana;
            $this->data[$i]->name = $this->data[$i]->thana;
            unset($this->data[$i]->thana);
            unset($this->data[$i]->district);
            unset($this->data[$i]->division);
            unset($this->data[$i]->branch_name);
            unset($this->data[$i]->branch_code);
        }
        return $this->data;
    }
}
