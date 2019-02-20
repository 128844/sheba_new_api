<?php namespace Sheba\AppSettings\HomePageSetting;

class AvailableItems
{
    private $items;

    public function __construct()
    {
        $this->items = [
            'slider' => [
                "name" => "Slider",
                "model" => "App\\Models\\Slider"
            ],
            'grid' => [
                "name" => "Grid",
                "model" => "App\\Models\\Grid"
            ],
            'category_group' => [
                "name" => "Category Group",
                "model" => "App\\Models\\CategoryGroup"
            ],
            'offer' => [
                "name" => "Offer",
                "model" => "App\\Models\\OfferShowcase"
            ]
        ];
    }

    public function get()
    {
        return $this->items;
    }

    public function getKeyByName($name)
    {
        return $this->getKeyFromValues($this->getModels(), $name);
    }

    public function getKeyByModel($model)
    {
        return $this->getKeyFromValues($this->getModels(), $model);
    }

    private function getKeyFromValues($items, $search)
    {
        foreach ($items as $key => $item) {
            if($item == $search) return $key;
        }
        throw new \InvalidArgumentException('Provided needle or haystack is not valid.');
    }

    public function getName($key)
    {
        return $this->getNames()[$key];
    }

    public function getModel($key)
    {
        return $this->getModels()[$key];
    }

    public function getNames()
    {
        return $this->mapAvailableItemsWithKey('name');
    }

    public function getModels()
    {
        return $this->mapAvailableItemsWithKey('model');
    }

    private function mapAvailableItemsWithKey($key)
    {
        $items = [];
        array_walk($this->items, function($item, $index) use (&$items, $key) {
            $items[$index] = $item[$key];
        });
        return $items;
    }
}