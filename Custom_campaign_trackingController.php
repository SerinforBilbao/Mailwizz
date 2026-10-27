<?php declare(strict_types=1);
if (!defined('MW_PATH')) {
    exit('No direct script access allowed');
}

/**
 * InternalApiController
 *
 * Handles internal API operations requiring specific privileges (e.g., admin).
 * This controller MUST NOT be added to 'unprotectedControllers'.
 */
class Custom_campaign_trackingController extends Controller
{
    /**
     * Default access rules.
     * Rely on the main API authentication (X-Api-Key / Signature)
     * since this controller won't be in 'unprotectedControllers'.
     * @return array
     */
    public function accessRules()
    {
        // Allow access if the core API authentication passes.
        return [
            ['allow'],
        ];
    }

    /**
     * @param string $campaign_uid
     *
     * @return Campaign|null
     */
    private function loadCampaignByUid(string $campaign_uid): ?Campaign
    {
        $criteria = new CDbCriteria();
        $criteria->compare('campaign_uid', $campaign_uid);
        $criteria->addNotInCondition('status', [Campaign::STATUS_PENDING_DELETE]);

        /** @var Campaign|null $model */
        $model = Campaign::model()->find($criteria);

        return $model;
    }

    
    /**
     * @param string $campaign_uid
     *
     * @return array
     */
    private function getOpeningDevices(Campaign $campaign): array
    {
        $data = [
            'os'      => [],
            'device'  => [],
            'browser' => [],
        ];

        $limit = 5000;
        $offset = 0;

        do {
            $rows = db()->createCommand()
                ->select('user_agent, COUNT(*) AS counter')
                ->from(CampaignTrackOpen::model()->tableName())
                ->where(
                    'campaign_id = :campaign_id',
                    [':campaign_id' => (int) $campaign->campaign_id]
                )
                ->group('user_agent')
                ->order('user_agent ASC')
                ->limit($limit)
                ->offset($offset)
                ->queryAll();

            foreach ($rows as $row) {
                $userAgent = (string) $row['user_agent'];

                if (strlen($userAgent) < 10) {
                    continue;
                }

                $result = new WhichBrowser\Parser(
                    $userAgent,
                    ['detectBots' => false]
                );

                if (
                    empty($result->os->name) ||
                    empty($result->device->type) ||
                    empty($result->browser->name)
                ) {
                    continue;
                }

                $count = (int) $row['counter'];

                // Operative System
                $os = $result->os->name;

                if (!isset($data['os'][$os])) {
                    $data['os'][$os] = [
                        'name'  => ucwords($os),
                        'count' => 0,
                    ];
                }

                $data['os'][$os]['count'] += $count;

                // Device
                $device = $result->device->type;

                if (!isset($data['device'][$device])) {
                    $data['device'][$device] = [
                        'name'  => ucwords($device),
                        'count' => 0,
                    ];
                }

                $data['device'][$device]['count'] += $count;

                // Browser
                $browser = $result->browser->name;

                if (!empty($result->browser->version->value)) {
                    $version = explode(
                        '.',
                        (string) $result->browser->version->value
                    );

                    $version = implode('.', array_slice($version, 0, 2));

                    $browser .= sprintf('(v.%s)', $version);
                }

                if (!isset($data['browser'][$browser])) {
                    $data['browser'][$browser] = [
                        'name'  => ucwords($browser),
                        'count' => 0,
                    ];
                }

                $data['browser'][$browser]['count'] += $count;
            }

            $offset += $limit;

        } while (count($rows) === $limit);

        // order by cant opens
        foreach ($data as $key => $items) {
            usort($items, function ($a, $b) {
                return $b['count'] <=> $a['count'];
            });

            $data[$key] = array_slice($items, 0, 50);
        }

        return $data;
    }

    /**
     * @param string $campaign_uid
     *
     * @return array
     */
    private function getHourlyPerformance(Campaign $campaign): array
    {
        $params = [
            ':cid' => (int) $campaign->campaign_id,
        ];

        $subscriber = new ListSubscriber();
        $dateFormatter = $subscriber->dateTimeFormatter;

        $result = [
            'opens'  => [],
            'clicks' => [],
        ];

        // Unics opens by hour
        $queryOpens = "
            SELECT
                COUNT(DISTINCT subscriber_id) AS counter,
                DATE_FORMAT(date_added, '%Y-%m-%d %H:00:00') AS hr
            FROM {{campaign_track_open}}
            WHERE
                date_added >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND campaign_id = :cid
            GROUP BY hr
            ORDER BY hr ASC
            LIMIT 24
        ";

        $rows = db()->createCommand($queryOpens)->queryAll(true, $params);

        foreach ($rows as $row) {
            $result['opens'][] = [
                'timestamp' => (int) (
                    strtotime($dateFormatter->convertDateTime($row['hr'])) * 1000
                ),
                'count' => (int) $row['counter'],
            ];
        }

        // Unics clics by hour
        $queryClicks = "
            SELECT
                COUNT(DISTINCT t.subscriber_id) AS counter,
                DATE_FORMAT(t.date_added, '%Y-%m-%d %H:00:00') AS hr
            FROM {{campaign_track_url}} t
            INNER JOIN {{campaign_url}} cu
                ON cu.url_id = t.url_id
            WHERE
                t.date_added >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND cu.campaign_id = :cid
            GROUP BY hr
            ORDER BY hr ASC
            LIMIT 24
        ";

        $rows = db()->createCommand($queryClicks)->queryAll(true, $params);

        foreach ($rows as $row) {
            $result['clicks'][] = [
                'timestamp' => (int) (
                    strtotime($dateFormatter->convertDateTime($row['hr'])) * 1000
                ),
                'count' => (int) $row['counter'],
            ];
        }

        return $result;
    }

    /**
     * Get custom stats
     * Intended to be called by a trusted backend using admin credentials.
     *
     * @param string $campaign_uid
     * @return void // Outputs JSON
     * @throws CHttpException
     */
    public function actionGetcustomstats(string $campaign_uid)
    {
        /** @var Campaign|null $campaign */
        $campaign = $this->loadCampaignByUid($campaign_uid);
        if (empty($campaign)) {
            $this->renderJson([
                'status' => 'error',
                'error'  => 'Campaign not found',
            ], 404);
            return;
        }

        $criteria = new CDbCriteria();
        $criteria->compare('t.campaign_id', $campaign->campaign_id);
        $criteria->with = ['subscriber'];
        $criteria->order = 't.date_added DESC';
        $criteria->limit = 10;

        $opens = CampaignTrackOpen::model()->findAll($criteria);

        $data_opens=[];

        foreach ($opens as $open) {
            $data_opens[] = [
                'email'      => $open->subscriber->email,
                'ip_address' => $open->ip_address,
                'user_agent' => $open->user_agent,
                'date_added' => $open->date_added,
            ];
        }

        //Subscribers with most opens
        $criteria = new CDbCriteria();

        $criteria->select = [
            't.subscriber_id',
            'COUNT(t.id) AS counter',
        ];

        $criteria->compare('t.campaign_id', $campaign->campaign_id);
        $criteria->with = ['subscriber'];
        $criteria->group = 't.subscriber_id';
        $criteria->order = 'counter DESC';
        $criteria->limit = 10;

        $topOpens = CampaignTrackOpen::model()->findAll($criteria);

        $dataTopOpens = [];

        foreach ($topOpens as $open) {
            $dataTopOpens[] = [
                'email' => $open->subscriber->email,
                'opens' => (int) $open->counter,
            ];
        }

        //data devices
        $dataDevices = $this->getOpeningDevices($campaign);

        //24 Hours Performance
        $hourlyPerformance = $this->getHourlyPerformance($campaign);

        $this->renderJson([
            "last_opens"=>$data_opens,
            "top_opens"  => $dataTopOpens,
            "hourly_performance" => $hourlyPerformance,
            "opening_devices" => $dataDevices
        ], 200);
    }
}