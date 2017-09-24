<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Review;
use App\Repositories\ReviewRepository;
use Illuminate\Http\Request;
use Gate;
use Validator;

class ReviewController extends Controller
{
    private $reviewRepository;

    public function __construct()
    {
        $this->reviewRepository = new ReviewRepository();
    }

    public function modifyReview($customer, Request $request)
    {
        if ($msg = $this->_validateReview($request)) {
            return api_response($request, null, 500, ['message' => $msg]);
        }
        if ($request->has('job')) {
            $job_id = $request->job;
        } else {
            $job_id = $request->job_id;
        }
        $review = Review::where([['job_id', $job_id], ['customer_id', $customer]])->first();
        if ($review != null) {
            $review = $this->reviewRepository->update($review, $request);
            return api_response($request, $review, 200);
        } else {
            $job = $this->reviewRepository->customerCanGiveReview($customer, $job_id);
            if ($job != false) {
                $review = $this->reviewRepository->save($job, $request);
                return api_response($request, $review, 200);
            } else
                return api_response($request, null, 403);
        }
    }

    public function giveRatingFromEmail(Request $request)
    {
        $this->validate($request, [
            'rating' => 'required|numeric|between:1,5',
            'j' => 'required|numeric',
            'c' => 'required|numeric',
            'token' => 'required|string',
        ]);
        if ($request->input('rating') > 5 || $request->input('rating') < 1) {
            return response()->json(['msg' => 'I see what you did there ;)', 'code' => 409]);
        }
        $customer = Customer::where('remember_token', $request->input('token'))->first();

        //customer is valid
        if ($customer && $customer->id == $request->input('c')) {
            $job = Job::find($request->input('j'));
            //customer can give review to this job
            if ($this->reviewRepository->customerCanGiveReview($customer->id, $request->input('j'))) {
                //if review isn't given yet
                if ($job->review == null) {
                    $review = new Review();
                    $review->rating = $request->input('rating');
                    $review->job_id = $job->id;
                    $review->resource_id = $job->resource_id;
                    $review->partner_id = $job->partner_order->partner_id;
                    $review->service_id = $job->service_id;
                    $review->customer_id = $customer->id;
                    if ($review->save()) {
                        return response()->json(['msg' => 'successful', 'code' => 200]);
                    } else {
                        return response()->json(['msg' => 'error', 'code' => 500]);
                    }
                } //update the review
                else {
                    $review = $job->review;
                    $review->rating = $request->input('rating');
                    if ($review->update()) {
                        return response()->json(['msg' => 'successful', 'code' => 200]);
                    }
                }
            }
        }
        return response()->json(['msg' => 'unauthorized', 'code' => 409]);
    }

    private function _validateReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'review' => 'required_with:review_title|string|min:40',
            'review_title' => 'required_with:review|string|min:5',
            'rating' => 'required|numeric|between:1,5',
            'job' => 'required_without:job_id|numeric',
            'job_id' => 'required_without:job|numeric'
        ]);
        return $validator->fails() ? $validator->errors()->all()[0] : false;
    }

}
