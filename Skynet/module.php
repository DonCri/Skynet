<?php

// Klassendefinition
class Skynet extends IPSModule {
    // Überschreibt die interne IPS_Create($id) Funktion

    public function Create() {
        parent::Create();

        // Profile
        

        // variable
        $this->RegisterVariableBoolean('SKYNET_STATE', $this->Translate('State'), '', 0);
        $this->EnableAction('SKYNET_STATE');
        $this->RegisterVariableString('MESSAGE', $this->Translate('Message'), '', 1);
        $this->RegisterVariableInteger('RECOMMENDED_VALUE', $this->Translate('Recommended Value'), '', 2);
        IPS_SetHidden($this->GetIDForIdent('RECOMMENDED_VALUE'), true);
        $this->RegisterVariableString('UNIT', $this->Translate('Unit'), '', 3);
        IPS_SetHidden($this->GetIDForIdent('UNIT'), true);
        $this->RegisterVariableString('ROOM', $this->Translate('Room'), '', 4);
        IPS_SetHidden($this->GetIDForIdent('ROOM'), true);
    

        // Property
        $this->RegisterPropertyString('mistral_api_key', '');
        $this->RegisterPropertyString('mistral_model', 'mistral-large-latest');
        $this->RegisterPropertyString('message_language', 'german');
        $this->RegisterPropertyString('devices', '[]');
        $this->RegisterPropertyString('url', 'https://api.mistral.ai/v1/chat/completions');
        $this->RegisterPropertyInteger('triggerdifference', 15);

        // Atributes

        // Register a Timer with an Intervall of 0 milliseconds (initial deaktiviert)

        // Log Message variable
        $archiveGUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
        $archiveIdList = IPS_GetInstanceListByModuleID($archiveGUID);
        $archiveID = $archiveIdList[0];
        AC_SetLoggingStatus($archiveID, $this->GetIDForIdent('MESSAGE'), true);
        AC_SetLoggingStatus($archiveID, $this->GetIDForIdent('UNIT'), true);
        AC_SetLoggingStatus($archiveID, $this->GetIDForIdent('UNIT'), true);

    }

    public function SendRequest($roomName, $value, $unit) {
        $messageLanguage = $this->ReadPropertyString('message_language');
        $mistralModel = $this->ReadPropertyString('mistral_model');
        $url = $this->ReadPropertyString('url');
        $apiToken = $this->ReadPropertyString('mistral_api_key');

        $header = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiToken,
        ];

        $data = [
            "model" => $mistralModel,
            "messages" => [
                [
                    "role" => "system",
                    "content" => "You are an home automation assistant who gives advices for a better living and security experience. 
                            The advice is based on values from the home automation system. The advice must be the best choice between comfort and energy safety. 

                            Replay in the following JSON format:
                            {
                                \"advice\" : {
                                    \"room\": ,
                                    \"valueFrom\": Recommended value from (if there is a value range),
                                    \"valueTo\": Recommended value to (if there is a value range),
                                    \"unit\": Value unit,
                                    \"text\": Best practic advice in natural language, short version and in $messageLanguage
                                    \"recommendedValue\": The averege value between valueFrom and valueTo. Only value, no other text.
                                    }
                            }"
                ],
                [
                    "role" => "user",
                    "content" => "Room: $roomName, $value, $unit"
                ]
            ],
        ];

        // init curl
        $ch = curl_init($url);
        // set curl option
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }

        // Get HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close the cURL session
        curl_close($ch);

        $responseJSON = json_decode($response, true);

        if($httpCode == 422) {
            IPS_LogMessage("Skynet", "Error: " .$responseJSON['detail'][0]['msg']);
            SetValue(19061, "Fehlerhafte Antwort");
        }
        if($httpCode == 200) {
            // Log Message
            IPS_LogMessage("Skynet", "successful: " . $response);
            // Entferne die Backticks (```json und ```)
            $content = trim($responseJSON['choices'][0]['message']['content'], '`');
            $jsonSTRING = str_replace(['```', 'json'], '', $content);
            $contentJSON = json_decode($jsonSTRING, true);
            
            SetValue($this->GetIDForIdent('MESSAGE'), $contentJSON['advice']['text']);
            SetValue($this->GetIDForIdent('RECOMMENDED_VALUE'), $contentJSON['advice']['recommendedValue']);
            SetValue($this->GetIDForIdent('UNIT'), $contentJSON['advice']['unit']);;
            SetValue($this->GetIDForIdent('ROOM'), $roomName);
        }
    }

    public function RequestAction($Ident, $Value) {
        $IdentID = $this->GetIDForIdent($Ident);
        if($IdentID) {
            SetValue($IdentID, $Value);
        }
    }

    public function ApplyChanges() {
        parent::ApplyChanges();

        IPS_LogMessage('Skynet', "Selected model: " . $this->ReadPropertyString('mistral_model'));

        // Register variable as triggers
        $deviceListString = $this->ReadPropertyString('devices');
        $deviceListJSON = json_decode($deviceListString, true);
        if(empty($deviceListJSON)) {
            IPS_LogMessage('Skynet', 'Device List is empty');
        } else {
            foreach($deviceListJSON as $list) {
            $id = $list['SensorID'];
            IPS_LogMessage('Skynet', 'registered device ID ' . $id);
            $this->RegisterMessage($id, 10603);
        }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        $deviceListString = $this->ReadPropertyString('devices');
        $deviceListJSON = json_decode($deviceListString, true);	

        $skynetStat = $this->GetValue('SKYNET_STATE');

        if($skynetStat) {
            foreach($deviceListJSON as $content) {
            if($SenderID == $content['SensorID']) {
                if($Data[1] == 1) {
                    $oldValue = $Data[2];
                    $newValue = $Data[0];
                    $valueDifferent = abs($newValue - $oldValue);
                    $valueDifferentInPercent = ($valueDifferent / $oldValue) * 100;
                    if($valueDifferentInPercent >= $this->ReadPropertyInteger('triggerdifference')) {
                        IPS_LogMessage('Skynet', 'different: ' . $valueDifferentInPercent);
                        $this->SendRequest($content['Room'], GetValue($SenderID), $content['SensorType']);
                        }
                    }
                }
            }
        } 

        IPS_LogMessage("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true));
    }

}