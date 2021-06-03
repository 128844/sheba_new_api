<?php

namespace Tests\Feature\Accounting;

use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\FeatureTestCase;

class DueDepositApiTest extends FeatureTestCase
{
    private $token;

    public function test_entry_type_due()
    {
        $response = $this->post(config('sheba.api_url').'/v2/accounting/due-tracker', $this->getFormData('due'), [
            'Authorization' => $this->token ?? $this->generateToken()
        ]);

        $id = json_decode($response->response->getContent())->data->id;
        $response->assertResponseOk();
        $response->seeJson([
            "code" => 200,
            "message" => "Successful",
            "data" => [
                "id" => $id,
                "amount" => 4440
            ]
        ]);
    }

    public function test_entry_type_deposit()
    {
        $response = $this->post(config('sheba.api_url').'/v2/accounting/due-tracker', $this->getFormData('deposit'), [
            'Authorization' => $this->token ?? $this->generateToken()
        ]);

        $id = json_decode($response->response->getContent())->data->id;
        $response->assertResponseOk();
        $response->seeJson([
            "code" => 200,
            "message" => "Successful",
            "data" => [
                "id" => $id,
                "amount" => 4440
            ]
        ]);
    }

    private function generateToken(){
        $client = new Client();
        $response = $client->get('https://accounts.dev-sheba.xyz/api/v3/token/generate?type=resource&token=TemAMQbHo8NES7nlEielwNw1EGTOKcQTC6jImGLNP4MLbFCjtvbeziGwlMd7&type_id=45320');
        $this->token = 'Bearer ' . \GuzzleHttp\json_decode($response->getBody())->token;
        return $this->token;
    }

    private function getFormData(string $entryType) : array {
        return [
            'amount' => 4440,
            'account_key' => 'cash',
            'date' => '2020-12-25 15:49:59',
            'note' => 'note',
            'attachments' => '',
            'entry_type' => $entryType,
            'customer_id' => 568,
        ];
    }
}
