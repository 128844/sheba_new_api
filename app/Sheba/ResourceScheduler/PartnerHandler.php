<?php namespace Sheba\ResourceScheduler;

use App\Models\Category;
use App\Models\Partner;

class PartnerHandler
{
    private $partner;

    public function __construct(Partner $partner)
    {
        $this->partner = $partner;
    }

    public function isAvailable($date, $time, $category)
    {
        $is_available = false;
        $available_resources = collect([]);
        $unavailable_resources = collect([]);
        $category = Category::find($category);

        $this->partner->resourcesInCategory($category)->each(function ($resource) use ($date, $time, &$is_available, &$available_resources, &$unavailable_resources, $category) {
            if (scheduler($resource)->isAvailableForCategory($date, $time, $category)) {
                $available_resources->push($resource);
                $is_available = true;
            } else {
                $unavailable_resources->push($resource);
            }
        });

        return collect([
            'is_available' => $is_available,
            'available_resources' => $available_resources,
            'unavailable_resources' => $unavailable_resources
        ]);
    }
}