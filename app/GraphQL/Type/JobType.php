<?php

namespace App\GraphQL\Type;

use Carbon\Carbon;
use Folklore\GraphQL\Support\Type as GraphQlType;
use GraphQL;
use GraphQL\Type\Definition\Type;

class JobType extends GraphQlType
{
    protected $attributes = [
        'name' => 'Job'
    ];

    public function fields()
    {
        return [
            'id' => ['type' => Type::int()],
            'completed_at' => ['type' => Type::string()],
            'additional_information' => ['type' => Type::string()],
            'price' => ['type' => Type::float()],
            'due' => ['type' => Type::float()],
            'status' => ['type' => Type::string()],
            'pickup_address' => ['type' => Type::string()],
            'pickup_address_geo' => ['type' => Type::string()],
            'pickup_area' => ['type' => Type::string()],
            'destination_area' => ['type' => Type::string()],
            'destination_address_geo' => ['type' => Type::string()],
            'destination_address' => ['type' => Type::string()],
            'schedule_date' => ['type' => Type::string()],
            'schedule_date_timestamp' => ['type' => Type::int()],
            'preferred_time' => ['type' => Type::string()],
            'preferred_time_readable' => ['type' => Type::string()],
            'completed_at_timestamp' => ['type' => Type::float()],
            'category' => ['type' => GraphQL::type('Category')],
            'review' => ['type' => GraphQL::type('Review')],
            'resource' => ['type' => GraphQL::type('Resource')],
            'services' => ['type' => Type::listOf(GraphQL::type('JobService'))],
            'materials' => ['type' => Type::listOf(GraphQL::type('JobMaterial'))],
            'order' => ['type' => GraphQL::type('Order')],
            'complains' => ['type' => Type::listOf(GraphQL::type('Complain'))],
            'hasComplain' => ['type' => Type::int()],
            'is_home_delivery' => ['type' => Type::int()],
            'is_on_premise' => ['type' => Type::int()],
            'is_favorite' => ['type' => Type::int()],
            'customerFavorite' => ['type' => GraphQL::type('CustomerFavorite')],
            'can_take_review' => ['type' => Type::boolean()],
            'can_pay' => ['type' => Type::boolean()],
            'can_add_promo' => ['type' => Type::int()],
            'is_car_rental' => ['type' => Type::boolean()],
            'pickup_location_id' => ['type' => Type::int()],
            'destination_location_id' => ['type' => Type::int()],
        ];
    }

    protected function resolveIsFavoriteField($root, $args)
    {
        return $root->customerFavorite ? 1 : 0;
    }

    protected function resolveCompletedAtField($root, $args)
    {
        return $root->delivered_date ? $root->delivered_date->format('M jS, Y') : null;
    }

    protected function resolveIsHomeDeliveryField($root, $args)
    {
        return $root->site == 'customer' ? 1 : 0;
    }

    protected function resolveIsOnPremiseField($root, $args)
    {
        return $root->site == 'partner' ? 1 : 0;
    }

    protected function resolveCompletedAtTimestampField($root, $args)
    {
        return $root->delivered_date ? $root->delivered_date->timestamp : null;
    }

    protected function resolveServicesField($root, $args)
    {
        if (count($root->jobServices) == 0) {
            return array(array(
                'id' => $root->service->id,
                'name' => $root->service_name, 'options' => $root->service_variables,
                'unit' => $root->service->unit,
                'quantity' => (float)$root->service_quantity,
                'unit_price' => (float)$root->service_unit_price),
                'option' => $root->service_option,
                'min_price' => 0
            );
        } else {
            $services = [];
            foreach ($root->jobServices as $jobService) {
                array_push($services, array(
                        'id' => $jobService->service->id,
                        'name' => $jobService->service->name,
                        'options' => $jobService->variables,
                        'option' => $jobService->option,
                        'unit' => $jobService->service->unit,
                        'quantity' => (float)$jobService->quantity,
                        'unit_price' => (float)$jobService->unit_price,
                        'min_price' => (float)$jobService->min_price
                    )
                );
            }
            return $services;
        }
    }

    protected function resolveMaterialsField($root, $args)
    {
        return $root->usedMaterials;
    }

    protected function resolveOrderField($root, $args)
    {
        return $root->partnerOrder;
    }

    protected function resolvePriceField($root, $args)
    {
        $partnerOrder = $root->partnerOrder;
        $partnerOrder->calculate(true);
        return (double)$partnerOrder->totalPrice;
    }

    protected function resolveDueField($root, $args)
    {
        return (double)$root->partnerOrder->calculate(true)->dueWithLogistic;
    }

    protected function resolveComplainsField($root, $args, $fields)
    {
        return $root->complains->where('accessor_id', 1);
    }

    protected function resolveHasComplainField($root, $args, $fields)
    {
        return $root->complains->count() > 0 ? 1 : 0;
    }

    protected function resolvePreferredTimeReadableField($root, $args)
    {
        return $root->readable_preferred_time;
    }

    protected function resolveScheduleDateTimestampField($root, $args)
    {
        return Carbon::parse($root->schedule_date)->timestamp;
    }

    protected function resolvePickupAddressField($root)
    {
        return $root->carRentalJobDetail ? $root->carRentalJobDetail->pick_up_address : null;
    }

    protected function resolveDestinationAddressField($root)
    {
        return $root->carRentalJobDetail ? $root->carRentalJobDetail->destination_address : null;
    }

    protected function resolvePickupAreaField($root)
    {
        if ($root->carRentalJobDetail) {
            if ($root->carRentalJobDetail->pick_up_location_id) {
                return $root->carRentalJobDetail->pickUpLocation->name;
            }
        } else {
            return null;
        }
    }

    protected function resolveDestinationAreaField($root)
    {
        if ($root->carRentalJobDetail) {
            if ($root->carRentalJobDetail->destination_location_id) {
                return $root->carRentalJobDetail->destinationLocation->name;
            }
        } else {
            return null;
        }
    }

    protected function resolveCanTakeReviewField($root, $args)
    {
        return $this->canTakeReview($root);
    }

    protected function resolveCanAddPromoField($root, $args)
    {
        if (!isset($root->totalDiscount)) {
            $root->calculate(true);
        }
        $partner_order = $root->partnerOrder;
        return (double)$root->totalDiscount == 0 && !$partner_order->order->voucher_id && $partner_order->due != 0 && !$partner_order->cancelled_at && !$partner_order->closed_at ? 1 : 0;
    }

    protected function canTakeReview($job)
    {
        $review = $job->review;

        if (!is_null($review) && $review->rating > 0) {
            return false;
        } else if ($job->partnerOrder->closed_at) {
            $closed_date = Carbon::parse($job->partnerOrder->closed_at);
            $now = Carbon::now();
            $difference = $closed_date->diffInDays($now);

            return $difference < constants('CUSTOMER_REVIEW_OPEN_DAY_LIMIT');
        } else {
            return false;
        }
    }

    protected function resolveCanPayField($root, $args)
    {
        $due = $root->partnerOrder->calculate(true)->due;
        $status = $root->status;

        if (in_array($status, ['Declined', 'Cancelled']))
            return false;
        else {
            return $due > 0;
        }
    }

    protected function resolveIsCarRentalField($root, $args)
    {
        return !!$root->isRentCar();
    }

    protected function resolvePickupAddressGeoField($root, $args)
    {
        return $root->carRentalJobDetail ? $root->carRentalJobDetail->pick_up_address_geo : null;
    }

    protected function resolveDestinationAddressGeoField($root, $args)
    {
        return $root->carRentalJobDetail ? $root->carRentalJobDetail->destination_address_geo : null;
    }

    protected function resolvePickupLocationIdField($root, $args)
    {
        if ($root->carRentalJobDetail) {
            return $root->carRentalJobDetail->pickUpLocation ? $root->carRentalJobDetail->pickUpLocation->location_id : null;
        } else {
            return null;
        }
    }

    protected function resolveDestinationLocationIdField($root, $args)
    {
        if ($root->carRentalJobDetail) {
            return $root->carRentalJobDetail->destinationLocation ? $root->carRentalJobDetail->destinationLocation->location_id : null;
        } else {
            return null;
        }
    }

}
