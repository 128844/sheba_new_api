<?php

namespace App\Repositories;


use App\Models\Category;
use App\Models\HyperLocal;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerWorkingHour;
use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;
use Sheba\ModificationFields;

class PartnerRepository
{
    use ModificationFields, CdnFileManager, FileManager;

    private $partner;
    private $serviceRepo;

    public function __construct($partner)
    {
        $this->partner = $partner instanceof Partner ? $partner : Partner::find($partner);
        $this->serviceRepo = new ServiceRepository();
    }

    public function handymanResources($verify = null, $category_id = null, $date = null, $preferred_time = null)
    {
        $resources = $this->partner->handymanResources()->get()->unique();
        $resources->load(['jobs' => function ($q) {
            $q->where('status', '<>', constants('JOB_STATUSES')['Cancelled']);
        }, 'profile' => function ($q) {
            $q->select('id', 'name', 'mobile', 'pro_pic');
        }, 'reviews' => function ($q) {
            $q->select('id', 'rating', 'resource_id', 'category_id');
        }]);
        if ($verify !== null && !$this->partner->isLite()) {
            $resources = $resources->filter(function ($resource) use ($verify) {
                return $resource->is_verified == $verify;
            });
        };
        $job = null;
        if ($category_id != null) {
            $resources = $resources->map(function ($resource) use ($category_id) {
                $resource_categories = $resource->categoriesIn($this->partner->id);
                $is_tagged = $resource_categories->pluck('id')->contains($category_id);
                array_add($resource, 'is_tagged', $is_tagged ? 1 : 0);
                array_add($resource, 'total_tagged_categories', count($resource_categories));
                return $resource;
            });
        }

        return $resources->map(function ($resource) use ($category_id, $date, $preferred_time) {
            $data = [];
            $data['id'] = $resource->id;
            $data['profile_id'] = $resource->profile_id;
            $ongoing_jobs = $resource->jobs->whereIn('status', [constants('JOB_STATUSES')['Serve_Due'], constants('JOB_STATUSES')['Accepted'], constants('JOB_STATUSES')['Process'], constants('JOB_STATUSES')['Schedule_Due']]);
            $data['ongoing'] = $ongoing_jobs->count();
            $data['completed'] = $resource->jobs->where('status', constants('JOB_STATUSES')['Served'])->count();
            $data['name'] = $resource->profile->name;
            $data['mobile'] = $resource->profile->mobile;
            $data['picture'] = $resource->profile->pro_pic;
            $avg_rating = $resource->reviews->avg('rating');
            $data['rating'] = $avg_rating != null ? round($avg_rating, 2) : null;
            $data['joined_at'] = $resource->pivot->created_at->timestamp;
            $data['resource_type'] = $resource->pivot->resource_type;
            $data['is_verified'] = $resource->is_verified;
            $data['is_available'] = $resource->is_tagged;
            $data['booked_jobs'] = [];
            $data['is_tagged'] = $resource->is_tagged;
            $data['total_tagged_categories'] = isset($resource->total_tagged_categories) ? count($resource->total_tagged_categories) : count($resource->categoriesIn($this->partner->id));
            if ($category_id) {
                $category = Category::find($category_id);
                if (in_array($category_id, array_map('intval', explode(',', env('RENT_CAR_IDS'))))) {
                    foreach ($ongoing_jobs->where('resource_id', $resource->id)->where('category_id', $category_id) as $job) {
                        array_push($data['booked_jobs'], array(
                            'job_id' => $job->id,
                            'partner_order_id' => $job->partnerOrder->id,
                            'code' => $job->partnerOrder->order->code()
                        ));
                    }
                } else {
                    $resource_scheduler = scheduler($resource);
                    if (!$resource_scheduler->isAvailableForCategory($date, explode('-', $preferred_time)[0], $category)) {
                        $data['is_available'] = 0;
                        foreach ($resource_scheduler->getBookedJobs() as $job) {
                            array_push($data['booked_jobs'], array(
                                'job_id' => $job->id,
                                'partner_order_id' => $job->partnerOrder->id,
                                'code' => $job->partnerOrder->order->code()
                            ));
                        }
                    }
                }
            }
            return $data;
        });
    }

    public function jobs(Array $statuses, $offset, $limit)
    {
        $this->partner->load(['jobs' => function ($q) use ($statuses, $offset, $limit) {
            $q->info()->status($statuses)->skip($offset)->take($limit)->orderBy('id', 'desc')->with(['jobServices.service', 'cancelRequests', 'category', 'usedMaterials' => function ($q) {
                $q->select('id', 'job_id', 'material_name', 'material_price');
            }, 'resource.profile', 'review', 'partner_order' => function ($q) {
                $q->with(['order' => function ($q) {
                    $q->with('location', 'customer.profile');
                }]);
            }]);
        }]);
        return $this->partner->jobs;
    }

    public function resolveStatus($status)
    {
        if ($status == 'new') {
            return array(constants('JOB_STATUSES')['Pending'], constants('JOB_STATUSES')['Not_Responded']);
        } elseif ($status == 'ongoing') {
            return array(constants('JOB_STATUSES')['Serve_Due'], constants('JOB_STATUSES')['Accepted'], constants('JOB_STATUSES')['Process'], constants('JOB_STATUSES')['Schedule_Due']);
        } elseif ($status == 'history') {
            return array(constants('JOB_STATUSES')['Served']);
        }
    }

    public function hasAppropriateCreditLimit()
    {
        return (double)$this->partner->wallet >= (double)$this->partner->walletSetting->min_wallet_threshold;
    }

    public function getLocations()
    {
        if ($this->partner->geo_informations) {
            $geo_info = json_decode($this->partner->geo_informations);
            if ($geo_info) {
                $hyper_locations = HyperLocal::insideCircle($geo_info)
                    ->with('location')
                    ->get()
                    ->filter(function ($item) {
                        return !empty($item->location);
                    })->pluck('location');
                return $hyper_locations;
            } else {
                return [];
            }
        } else {
            return Location::published()->select('id', 'name')->get();
        }

    }

    public function saveDefaultWorkingHours($by)
    {
        $default_working_days = getDefaultWorkingDays();
        $default_working_hours = getDefaultWorkingHours();

        foreach ($default_working_days as $day) {
            $this->partner->workingHours()->save(new PartnerWorkingHour(array_merge($by, [
                'day' => $day,
                'start_time' => $default_working_hours->start_time,
                'end_time' => $default_working_hours->end_time
            ])));
        }
    }

    /**
     * Save logo for partner to cdn.
     *
     * @param $request
     * @return string
     */
    public function saveLogo($request)
    {
        list($logo, $logo_filename) = $this->makeThumb($request->file('logo'), $this->partner->name);
        return $this->saveImageToCDN($logo, getPartnerLogoFolder(), $logo_filename);
    }

    /**
     * Delete old logo of the partner from cdn.
     *
     * @param $delete_both
     */
    private function _deleteOldLogo($delete_both = true)
    {
        if ($this->partner->logo != getPartnerDefaultLogo()) {
            $old_logo = substr($this->partner->logo, strlen(env('S3_URL')));
            $this->deleteImageFromCDN($old_logo);
            if ($delete_both && ($this->partner->logo_original != getPartnerDefaultLogo())) {
                $old_logo_original = substr($this->partner->logo_original, strlen(env('S3_URL')));
                $this->deleteImageFromCDN($old_logo_original);
            }
        }
    }

    /**
     * Update logo for partner.
     *
     * @param $request
     * @return string
     */
    public function updateLogo($request)
    {
        $this->_deleteOldLogo();
        $data['logo_original'] = $this->saveLogo($request);
        $data['logo'] = $data['logo_original'];
        return $this->partner->update($data);
    }
}

