<?php namespace App\Http\Controllers\EKYC;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Sheba\EKYC\EkycClient;
use Sheba\EKYC\Exceptions\EkycServerError;


class NidOcrController extends Controller
{
    private $client;
    private $api;

    public function __construct(EkycClient $client)
    {
        $this->client = $client;
        $this->api = 'nid-ocr-data';
    }

    public function storeNidOcrData(Request $request)
    {
        try {
            $data = $this->toData($request);
            $userId = isset($request->user_id) ? $request->user_id : 1;
            return $this->client->setUserId($userId)
                ->post($this->api, $data);
        } catch (EkycServerError $e) {
            throw new EkycServerError($e->getMessage(), $e->getCode());
        }
    }

    private function toData($request)
    {
        $data['client_id'] = $request->header('client-id');
        $data['client_secret'] = $request->header('client-secret');
        $data['id_front'] = $request->id_front;
        $data['id_back'] = $request->id_back;
        return $data;
    }
}
