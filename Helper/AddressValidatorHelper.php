<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticAddressValidatorBundle\Helper;

use Mautic\CoreBundle\Exception as MauticException;
use Joomla\Http\Http;
use Mautic\CoreBundle\Model\NotificationModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Mautic\PluginBundle\Helper\IntegrationHelper;

class AddressValidatorHelper
{
    /**
     * @var Http $connector ;
     */
    protected $connector;

    /**
     * @var RequestStack $request ;
     */
    protected $request;

    /**
     * @var IntegrationHelper $integrationHelper
     */
    protected $integrationHelper;

    protected $serverStatus = true;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var NotificationModel
     */
    private $notificationModel;


    /**
     * AddressValidatorHelper constructor.
     *
     * @param Http              $connector
     * @param RequestStack      $request
     * @param IntegrationHelper $integrationHelper
     * @param LoggerInterface   $logger
     * @param NotificationModel $notificationModel
     */
    public function __construct(
        Http $connector,
        RequestStack $request,
        IntegrationHelper $integrationHelper,
        LoggerInterface $logger,
        NotificationModel $notificationModel
    ) {
        $this->connector = $connector;
        $this->request = $request->getCurrentRequest();
        $this->integrationHelper = $integrationHelper;
        $this->logger = $logger;
        $this->notificationModel = $notificationModel;
    }


    /**
     * Validation
     * @param bool $check
     * @param null $value
     * @param array $data
     * @return bool|string|void
     */
    public function validation($check = false, $value = null, $values = [])
    {

        $integration = $this->integrationHelper->getIntegrationObject('AddressValidator');

        if (!$integration || $integration->getIntegrationSettings()->getIsPublished() === false) {
            return;
        }

        $featureSettings = $integration->getDecryptedApiKeys();

        if (isset($_POST['integration_details']['apiKeys'])) {
            $featureSettings = [
                'apiUrl' => $_POST['integration_details']['apiKeys']['apiUrl'],
                'validatorApiKey' => $_POST['integration_details']['apiKeys']['validatorApiKey'],
            ];
        }

        $dataToservice =  !empty($values) ? $values : $this->request->request->all();

        // On Submit form don't check validation
         if($check && !empty($dataToservice['mauticform'])){
             return true;
         }

       /* if(!empty($dataToservice['CountryCode']) && $dataToservice['CountryCode'] == 'Australia'){
            $dataToservice['CountryCode'] = 'au';
        }*/
        try {
            $data = $this->connector->post(
                $featureSettings['apiUrl'],
                $dataToservice,
                array('Authorization' => 'Token '.($value ? $value : $featureSettings['validatorApiKey']).''),
                10
            );
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->logger->error('Address Validator: '.$message);
            $this->notificationModel->addNotification(
                $message,
                'error',
                false,
                'Address Validator ERROR'

            );
            $this->serverStatus = false;
            if ($check) {
            //    return false;
                return true;
            }

            return json_encode(['address_validated' => false, 'status' => 'DOWN']);
        }

        $result = false;
        if (in_array($data->code, [200, 201])  && trim($data->body) != 'HTTP Token: Access denied.') {
            $result = true;
        }
        if ($check) {
            return $result;
        } elseif ($result == true) {
            return $data->body;
        } else {
            return json_encode(['address_validated' => false]);
        }
    }

    /**
     * @param array $data
     * @return array
     */
    public function parseDataFromRequest(array $data){

        $requestData = [];
        $requestData['StreetAddress']=  (isset($data['address1'])? $data['address1'] :'').' '.(isset($data['address2'])? $data['address2'] :'');
        $requestData['City'] = (isset($data['city'])? $data['city'] :'');
        $requestData['State'] = (isset($data['state'])? $data['state'] :'');
        $requestData['PostalCode'] = (isset($data['zip'])? $data['zip'] :'');
        $requestData['CountryCode'] = (isset($data['country'])? $data['country'] :'');
        return $requestData;
    }

    /**
     * @return boolean
     */
    public function isServerStatus()
    {
        return $this->serverStatus;
    }
}