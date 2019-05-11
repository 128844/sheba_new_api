<?php namespace Sheba\Transport\Bus\Vendor\BdTickets;

use GuzzleHttp\Exception\GuzzleException;
use Predis\ClientException;
use Psr\Http\Message\ResponseInterface;
use Sheba\Transport\Bus\ClientCalls\WalletClient;
use Sheba\Transport\Bus\Exception\UnCaughtClientException;
use Sheba\Transport\Bus\Order\TransportTicketRequest;
use Sheba\Transport\Bus\Repositories\BusRouteLocationRepository;
use Sheba\Transport\Bus\Response\BdTicketsFailResponse;
use Sheba\Transport\Bus\Response\BdTicketsResponse;
use Sheba\Transport\Bus\Vendor\Vendor;
use Sheba\Transport\Bus\ClientCalls\BdTickets as BdTicketsClientCall;
use Throwable;

class BdTickets extends Vendor
{
    const BOOK_APPLICATION = 'BUS';
    const APPLICATION_CHANNEL = 'REMOTE';
    const ACCOUNT_TYPE = 'AGENT';

    /** @var BdTicketsClientCall $bdTicketClient */
    private $bdTicketClient;
    /** @var TransportTicketRequest $ticketRequest */
    private $ticketRequest;
    /** @var WalletClient $walletClient */
    private $walletClient;
    /** @var BdTicketsResponse $bdTicketResponse */
    private $bdTicketResponse;

    public function __construct(BdTicketsClientCall $bd_ticket_client, BusRouteLocationRepository $bus_route_location_repo,
                                WalletClient $wallet_client, BdTicketsResponse $bd_ticket_response)
    {
        parent::__construct($bus_route_location_repo);
        $this->bdTicketClient = $bd_ticket_client;
        $this->walletClient = $wallet_client;
        $this->bdTicketResponse = $bd_ticket_response;
    }

    /**
     * @param TransportTicketRequest $ticket_request
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     */
    public function bookTicket(TransportTicketRequest $ticket_request)
    {
        $this->ticketRequest = $ticket_request;
        $cart_id = $this->createCart();
        $this->updateCart($cart_id);
        return $this->_bookTicket($cart_id);
    }

    /**
     * CREATE EMPTY CART FOR TICKET PLACE HOLDER
     *
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     */
    private function createCart()
    {
        $data = ['bookApplication' => self::BOOK_APPLICATION, 'applicationChannel' => self::APPLICATION_CHANNEL];
        $response = $this->bdTicketClient->post('carts', $data);
        return $response['data']['id'];
    }

    private function updateCart($cart_id)
    {
        $data = [
            "cartType" => "DEPARTURE",
            "boardingPoint" => (int)$this->ticketRequest->getBoardingPoint(),
            "droppingPoint" => (int)$this->ticketRequest->getDroppingPoint(),
            "coachId" => $this->ticketRequest->getCoachId(),
            "passengerList" => [json_encode(["firstName" => $this->ticketRequest->getReserverName(), "lastName" => "", "phoneNumber" => $this->ticketRequest->getReserverMobile(), "email" => $this->ticketRequest->getReserverEmail(), "gender" => strtoupper($this->ticketRequest->getReserverGender()[0])])],
            "seatIdList" => $this->ticketRequest->getSeatIdList(),
            "applicationChannel" => self::APPLICATION_CHANNEL
        ];

        return $this->bdTicketClient->put("carts/$cart_id", $data);
    }

    private function _bookTicket($cart_id)
    {
        $data = ['cartId' => $cart_id, 'applicationChannel' => self::APPLICATION_CHANNEL];
        return $this->bdTicketClient->post('carts/book', $data);
    }

    public function confirmTicket($ticket_id)
    {
        $data = ['ticketId' => $ticket_id, 'accountType' => self::ACCOUNT_TYPE, 'applicationChannel' => self::APPLICATION_CHANNEL];
        return $this->bdTicketClient->post('tickets/confirm', $data);
    }

    /**
     * @param $transport_ticket_order
     * @param $amount
     * @param $transaction_id
     * @throws GuzzleException
     * @throws Throwable
     */
    public function deduceAmount($transport_ticket_order, $amount, $transaction_id)
    {
        $wallet_id = $transport_ticket_order->vendor->wallet_id;
        $log = "$amount Tk. has been debited for transport ticket.";
        $transaction_details = json_encode(['transaction_id' => $transaction_id, 'log' => $log]);

        $this->walletClient->saveTransaction($wallet_id, $amount, 'debit', $transaction_details, 'purchase');
    }
}