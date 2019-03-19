<?php

namespace App\Http\Controllers;

use App\Models\MovieTicketOrder;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

use Sheba\MovieTicket\MovieTicket;
use Sheba\MovieTicket\MovieTicketManager;
use Sheba\MovieTicket\MovieTicketRequest;
use Sheba\MovieTicket\Vendor\VendorFactory;

class MovieTicketController extends Controller
{
    /**
     * @param MovieTicketManager $movieTicket
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableTickets(MovieTicketManager $movieTicket, Request $request)
    {
        try {
            $movies = $movieTicket->initVendor()->getAvailableTickets();
            return api_response($request, $movies, 200, ['movies' => $this->convertToJson($movies)]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }


    /**
     * @param MovieTicketManager $movieTicket
     * @return \Illuminate\Http\JsonResponse
     * @throws GuzzleException
     */
    public function getAvailableTheatres(MovieTicketManager $movieTicket, Request $request)
    {
        try {
            $this->validate($request, [
                'movie_id' => 'required',
                'request_date' => 'required'
            ]);
            $theatres = $movieTicket->initVendor()->getAvailableTheatres($request->movie_id,$request->request_date);
            return api_response($request, $theatres, 200, ['theatres' => $this->convertToJson($theatres)]);
        }   catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param MovieTicketManager $movieTicket
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getTheatreSeatStatus(MovieTicketManager $movieTicket, Request $request)
    {
        try {
            $this->validate($request, [
                'dtmid' => 'required',
                'slot' => 'required'
            ]);
            $status = $movieTicket->initVendor()->getTheatreSeatStatus($request->dtmid,$request->slot);
            return api_response($request, $status, 200, ['status' => $this->convertToJson($status )]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }


    public function bookTickets(MovieTicketManager $movieTicket, Request $request)
    {
        try {
            $this->validate($request, [
                'dtmsid' => 'required',
                'seat_class' => 'required',
                'seat' => 'required',
                'customer_name' => 'required',
                'customer_email' => 'required',
                'customer_mobile' => 'required|mobile:bd',
            ]);

            $bookingResponse = $movieTicket->initVendor()->bookSeats([
                'DTMSID' => $request->dtmsid,
                'SeatClass'=> $request->seat_class,
                'Seat'=> $request->seat,
                'CusName'=> $request->customer_name,
                'CusEmail'=> $request->customer_email,
                'CusMobile'=> $request->customer_mobile
            ]);
            return api_response($request, $bookingResponse, 200, ['status' => $bookingResponse]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        } catch (GuzzleException $e) {
            return api_response($request, null, 500);
        }
    }

    public function updateTicketStatus(MovieTicketManager $movieTicketManager, MovieTicket $movieTicket, Request $request, MovieTicketRequest $movieTicketRequest,VendorFactory $vendor)
    {
        try{
            $this->validate($request, [
                'trx_id' => 'required',
                'dtmsid' => 'required',
                'lid' => 'required',
                'confirm_status' => 'required',
                'customer_name' => 'required',
                'customer_email' => 'required',
                'customer_mobile' => 'required|mobile:bd',
                'cost' => 'required',
                'image_url' => 'required'
            ]);

            $agent = $this->getAgent($request);
            if ($agent->wallet < (double) $request->cost) return api_response($request, null, 403, ['message' => "You don't have sufficient balance to buy this ticket."]);
            $movieTicketRequest->setName($request->customer_name)->setEmail($request->customer_email)->setAmount($request->cost)
                    ->setMobile($request->customer_mobile)->setTrxId($request->trx_id)->setDtmsId($request->dtmsid)
                    ->setTicketId($request->lid)->setConfirmStatus($request->confirm_status)->setImageUrl($request->image_url);
            $vendor = $vendor->getById(1);
            $response = $movieTicket->setAgent($agent)->setVendor($vendor)->buyTicket($movieTicketRequest);
            if($response->hasSuccess()) {
                $details = $response->getSuccess()->transactionDetails;
                $movieOrder =  $movieTicket->getMovieTicketOrder();
                $details->order_id = $movieOrder->id;
                $details->agent_commission = $movieOrder->agent_commission;
                $details->sheba_commission = $movieOrder->sheba_commission;
                $details->cost = $details->cost + $details->sheba_commission;
                return api_response($request, $response, 200, ['status' => $details]);
            }
            else
            {
                $error = $response->getError();
                return api_response($request, $response, 200, ['status' => [
                    'message' => $error->errorMessage,
                    'status' => $error->status
                ]]);
            }
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            dd($e);
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        } catch (GuzzleException $e) {
            return api_response($request, null, 500);
        }
    }

    public function history(Request $request)
    {
        try {
            $agent = $this->getAgent($request);
            $orders =  MovieTicketOrder::where('agent_type',get_class($agent))->where('agent_id',$agent->id)->orderBy('created_at','desc')->get();
            $histories = array();
            foreach ($orders as $order) {
                $reservation_details = json_decode($order->reservation_details);
                $history = array(
                    'id' => $order->id,
                    'movie_title' => $reservation_details->MovieName,
                    'show_date' => $reservation_details->ShowDate,
                    'show_time' => $reservation_details->ShowTime,
                    'quantity' => $reservation_details->quantity,
                    'reserver_mobile'=> $order->reserver_mobile,
                    'image_url' =>  isset($reservation_details->image_url) ? $reservation_details->image_url : null
                );
                array_push($histories, $history);
            }
            return api_response($request, $orders, 200, ['history' => $histories]);
        }  catch (\Throwable $e) {
            dd($e);
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function historyDetails($affiliate, $order, Request $request)
    {
        try {
            $order = MovieTicketOrder::find((int) $order);
            removeRelationsAndFields($order);
            $order->reservation_details = json_decode($order->reservation_details);
            $order->reservation_details->cost = $order->amount;
            return api_response($request, $order, 200, ['details' => $order]);
        }  catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    private function getAgent(Request $request)
    {
        if ($request->affiliate) return $request->affiliate;
        elseif ($request->customer) return $request->customer;
        elseif ($request->partner) return $request->partner;
        throw new \Exception('Invalid Agent');
    }

    private function  convertToJson($response) {
        return json_decode(json_encode($response));
    }
}
