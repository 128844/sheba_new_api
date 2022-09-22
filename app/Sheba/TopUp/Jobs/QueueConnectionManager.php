<?php namespace Sheba\TopUp\Jobs;

use Illuminate\Support\Facades\Redis;
use Sheba\TopUp\TopUpAgent;

class QueueConnectionManager
{
    public static function createConnections(): array
    {
        $connection_names = self::getDistribution();

        $agent_connections = [
            "affiliate" => [
                "default" => "topup_affiliate_default"
            ],
            "partner" => [
                "default" => "topup_partner_default"
            ],
            "business" => [
                "default" => "topup_business_default"
            ],
            "default" => "topup_default"
        ];

        $queue_driver = env('TOPUP_QUEUE_DRIVER');

        $connections = [];
        foreach ($connection_names as $connection_name => $agent_ids) {
            $connections[$connection_name] = [
                'driver' => $queue_driver,
                'connection' => 'default',
                'queue' => $connection_name,  // otherwise, it'll go to 'queues:default' along with many other jobs.
                'expire' => 60
            ];

            if (ends_with($connection_name, "_default")) continue;

            $agent_type = explode("_", $connection_name)[1];

            if (str_contains($connection_name, "_chunk_")) {
                $value = array_merge($agent_ids, ["connection_name" => $connection_name]);
                array_push_on_array($agent_connections[$agent_type], "chunk", $value);
                continue;
            }

            foreach ($agent_ids as $id) {
                $agent_connections[$agent_type][$id] = $connection_name;
            }
        }

        return [
            'connections' => $connections,
            'agent_connections' => $agent_connections
        ];
    }

    private static function getDistribution(): array
    {
        /**
         * New connection name needs to be listened from supervisor.
         */
        return [
            /**
             * Affiliates
             */
            "topup_affiliate_1" => [52585, 113908, 99291, 56464, 86463, 39662, 95829, 36443],
            "topup_affiliate_2" => [52807, 130546, 126724, 105278, 35922, 127249, 105929, 105998],
            "topup_affiliate_3" => [56317, 125090, 39755, 95948, 52376, 104288, 56830, 22355, 128162, 120697],
            "topup_affiliate_4" => [108618, 123995, 123422, 35751, 83479, 102926, 119102, 97955, 2635, 112387, 125756, 123593],

            "topup_affiliate_chunk_1" => ["from" => 1, "to" => 40000],
            "topup_affiliate_chunk_2" => ["from" => 40001, "to" => 80000],
            "topup_affiliate_chunk_3" => ["from" => 80001, "to" => 120000],
            "topup_affiliate_chunk_4" => ["from" => 120001, "to" => 160000],
            "topup_affiliate_chunk_5" => ["from" => 160001, "to" => 200000],
            "topup_affiliate_chunk_6" => ["from" => 200001, "to" => 240000],

            "topup_affiliate_default" => null,

            /**
             * Partners
             */
            "topup_partner_1" => [470194, 123497, 370604, 1070207],
            "topup_partner_2" => [291149, 521301, 89642, 451283],

            "topup_partner_chunk_1"  => ["from" => 1, "to" => 200000],
            "topup_partner_chunk_2"  => ["from" => 200001, "to" => 400000],
            "topup_partner_chunk_3"  => ["from" => 400001, "to" => 600000],
            "topup_partner_chunk_4"  => ["from" => 600001, "to" => 800000],
            "topup_partner_chunk_5"  => ["from" => 800001, "to" => 1000000],
            "topup_partner_chunk_6"  => ["from" => 1000001, "to" => 1100000],
            "topup_partner_chunk_7"  => ["from" => 1100001, "to" => 1200000],
            "topup_partner_chunk_8"  => ["from" => 1200001, "to" => 1225000],
            "topup_partner_chunk_9"  => ["from" => 1225001, "to" => 1250000],
            "topup_partner_chunk_10" => ["from" => 1250001, "to" => 1300000],
            "topup_partner_chunk_11" => ["from" => 1300001, "to" => 1330025],
            "topup_partner_chunk_12" => ["from" => 1330026, "to" => 1360050],
            "topup_partner_chunk_13" => ["from" => 1360051, "to" => 1400000],
            "topup_partner_chunk_14" => ["from" => 1400001, "to" => 1450000],
            "topup_partner_chunk_15" => ["from" => 1450001, "to" => 2400000],

            "topup_partner_default" => null,

            /**
             * Businesses
             */
            "topup_business_1" => [113],
            "topup_business_2" => [1605, 91],
            "topup_business_default" => null,

            /**
             * Others
             */
            "topup_default" => null
        ];
    }

    /**
     * @param TopUpAgent $agent
     * @return string
     */
    public static function getConnectionName(TopUpAgent $agent): string
    {
        $connections = config('topup_queues.agent_connections');
        $agent_type = strtolower(class_basename($agent));
        if (!array_key_exists($agent_type, $connections)) return $connections['default'];

        $agent_connections = $connections[$agent_type];
        if (array_key_exists($agent->id, $agent_connections)) return $agent_connections[$agent->id];

        if (array_key_exists("chunk", $agent_connections)) {
            $chunks = $agent_connections["chunk"];
            foreach ($chunks as $chunk) {
                if ($agent->id >= $chunk['from'] && $agent->id <= $chunk['to']) {
                    return $chunk['connection_name'];
                }
            }
        }

        return $agent_connections['default'];
    }
}