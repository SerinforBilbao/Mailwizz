<?php
/*
Versión MailWizz: 2.6.4

Fecha: 2026-01-20

Autor: Marco Marin
*/

// Hook se encuentra en la línea 984 del archivo CampaignHelper.php
Yii::app()->hooks->addFilter('campaigns_get_common_tags_search_replace', function($searchReplace, $campaign, $subscriber, $server) {

    //anonymous function format url for tag
    $formatUrl = function ($baseUrl,$params,$test = false){
        if(!$test){
            // Build the query string
            $queryString = http_build_query($params);
            $finalUrl = $baseUrl . '?' . $queryString;
            
            // Según la línea 380 y 31 del archivo CampaignHelper.php, 
            // MailWizz IGNORA el tracking para este enlace.
            $finalUrl .= '&disable-tracking=true';
        }
        else{
            $finalUrl = $baseUrl . '?test=true';
        }

        return $finalUrl;
    };
    
    $params = [];
    $test = true;
    if (!empty($subscriber) && is_object($subscriber)) {
        $test = false;
        // get IDs 
        $listUid = (isset($campaign->list) && isset($campaign->list->list_uid)) ? $campaign->list->list_uid : '';
        $campUid = (isset($campaign->campaign_uid)) ? $campaign->campaign_uid : '';
        
        $params = [
            'subscriber_uid' => $subscriber->subscriber_uid,
            'campaign_uid' => $campUid,
            'list_uid' => $listUid
        ];
    } 

    //--------------First tag -------------
    $tag = '[MAILBYS_UNSUBSCRIBE_URL]';
    $baseUrl = 'https://mailbys.com/unsubscribe';
    $url_tagSub = $formatUrl($baseUrl,$params,$test);

    // Add tag to MailWizz replacements array
    // MailWizz will take care of searching for [LABEL] and changing it to the URL
    $searchReplace[$tag] = $url_tagSub;


    
    //--------------Second tag -------------
    
    //--------------Third tag -------------

    return $searchReplace;

}, 10, 4); // Priority 10, 4 arguments

