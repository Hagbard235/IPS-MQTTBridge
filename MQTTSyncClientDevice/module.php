<?php

declare(strict_types=1);

class MQTTSyncClientDevice extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyString('GroupTopic', '');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');

        $GroupTopic = $this->ReadPropertyString('GroupTopic');
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*mqttsync/' . $GroupTopic . '/' . $MQTTTopic . '.*');
        //$this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData JSON', $JSONString, 0);
        $Data = json_decode($JSONString);
        $Buffer = json_decode($Data->Buffer);

        if (property_exists($Buffer, 'Topic')) {
            $Variablen = json_decode($Buffer->Payload);
            foreach ($Variablen as $Variable) {
                if ($Variable->ObjectIdent == '') {
                    $ObjectIdent = $Variable->ID;
                } else {
                    $ObjectIdent = $Variable->ObjectIdent;
                }

                if ($Variable->VariableCustomProfile != '') {
                    $VariableProfile = $Variable->VariableCustomProfile;
                } else {
                    $VariableProfile = $Variable->VariableProfile;
                }
                $ID = $this->GetIDForIdent($ObjectIdent);
                if (!$ID) {
                    switch ($Variable->VariableTyp) {
                        case 0:
                            $this->RegisterVariableBoolean($ObjectIdent, $Variable->Name, $VariableProfile);
                            break;
                        case 1:
                            $this->RegisterVariableInteger($ObjectIdent, $Variable->Name, $VariableProfile);
                            break;
                        case 2:
                            $this->RegisterVariableFloat($ObjectIdent, $Variable->Name, $VariableProfile);
                            break;
                        case 3:
                            $this->RegisterVariableString($ObjectIdent, $Variable->Name, $VariableProfile);
                            break;
                        default:
                            IPS_LogMessage('MQTTSync Client', 'invalid variablen profile');
                            break;
                    }
                    if ($Variable->VariableAction != 0 || $Variable->VariableCustomAction != 0) {
                        $this->EnableAction($ObjectIdent);
                    }
                }
                $this->SendDebug('Value for ' . $ObjectIdent . ':', $Variable->Value, 0);
                $this->SetValue($ObjectIdent, $Variable->Value);
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $Payload = [];
        $Payload['ObjectIdent'] = $Ident;
        $Payload['Value'] = $Value;
        $Topic = 'mqttsync/' . $this->ReadPropertyString('GroupTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/set';
        $this->sendMQTTCommand($Topic, json_encode($Payload));
    }

    protected function sendMQTTCommand($topic, $payload, $retain = false)
    {
        $Buffer['Topic'] = $topic;
        $Buffer['Payload'] = $payload;
        $Buffer['Retain'] = $retain;
        $BufferJSON = json_encode($Buffer);
        $this->SendDebug('sendMQTTCommand Buffer', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(['DataID' => '{97475B04-67C3-A74D-C970-E9409B0EFA1D}', 'Action' => 'Publish', 'Buffer' => $BufferJSON]));
    }
}
