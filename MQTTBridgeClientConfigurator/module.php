<?php

declare(strict_types=1);

class MQTTBridgeClientConfigurator extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
        $this->RegisterPropertyString('GroupTopic', 'symcon');
        $this->RegisterAttributeString('Devices', '[]');
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Devices = $this->ReadAttributeString('Devices');

        $Values = [];
        $Devices = json_decode($Devices);

        foreach ($Devices as $Device) {
            $instanceID = $this->getMQTTBridgeClientDeviceInstance($Device->MQTTTopic);

            $tmpDevice = [];
            $tmpDevice = [
                'ObjectID'      => $Device->ObjectID,
                'ObjectName'    => $Device->ObjectName,
                'MQTTTopic'     => $Device->MQTTTopic,
                'ObjectType'    => $Device->ObjectType,
                'name'          => $Device->ObjectName . ' (' . $Device->MQTTTopic . ')',
                'instanceID'    => $instanceID

            ];
            $tmpDevice['create'] = [
                'moduleID'      => '{98BA2EDD-B52B-BAF2-6750-94FA9092B063}',
                'configuration' => [
                    'GroupTopic'   => $this->ReadPropertyString('GroupTopic'),
                    'MQTTTopic'    => $Device->MQTTTopic,
                ],
            ];

            $Values[] = $tmpDevice;
        }

        $Form['actions'][0]['values'] = $Values;
        return json_encode($Form);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');

        $MQTTTopic = $this->ReadPropertyString('GroupTopic');
        $this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData JSON', $JSONString, 0);
        $Data = json_decode($JSONString);

        if (property_exists($Data, 'Topic')) {
            $arrTopic = explode('/', $Data->Topic);
            $CountItems = count($arrTopic);
            $Topic = $arrTopic[array_key_last($arrTopic)];
            if ($Topic == 'Configuration') {
                $Devices = json_decode($Data->Payload);
                IPS_LogMessage('Devices', 'test');
                $this->WriteAttributeString('Devices', $Data->Payload);
                $this->UpdateFormField('Devices', 'values', $Data->Payload);

                $this->SendDebug(__FUNCTION__, 'Topic: ' . 'Configuration ', 0);
            }
            if ($Topic == 'VariablenProfiles') {
                $Profiles = json_decode($Data->Payload, true);

                foreach ($Profiles as $Profile) {
                    $profileName = $Profile['ProfileName'];

                    IPS_CreateVariableProfile($profileName, $Profile['ProfileType']);
                    IPS_SetVariableProfileText($profileName, $Profile['Prefix'], $Profile['Suffix']);
                    IPS_SetVariableProfileValues($profileName, $Profile['MinValue'], $Profile['MaxValue'], $Profile['StepSize']);
                    IPS_SetVariableProfileDigits($profileName, $Profile['Digits']);
                    IPS_SetVariableProfileIcon($profileName, $Profile['Icon']);
                    foreach ($Profile['Associations'] as $association) {
                        IPS_SetVariableProfileAssociation($profileName, $association['Value'], $association['Name'], $association['Icon'], $association['Color']);
                    }
                }
            }
        }
    }

    private function getMQTTBridgeClientDeviceInstance($Topic)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID('{98BA2EDD-B52B-BAF2-6750-94FA9092B063}'); //MQTTBridgeClientDevice
        foreach ($InstanceIDs as $id) {
            if (IPS_GetProperty($id, 'MQTTTopic') == $Topic) {
                if (IPS_GetInstance($id)['ConnectionID'] == IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                    return $id;
                }
            }
        }
        return 0;
    }
}
