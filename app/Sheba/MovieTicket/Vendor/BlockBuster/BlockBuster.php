<?php namespace Sheba\MovieTicket\Vendor;


use Sheba\MovieTicket\Actions;
use Sheba\MovieTicket\TransactionGenerator;
use Sheba\MovieTicket\Vendor\BlockBuster\KeyEncryptor;

class BlockBuster extends Vendor
{
    // User Credentials
    private $userName;
    private $password;
    private $key;

    // API Urls
    private $apiUrl;
    private $imageServerUrl;
    private $secretKey;
    private $connectionMode;

    /**
     * BlockBuster constructor.
     * @param $connection_mode
     */
    public function __construct($connection_mode)
    {
        $this->imageServerUrl = config('blockbuster.image_server_url');
        $this->connectionMode = $connection_mode;
    }

    private function getSercretKey()
    {
        $cur_random_value = (new TransactionGenerator())->generate();$string = "password=$this->password&trxid=$cur_random_value&format=xml";
        $BBC_Codero_Key_Generate = (new KeyEncryptor())->encrypt_cbc($string,$this->key);
        $BBC_Request_KEY_VALUE =urlencode($BBC_Codero_Key_Generate);
        return $BBC_Request_KEY_VALUE;
    }

    /**
     * @throws \Exception
     */
    public function init()
    {
        if($this->connectionMode === 'dev') {
            // Connect to dev server with test credentials
            $this->userName = config('blockbuster.username_dev');
            $this->password = config('blockbuster.password_dev');
            $this->key = config('blockbuster.key_dev');
            $this->apiUrl = config('blockbuster.test_api_url');

        } else if($this->connectionMode === 'production'){
            // Connect to live server with prod credentials
            $this->userName = config('blockbuster.username_live');
            $this->password = config('blockbuster.password_live');
            $this->key = config('blockbuster.key_live');
            $this->apiUrl = config('blockbuster.live_api_url');

        } else {
            throw new \Exception('Invalid connection mode');
        }
        $this->secretKey = $this->getSercretKey();
    }

    /**
     * @param $action
     * @return string
     * @throws \Exception
     */
    public function generateURIForAction($action)
    {
        switch ($action) {
            case Actions::GET_MOVIE_LIST:
                return $this->apiUrl.'/MovieList.php?username='.$this->userName.'&request_id='.$this->secretKey;
                break;
            default:
                throw new \Exception('Invalid Action');
                break;
        }
    }
}