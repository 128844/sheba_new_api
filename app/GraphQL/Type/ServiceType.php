<?php

namespace App\GraphQL\Type;

use \Folklore\GraphQL\Support\Type as GraphQlType;
use GraphQL\Type\Definition\Type;
use GraphQL;

class ServiceType extends GraphQlType
{
    protected $attributes = [
        'name' => 'Service',
        'description' => 'Sheba service'
    ];

    public function fields()
    {
        return [
            'id' => ['type' => Type::int()],
            'category_id' => ['type' => Type::int()],
            'name' => ['type' => Type::string()],
            'slug' => ['type' => Type::string()],
            'description' => ['type' => Type::string()],
            'short_description' => ['type' => Type::string()],
            'unit' => ['type' => Type::string()],
            'min_quantity' => ['type' => Type::float()],
            'publication_status' => ['type' => Type::int(), 'description' => 'Indicates if service is published or not; 1 or 0'],
            'thumb' => ['type' => Type::string()],
            'app_thumb' => ['type' => Type::string()],
            'banner' => ['type' => Type::string()],
            'screen' => ['type' => Type::string()],
            'start_price' => ['type' => Type::float()],
            'faqs' => ['type' => Type::string(), 'description' => 'Frequently asked questions for this service'],
            'type' => ['type' => Type::string(), 'description' => 'Available types: Fixed,Options,Custom'],
            'options' => ['type' => Type::listOf(GraphQL::type('ServiceQuestion')), 'description' => 'Q&A of service, can be null'],
            'category' => ['type' => GraphQL::type('Category')]
        ];
    }

    protected function resolveTypeField($root, $args)
    {
        return $root->variable_type;
    }

    protected function resolveOptionsField($root, $args)
    {
        if ($root->variable_type == 'Options') {
            return json_decode($root->variables)->options;
        } else {
            return null;
        }
    }

    protected function resolveScreenField($root)
    {
        if ($root->variable_type == 'Options') {
            $questions = (collect(json_decode($root->variables)->options))->pluck('question');
            if (count($questions) > 3) {
                return "slide";
            }
        }
        return "normal";
    }

    public function resolveStartPriceField($root)
    {
        try {
            $price = [];
            $partners = $root->partners->where('status', 'Verified');
            if ($partners->count() == 0) return 0;
            foreach ($partners as $partner) {
                $partner_service = $partner->pivot;
                if (!($partner_service->is_verified && $partner_service->is_published)) continue;
                $prices = (array)json_decode($partner_service->prices);
                array_push($price, min($prices));
            }
            return (double)min($price) * (double)$root->min_quantity;
        } catch (\Throwable $e) {
            return 0;
        }
    }

}