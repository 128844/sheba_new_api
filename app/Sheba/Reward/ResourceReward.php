<?php namespace Sheba\Reward;

use App\Models\PartnerResource;
use App\Models\Resource;
use App\Models\Reward;
use Illuminate\Support\Facades\DB;

class ResourceReward extends ShebaReward
{
    /** @var Resource* */
    private $resource;
    private $limit;
    private $offset;
    private $type;

    public function __construct()
    {
        $this->limit = 100;
        $this->offset = 0;
    }

    /**
     * @param int $limit
     * @return ResourceReward
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @param int $type
     * @return ResourceReward
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param int $offset
     * @return ResourceReward
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @param Resource $resource
     * @return ResourceReward
     */
    public function setResource($resource)
    {
        $this->resource = $resource;
        return $this;
    }

    private function isValidReward($reward)
    {
        $category_constraints = $reward->constraints->where('constraint_type', constants('REWARD_CONSTRAINTS')['category']);
        $category_pass = $category_constraints->count() == 0;

        $package_constraints = $reward->constraints->where('constraint_type', constants('REWARD_CONSTRAINTS')['partner_package']);
        $package_pass = $package_constraints->count() == 0;
        if (!$category_pass) $category_pass = $this->checkForCategory($category_constraints);
        if (!$package_pass) $package_pass = $this->checkForPackage($package_constraints);
        return $category_pass && $package_pass;
    }

    private function checkForCategory($category_constraints)
    {
        $reward_categories = $category_constraints->pluck('constraint_id')->unique()->toArray();
        $partner_resources = PartnerResource::with('categories')->handyman()->select('id')->where('resource_id', $this->resource->id)->get();
        if ($partner_resources->count() == 0) return false;
        $category_partner_resources = DB::table('category_partner_resource')->where('partner_resource_id', $partner_resources->pluck('id')->toArray())
            ->select('category_id')->get();
        foreach ($category_partner_resources as $category_partner_resource) {
            if (in_array($category_partner_resource->category_id, $reward_categories)) return true;
        }
        return false;
    }

    private function checkForPackage($package_constraints)
    {
        return in_array($this->resource->partners[0]->package_id, $package_constraints->pluck('constraint_id')->unique()->toArray());
    }

    public function running()
    {
        // TODO: Implement running() method.
    }


    public function upcoming()
    {
        $rewards = Reward::upcoming()->forResource()->with('constraints')->skip($this->offset)->take($this->limit);
        if ($this->type === 'campaign') $rewards = $rewards->typeCampaign();
        if ($this->type === 'action') $rewards = $rewards->typeAction();
        $rewards = $rewards->get();
        $final = [];
        foreach ($rewards as $reward) {
            if ($this->isValidReward($reward)) array_push($final, $reward);
        }
        return $final;
    }
}