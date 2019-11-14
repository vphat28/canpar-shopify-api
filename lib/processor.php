<?php

/*
 * redirect_uri = /?mode=generatetoken
call_back_path = /?mode=call_back
/?mode=install

 */
class processor extends stdClass
{
    const SHOPIFY_BASE_URL = ".myshopify.com";
    const USER_AGENT = "CH Canpar Shopify Php v1.4";
    const LOOMIS_USER_AGENT = "CH Loomis Shopify Php v1.4";
    const ACCESS_MODE = "per-user";

    const AES_METHOD = "AES-128-ECB";
    const DATE_FORMAT = "Y-m-d H:i:s O";

    const ENC_KEY = "&*I&JI(O*UJ&*I*(&KU&*I(&&(KLS09";

    const URI_RATING_SANDBOX = "https://sandbox.canpar.com/canshipws/services/CanparRatingService";
    const URI_RATING_PROD = "https://canship.canpar.com/canshipws/services/CanparRatingService";

    const LOOMIS_URI_RATING_SANDBOX = "https://sandbox.loomis-express.com/axis2/services/USSRatingService";
    const LOOMIS_URI_RATING_PROD = "https://loomis-express.com/axis2/services/USSRatingService";


    protected $__get;

    protected $_referenceSerivceArray = array(
        '1' => 'CANPAR GROUND',
        '2' => 'CANPAR U.S.A.',
        '3' => 'CANPAR SELECT LETTER',
        '4' => 'CANPAR SELECT PAK',
        '5' => 'CANPAR SELECT',
        'C' => 'CANPAR EXPRESS LETTER',
        'D' => 'CANPAR EXPRESS PAK',
        'E' => 'CANPAR EXPRESS' ,
        'F' => 'CANPAR U.S.A. LETTER',
        'G' => 'CANPAR U.S.A. PAK',
        'H' => 'CANPAR SELECT U.S.A.' ,
        'I' => 'CANPAR INTERNATIONAL');

    protected $_referenceSerivceArray2 = array(
        '1' => 'canpar_1',
        '2' => 'canpar_2',
        '3' => 'canpar_3',
        '4' => 'canpar_4',
        '5' => 'canpar_5',
        'C' => 'canpar_6',
        'D' => 'canpar_7',
        'E' => 'canpar_8' ,
        'F' => 'canpar_9',
        'G' => 'canpar_10',
        'H' => 'canpar_11' ,
        'I' => 'canpar_12');

    protected $_configFile;
    protected $_config;
    protected $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    protected $_modes = array(
        'install' => '_install',
        'generatetoken' => '_generateToken',
        'call_back' => '_callBack',
        'canparapi' => '_canparapi',
        'testsql' => '_testsql',
    );

    public function __construct()
    {
        DEFINE('DS', '/');
        $this->_configFile = __DIR__ . '/../etc/config.ini';
        $this->_config = parse_ini_file($this->_configFile);
        $this->_config['BASE_PATH'] = realpath(__DIR__ . '/../');
        $this->_config['LOG_FILE'] = $this->_config['BASE_PATH'] . DS . $this->_config['LOG_FILE'];
    }


    protected function getEndpoint()
    {
        if (GATEWAY_BRAND === 'loomis') {
            return $this->getConfig('test_mode') ? self::LOOMIS_URI_RATING_SANDBOX : self::LOOMIS_URI_RATING_PROD;
        } else {
            return $this->getConfig('test_mode') ? self::URI_RATING_SANDBOX : self::URI_RATING_PROD;
        }
    }
    protected function getConfig($x)
    {
        return isset($this->_config[$x]) ? $this->_config[$x] : false;
    }

    protected function _testsql()
    {

        echo " weh ave some bits " . $this->safeDecrypt('pJ2IArklTcSPZk0vdb4uXg==') . "\n";
        exit;
        $mysqli = $this->getSqlConnection();


        $sql = "show create table api_data";

        $ret = $mysqli->query($sql);

        echo "<pre>";
        print_r($ret);

        $row = $ret->fetch_array(MYSQLI_NUM);

        print_r($row);

        echo "done";
        exit;
    }

    protected function getMode()
    {
        return isset($_GET) && isset($_GET['mode']) && isset($this->_modes[$_GET['mode']]) ? $this->_modes[$_GET['mode']] : false;
    }

    protected function getShopParam()
    {
        if(isset($_GET['shop']) && strpos($_GET['shop'], self::SHOPIFY_BASE_URL) !== false) {
            return $_GET['shop'];
        }
        return false;
    }

    protected function _canparapi()
    {

        if(isset($_POST)) {


            $shopify = $_POST["shopify"];
            $shop = $_POST["shop"];
            $password = $_POST["shipper_password"];
            $shipper_num = $_POST["shipper_num"];
            $user_Id = $_POST["user_id"];


            $mysqli = $this->getSqlConnection();


            $shop = $mysqli->real_escape_string($shop);
            $user_Id = $mysqli->real_escape_string($user_Id);

            $password = $mysqli->real_escape_string($this->safeEncrypt($password));
            $shipper_num = $mysqli->real_escape_string($shipper_num);


            $sql = "replace INTO api_data VALUES ('$shop', '$user_Id', '$password', '$shipper_num', now(), now(), 0 );";

            $ret = $mysqli->query($sql);
            if ($ret !== TRUE) {
                $this->log(__METHOD__ . __LINE__ . " Query failed: $sql {$ret} " . $mysqli->error);
                die("Sorry there was an error processing your request (9954)");
            }

            $shopify = "http://" . $shopify . self::SHOPIFY_BASE_URL;


            $mysqli->close();

            header("Location: {$shopify}/admin/settings/shipping");
            exit;

        }
        die("Sorry there was an error processing your request (9952)");
    }

    protected function _callBack()
    {

        $filename = time();
        $input = file_get_contents('php://input');
        $this->log(__METHOD__ . __LINE__  . '-input '  . $input);

// parse the request
        $rates = json_decode($input, true);

        $services = $this->canpar_api_call($rates);

// log the array format for easier interpreting
        $this->log(__METHOD__ . __LINE__  . '-debug ' . print_r($rates, true));
        $this->log(__METHOD__ . __LINE__  . '-return ' . print_r($services, true));

// build the array of line items using the prior values
        $output = array( 'rates' => array() );

        foreach($services as $service) {
            array_push($output['rates'] , $service);
        }

// encode into a json response
        $json_output = json_encode($output);

// log it so we can debug the response
        $this->log(__METHOD__ . __LINE__  .  '-output' . $json_output);

// send it back to shopify
        // TODO sharper we need output handler
        print $json_output;

    }

    /**
     * This is the first file in the Shopify-App handshake. This will request the Shopify Shop Owner to allow
     * to determine if the app can to install and allow requests for shipping information from Shopify
     * This will begin the installation of the app in Shopify The file must be reported with the Shopify
     * App Profile so Shopify knows where urls to request information from
     *
     */

    // Get our helper functions
    public function setOriginalGet($x)
    {
        $this->__get = $x;
    }

    protected function _generateToken()
    {

        // NOTE we have to use GET before we changed it..
        $params = $this->__get; // Retrieve all request parameters
        $hmac = $_GET['hmac']; // Retrieve HMAC request parameter
        $params = array_diff_key($params, array('hmac' => '')); // Remove hmac from params
        $params = array_diff_key($params, array('mode' => '')); // Remove mode from params

        ksort($params); // Sort params lexographically

        $computed_hmac = hash_hmac('sha256', http_build_query($params), $this->getConfig('shared_secret'));

        $this->log(" we have $hmac, $computed_hmac  " . json_encode($params));

        if (hash_equals($hmac, $computed_hmac)) {
            // Set variables for our request
            $query = array(
                "client_id" => $this->getConfig('api_key'), // Your API key
                "client_secret" => $this->getConfig('shared_secret'), // Your app credentials (secret key)
                "code" => $params['code'] // Grab the access key from the URL
            );
            // Generate access token URL
            $access_token_url = "https://" . $params['shop'] . "/admin/oauth/access_token";
            // Configure curl client and execute request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $access_token_url);
            curl_setopt($ch, CURLOPT_POST, count($query));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
            $result = curl_exec($ch);
            curl_close($ch);
            // Store the access token
            $result = json_decode($result, true);
            $access_token = $result['access_token'];
            // Show the access token (don't do this in production!)
            if($access_token) {
                $shop = str_replace(self::SHOPIFY_BASE_URL,"", $params['shop']);
                $data = array(
                    'carrier_service' => array(
                        'name' => 'Canpar',
                        'callback_url' => $this->_config['base_url'] . $this->_config['call_back_path'], // 'http://reeceburdevsite.com/shopifyapp.php',
                        'email' => $this->_config['email'],
                        'service_discovery' => true
                    ),
                );


                // TODO What is carrier for; we are not using it?
                $carrier = $this->shopify_call($access_token, $shop, "/admin/api/2019-04/carrier_services.json", $data, 'POST');
                // TODO replaced the first var it was $token replaced with $access_token is that correct?
                echo $this->get_shopify_canpar_api_token($access_token, $shop);


            }
        } else {
            // Someone is trying to be shady!
            die('This request is NOT from Shopify!');
        }
    }

    protected function _install()
    {
        $shop = $this->getShopParam();
        if(!$shop) {
            die("invalid request 009");
        }
        // Build install/approval URL to redirect to
        $installUrl = "http://" .
            $shop .
            "/admin/oauth/authorize?client_id=" .
            $this->getConfig('api_key') .
            "&scope=" .
            $this->getConfig('scopes') .
            "&redirect_uri=" .
            urlencode($this->getConfig('base_url') . $this->getConfig('redirect_uri')) .
            "&state=" . $this->getName() .
            "&grant_options[]=" . self::ACCESS_MODE;
        // Redirect
        header("Location: " . $installUrl);
        exit;
    }

    public function run()
    {

        $mode = $this->getMode();

        if($mode) {

            $this->$mode();
            exit;
        }

        echo $this->getTemplate('landingpage.phtml');

    }


    /**
     * Creates a randomString of Character of defined length
     *
     * @param integer length of random string
     *
     * @author Reece Burborough <rburborough@collinsharper.com>
     * @return string random string of determined length
     */

    function getName($n = 12) {

        $randomString = '';

        for ($i = 0; $i < $n; $i++) {
            $index = rand(0, strlen($this->characters) - 1);
            $randomString .= $this->characters[$index];
        }

        return $randomString;
    }


    /**
     * Creates Shopify API calls
     *
     * @param array   $userCred  Shipper's Canpar credentials
     * @param array   $delivery_address  Delivery address
     * @param array   $pickup_address  Pickup address
     *
     * @author Reece Burborough <rburborough@collinsharper.com>
     * @return array Shopify response to the API request
     */
    function shopify_call($token, $shop, $api_endpoint, $query = array(), $method = 'GET', $request_headers = array())
    {

        // Build URL
        $url = "https://" . $shop . self::SHOPIFY_BASE_URL  . $api_endpoint;
        if (!is_null($query) && in_array($method, array('GET', 	'DELETE'))) {
            $url = $url . "?" . http_build_query($query);
        }
        // Configure cURL
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, TRUE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 3);
        // curl_setopt($curl, CURLOPT_SSLVERSION, 3);
        if (GATEWAY_BRAND === 'loomis') {
            curl_setopt($curl, CURLOPT_USERAGENT, self::LOOMIS_USER_AGENT);
        } else {
            curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);
        }
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        // Setup headers
        //$request_headers[];
        //if (!is_null($token)) {
        //     $request_headers[] = "X-Shopify-Access-Token: " . $token;
        //}
        $request_headers[] = "";
        if (!is_null($token)) {
            $request_headers[] = "X-Shopify-Access-Token: " . $token;
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

        if ($method != 'GET' && in_array($method, array('POST', 'PUT'))) {
            if (is_array($query)) {
                $query = http_build_query($query);
            }
            curl_setopt ($curl, CURLOPT_POSTFIELDS, $query);
        }

        // Send request to Shopify and capture any errors
        $response = curl_exec($curl);
        $error_message = curl_error($curl);
        // Close cURL to be nice
        curl_close($curl);

        $result = json_decode($response, true);
        $this->log($result);

        // Return an error is cURL has a problem
        if( $error_message ) {
            $this->log(__METHOD__ . " Error ". print_r($result,1));

            return array('error_message' => $error_message);
        }

        // No error, return Shopify's response by parsing out the body and the headers
        $response = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        // Convert headers into an array
        $headers = array();
        $header_data = explode("\n",$response[0]);
        $headers['status'] = $header_data[0]; // Does not contain a key, have to explicitly set
        array_shift($header_data); // Remove status, we've already set it above
        foreach($header_data as $part) {
            $h = explode(":", $part);
            $headers[trim($h[0])] = trim($h[1]);
        }
        // Return headers and Shopify's response
        return array('headers' => $headers, 'response' => $response[1]);


    }

    function encryptPassword($login)
    {
        return crypt($login);
    }

    function decryptPassword($login, $crypted)
    {
        if(crypt($login, $crypted) == $crypted) {
            return true;
        }
        return false;
    }

    function log($data)
    {
        file_put_contents($this->_config['LOG_FILE'], date('c') . " - " . print_r($data, 1) . " \n", FILE_APPEND);
    }

    function getTemplate($file, $passedData = array())
    {
        if(is_array($passedData)) {
            $data = new stdClass();
            foreach($passedData as $k => $v) {
                $data->$k = $v;
            }
        }

        if (GATEWAY_BRAND === 'loomis') {
            $templateInclude = $this->_config['BASE_PATH'] . DS . $this->_config['template_path'] . DS . 'loomis' . DS . $file;
        } else {
            $templateInclude = $this->_config['BASE_PATH'] . DS . $this->_config['template_path'] . DS . $file;
        }

        ob_start();

        include($templateInclude);

        $html = ob_get_contents();

        ob_end_clean();

        return $html;
    }

    function get_shopify_canpar_api_token($token, $shop)
    {
        return $this->getTemplate('registration.phtml', array('config' => $this->_config, 'shop' => $shop, 'token' => $token));
    }


    function canpar_shipping_origin($shipperInformation)
    {
        return $this->_buildAddressObject($shipperInformation['rate']['origin']);
    }

    function _buildAddressObject($address)
    {
        $postal_code = str_replace(' ', '', $address['postal_code']);
        $destination_address = array(
            'address1' => $address['address1'],
            'address2' => $address['address2'],
            'address2' => $address['address3'],
            'city' =>  $address['city'],
            'province' =>  $address['province'],
            'country' => $address['country'],
            'postalcode' =>	$postal_code,
            'email' => $address['email'],
            'phone' => $address['phone'],
        );

        return $destination_address;

    }

    function canpar_shipping_destination($shipperInformation)
    {
        return $this->_buildAddressObject($shipperInformation['rate']['destination']);
    }

    function canpar_shipping_package($shipperInformation)
    {

        $weight = 0;
        $items = $shipperInformation['rate']['items'];

        foreach($items as $item) {
            $weight= $weight + ($item['grams']*$item['quantity']);
        }

        // Convert grams to lbs
        $weight = $weight * 0.0022046;

        $package = array(
            'weight' => $weight
        );

        $this->log(__METHOD__ . __LINE__  . '-package '  . print_r($package,true));

        return $package;

    }
    function canpar_api_service_call($userCred,$delivery_address,$pickup_address) {

        $date = date('Y-m-d H:i:s.BP', strtotime('now'));
        $date = str_replace(" ","T",$date);

        $xml_data =
            '<?xml version="1.0" encoding="ISO-8859-1"?>
	<soap:Envelope xmlns:xsd="http://ws.dto.canshipws.canpar.com/xsd" xmlns:ws="http://ws.onlinerating.canshipws.canpar.com" xmlns:soap="http://www.w3.org/2003/05/soap-envelope" ><soap:Header/>
	<soap:Body>
	<ws:getAvailableServices>
	<ws:request>
	<xsd:delivery_country>'. $delivery_address['country'] .'</xsd:delivery_country>
	<xsd:delivery_postal_code>' .$delivery_address['postalcode'] .'</xsd:delivery_postal_code>
	<xsd:password>'.$userCred['password'] .'</xsd:password>
	<xsd:pickup_postal_code>'.$pickup_address['postalcode'] .'</xsd:pickup_postal_code>
	<xsd:shipper_num>'.$userCred['shipper_number'] .'</xsd:shipper_num>
	<xsd:shipping_date>'. $date .'</xsd:shipping_date>
	<xsd:user_id>'. $userCred['user_id'] .'</xsd:user_id>
	</ws:request>
	</ws:getAvailableServices>
	</soap:Body>
	</soap:Envelope>';


        return $xml_data;

    }


    function can_api_all_service_rate($userCred,$delivery_address,$pickup_address,$package) {

        $total_weight = $package['weight'];

        $xml_data = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ws="http://ws.onlinerating.canshipws.canpar.com" xmlns:xsd="http://ws.dto.canshipws.canpar.com/xsd" xmlns:xsd1="http://dto.canshipws.canpar.com/xsd">
	   <soap:Header/>
	   <soap:Body>
	      <ws:rateShipmentAllServices>
	         <ws:request>
	            <xsd:password>'.$userCred['password'].'</xsd:password>
	            <xsd:shipment>
	               <xsd1:delivery_address>
	                  <xsd1:address_line_1>'.$delivery_address['address1'] .'</xsd1:address_line_1>
	                  <xsd1:city>'.$delivery_address['city'] .'</xsd1:city>
	                  <xsd1:country>'.$delivery_address['country'] .'</xsd1:country>
	                  <xsd1:name>Test</xsd1:name>
	                  <xsd1:postal_code>'.$delivery_address['postalcode'] .'</xsd1:postal_code>
	                  <xsd1:province>'.$delivery_address['province'] .'</xsd1:province>
	               </xsd1:delivery_address>
	               <xsd1:packages>
	                  <xsd1:reported_weight>'. $total_weight .'</xsd1:reported_weight>
	               </xsd1:packages>
	               <xsd1:pickup_address>
	                  <xsd1:address_line_1>'.$pickup_address['address1'].'</xsd1:address_line_1>
	                  <xsd1:city>'.$pickup_address['city'].'</xsd1:city>
	                  <xsd1:country>'.$pickup_address['country'].'</xsd1:country>
	                  <xsd1:name>test</xsd1:name>
	                  <xsd1:postal_code>'.$pickup_address['postalcode'].'</xsd1:postal_code>
	                  <xsd1:province>'.$pickup_address['province'].'</xsd1:province>
	               </xsd1:pickup_address>
	               <xsd1:reported_weight_unit>L</xsd1:reported_weight_unit>
	               <xsd1:shipper_num>'.$userCred['shipper_number'].'</xsd1:shipper_num>
	            </xsd:shipment>
	            <xsd:user_id>'.$userCred['user_id'].'</xsd:user_id>
	         </ws:request>
	      </ws:rateShipmentAllServices>
	   </soap:Body>
	</soap:Envelope>';

        return $xml_data;

    }

    function canpar_api_service_rate_call($userCred,$delivery_address,$pickup_address,$package) {

        $date = date('Y-m-d H:i:s.BP', strtotime('now'));
        $date = str_replace(" ","T",$date);

        $total_weight = $package['weight'];

        $xml_data_rate_template =
            '<?xml version="1.0" encoding="ISO-8859-1"?>
	<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
	<soapenv:Body>
	<ns3:rateShipment xmlns:ns3="http://ws.onlinerating.canshipws.canpar.com">
	<ns3:request>
	<ns1:apply_association_discount xmlns:ns1="http://ws.dto.canshipws.canpar.com/xsd">false</ns1:apply_association_discount>
	<ns1:apply_individual_discount xmlns:ns1="http://ws.dto.canshipws.canpar.com/xsd">false</ns1:apply_individual_discount>
	<ns1:apply_invoice_discount xmlns:ns1="http://ws.dto.canshipws.canpar.com/xsd">false</ns1:apply_invoice_discount>
	<ns1:password xmlns:ns1="http://ws.dto.canshipws.canpar.com/xsd">demo</ns1:password>
	<shipment xmlns="http://ws.dto.canshipws.canpar.com/xsd">
	<ns2:billed_weight xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:billed_weight>
	<ns2:billed_weight_unit xmlns:ns2="http://dto.canshipws.canpar.com/xsd">L</ns2:billed_weight_unit>
	<ns2:cod_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:cod_charge>
	<ns2:cod_type xmlns:ns2="http://dto.canshipws.canpar.com/xsd">N</ns2:cod_type>
	<ns2:collect_shipper_num xmlns:ns2="http://dto.canshipws.canpar.com/xsd"/>
	<ns2:consolidation_type xmlns:ns2="http://dto.canshipws.canpar.com/xsd"/>
	<ns2:cos xmlns:ns2="http://dto.canshipws.canpar.com/xsd">false</ns2:cos>
	<ns2:cos_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:cos_charge>
	<delivery_address xmlns="http://dto.canshipws.canpar.com/xsd">
	<address_id>'.$delivery_address['country'] .'</address_id>
	<address_line_1>'.$delivery_address['address1'] .'</address_line_1>
	<address_line_2>'.$delivery_address['address2'] .'</address_line_2>
	<address_line_3>'.$delivery_address['address3'] .'</address_line_3>
	<attention/>
	<city>'.$delivery_address['city'] .'</city>
	<country>'.$delivery_address['country'] .'</country>
	<email>'.$delivery_address['email'] .'</email>
	<extension></extension>
	<id>-1</id>
	<name>TEST ADDRESS</name>
	<phone>'.$delivery_address['phone'] .'</phone>
	<postal_code>'.$delivery_address['postalcode'] .'</postal_code>
	<province>'.$delivery_address['province'] .'</province>
	<residential>false</residential>
	</delivery_address>
	<ns2:description xmlns:ns2="http://dto.canshipws.canpar.com/xsd"/>
	<ns2:dg xmlns:ns2="http://dto.canshipws.canpar.com/xsd">false</ns2:dg>
	<ns2:dg_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:dg_charge>
	<ns2:dimention_unit xmlns:ns2="http://dto.canshipws.canpar.com/xsd">I</ns2:dimention_unit>
	<ns2:dv_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:dv_charge>
	<ns2:ea_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:ea_charge>
	<ns2:ea_zone xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0</ns2:ea_zone>
	<ns2:freight_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:freight_charge>
	<ns2:fuel_surcharge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:fuel_surcharge>
	<ns2:handling xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:handling>
	<ns2:handling_type xmlns:ns2="http://dto.canshipws.canpar.com/xsd">$</ns2:handling_type>
	<ns2:id xmlns:ns2="http://dto.canshipws.canpar.com/xsd">-1</ns2:id>
	<ns2:instruction xmlns:ns2="http://dto.canshipws.canpar.com/xsd"/>
	<ns2:manifest_num xmlns:ns2="http://dto.canshipws.canpar.com/xsd" xsi:nil="1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"/>
	<ns2:nsr xmlns:ns2="http://dto.canshipws.canpar.com/xsd">false</ns2:nsr>
	<packages xmlns="http://dto.canshipws.canpar.com/xsd">
	<alternative_reference/>
	<barcode/>
	<billed_weight>0.0</billed_weight>
	<cod xsi:nil="1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"/>
	<cost_centre/>
	<declared_value>0.0</declared_value>
	<dim_weight>0.0</dim_weight>
	<dim_weight_flag>false</dim_weight_flag>
	<height>0.0</height>
	<id>-1</id>
	<length>0.0</length>
	<min_weight_flag>false</min_weight_flag>
	<package_num>0</package_num>
	<package_reference>0</package_reference>
	<reference/>
	<reported_weight>'. $total_weight . '</reported_weight>
	<store_num/>
	<width>0.0</width>
	<xc>false</xc>
	</packages>
	<pickup_address xmlns="http://dto.canshipws.canpar.com/xsd">
	<address_id>A1</address_id>
	<address_line_1>'.$pickup_address['address1'] .'</address_line_1>
	<address_line_2>'.$pickup_address['address2'] .'</address_line_2>
	<address_line_3>'.$pickup_address['address3'] .'</address_line_3>
	<attention/>
	<city>'.$pickup_address['city'] .'</city>
	<country>'.$pickup_address['country'] .'</country>
	<email>'.$pickup_address['email'] .'</email>
	<extension>23</extension>
	<id>-1</id>
	<name>TEST ADDRESS</name>
	<phone>'.$pickup_address['phone'] .'</phone>
	<postal_code>'.$pickup_address['postalcode'] .'</postal_code>
	<province>'.$pickup_address['province'] .'</province>
	<residential>false</residential>
	</pickup_address>
	<ns2:premium xmlns:ns2="http://dto.canshipws.canpar.com/xsd">N</ns2:premium>
	<ns2:premium_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:premium_charge>
	<ns2:proforma xmlns:ns2="http://dto.canshipws.canpar.com/xsd" xsi:nil="1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"/>
	<ns2:ra_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:ra_charge>
	<ns2:reported_weight_unit xmlns:ns2="http://dto.canshipws.canpar.com/xsd">L</ns2:reported_weight_unit>
	<ns2:rural_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:rural_charge>
	<ns2:send_email_to_delivery xmlns:ns2="http://dto.canshipws.canpar.com/xsd">false</ns2:send_email_to_delivery>
	<ns2:send_email_to_pickup xmlns:ns2="http://dto.canshipws.canpar.com/xsd">false</ns2:send_email_to_pickup>
	<ns2:service_type xmlns:ns2="http://dto.canshipws.canpar.com/xsd">SERVICETYPE</ns2:service_type>
	<ns2:shipment_status xmlns:ns2="http://dto.canshipws.canpar.com/xsd">R</ns2:shipment_status>
	<ns2:shipper_num xmlns:ns2="http://dto.canshipws.canpar.com/xsd">99999992</ns2:shipper_num>
	<ns2:shipping_date xmlns:ns2="http://dto.canshipws.canpar.com/xsd">'. $date .'</ns2:shipping_date>
	<ns2:tax_charge_1 xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:tax_charge_1>
	<ns2:tax_charge_2 xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:tax_charge_2>
	<ns2:tax_code_1 xmlns:ns2="http://dto.canshipws.canpar.com/xsd"/>
	<ns2:tax_code_2 xmlns:ns2="http://dto.canshipws.canpar.com/xsd"/>
	<ns2:transit_time xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0</ns2:transit_time>
	<ns2:transit_time_guaranteed xmlns:ns2="http://dto.canshipws.canpar.com/xsd">false</ns2:transit_time_guaranteed>
	<ns2:user_id xmlns:ns2="http://dto.canshipws.canpar.com/xsd"/>
	<ns2:voided xmlns:ns2="http://dto.canshipws.canpar.com/xsd">false</ns2:voided>
	<ns2:xc_charge xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0.0</ns2:xc_charge>
	<ns2:zone xmlns:ns2="http://dto.canshipws.canpar.com/xsd">0</ns2:zone>
	</shipment>
	<ns1:user_id xmlns:ns1="http://ws.dto.canshipws.canpar.com/xsd">CH@DEMO.COM</ns1:user_id>
	</ns3:request>
	</ns3:rateShipment>
	</soapenv:Body>
	</soapenv:Envelope>';


        return $xml_data_rate_template;

    }

    function getSqlConnection()
    {
        $mysqli = new mysqli($this->getConfig('servername'),
            $this->getConfig('databaseUser'),
            $this->getConfig('databasePassword'),
            $this->getConfig('databaseName')
        );

        if ($mysqli->connect_error) {
            $this->log('Connect Error (' . $mysqli->connect_errno . ') '
                . $mysqli->connect_error);
            die(" sorry, we had an internal error (33344)");
        }


        return $mysqli;
    }

    function getUserCredentials($shop) {


        $mysqli = $this->getSqlConnection();

        if (!$mysqli) {
            $this->log(__METHOD__ . __LINE__ . " problem with connection : " . mysqli_connect_error());
            die(" error in your request (4455)");
        }


        $shop = $mysqli->real_escape_string($shop);
        $sql = "Select * FROM api_data  WHERE shop='$shop' limit 1";
        $sqlTwo = "update api_data  set requests = requests + 1 WHERE shop='$shop' ";

        $this->log(__METHOD__ . " Run $sql ");
        $this->log(__METHOD__ . " run update $sqlTwo");

        $resultTwo = $mysqli->query($sqlTwo);

        $result = $mysqli->query($sql);

        $row = $result->fetch_assoc();
        $this->log(__METHOD__ . " sql $sql " . print_r($row ,1));

        if(!$row) {
            $this->log(__METHOD__ . " issue with $sql ");
            die(" we had an internal isue (33345)");
        }

        $password = $this->safeDecrypt($row["password"]);
        $user_id = $row["user_id"];
        $shipper_number = $row["shipper_number"];

        $mysqli->close();

        $user_credentials = array(
            'password' => $password,
            'user_id' => $user_id ,
            'shipper_number' => $shipper_number,
        );

        return $user_credentials;
    }

    function getItemByRelativeKey($item, $key)
    {
        foreach ($item as $serviceKey => $serviceTmp) {
            if (strpos($serviceKey, $key) !== false) {
                return $serviceTmp;
            }
        }

        return null;
    }

    function canpar_api_call($shipping_information)
    {


        $this->log(__METHOD__ . __LINE__  . '-servicecall' . print_r($shipping_information, true));

        $pickup_address = $this->canpar_shipping_origin($shipping_information);
        $delivery_address = $this->canpar_shipping_destination($shipping_information);
        $package = $this->canpar_shipping_package($shipping_information);
        // TODO is this sound for getting the store name?
        $userCred = $this->getUserCredentials($shipping_information['rate']['origin']['company_name']);

        $this->log(__METHOD__ . __LINE__ . '-usercred' .  print_r($userCred, true));
        $this->log(__METHOD__ . __LINE__ . '-delivery_address' . print_r($delivery_address, true));
        $this->log(__METHOD__ . __LINE__ . '-pickup_address' . print_r($pickup_address, true));

        $xml_data = $this->can_api_all_service_rate($userCred, $delivery_address, $pickup_address,$package);


        $this->log(__METHOD__ . __LINE__  . '-rates' . print_r($xml_data, true));

        $URL = $this->getEndpoint();

        $ch = curl_init($URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/soap+xml charset=ISO-8859-1'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml_data");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $error_number = curl_errno($ch);
        $error_message = curl_error($ch);

        $this->log(__METHOD__ . __LINE__ ." response ". print_r($response,1));
        // Return an error is cURL has a problem
        if( $error_message ) {
            $this->log(__METHOD__ . " Error ". print_r($response,1));

            return array('error_message' => $error_message);
        }

        curl_close($ch);

        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
        $this->log(__METHOD__ . " APi response ". print_r($response,1));
        $xml = new SimpleXMLElement($response);
        $body = $xml->xpath('//ax25processShipmentResult');
        $servicesArray = json_decode(json_encode((array)$body), TRUE);

        $this->log(__METHOD__ . __LINE__  . ' -serviceresponse body ' . print_r($body, true));
        $this->log(__METHOD__ . __LINE__  . ' -serviceresponse ' . print_r($response, true));
        $this->log(__METHOD__ . __LINE__  . ' -serviceresponseArray ' . print_r($servicesArray, true));


        $rateArray = array();

        foreach($servicesArray as $service) {

            $this->log(__METHOD__ . __LINE__  . ' -service item ' . print_r($service, true));
            $serviceCode = $this->getItemByRelativeKey($service, 'shipment');
            $deliverydate = $this->getItemByRelativeKey($serviceCode, 'estimated_delivery_date');

            $cost = '';
            $cost = $this->getItemByRelativeKey($serviceCode, 'total_with_handling');

            $on_min_date = date(self::DATE_FORMAT , strtotime($deliverydate));
            $on_max_date = date(self::DATE_FORMAT , strtotime($deliverydate));

            $serviceType = false;

            if (!empty($this->getItemByRelativeKey($serviceCode, 'service_type'))) {
                $serviceType = $this->getItemByRelativeKey($serviceCode, 'service_type');
            }

            if( intval($cost) > 0 && isset($this->_referenceSerivceArray[$serviceType])) {
                $cost = $cost * 100;
                //$serviceRateArray = array(
                $mergeArray = array(
                    'service_name' => $this->_referenceSerivceArray[$serviceType],
                    'service_code' => 'canpar_'. $serviceType,
                    'total_price' => $cost,
                    'currency' => 'CAD',
                    'min_delivery_date' => $on_min_date,
                    'max_delivery_date' => $on_max_date
                );

                array_push($rateArray , $mergeArray);
            }
        }

        return $rateArray;

    }

    /**
     * Encrypt a message
     *
     * @param string $message - message to encrypt
     * @param string $key - encryption key
     * @return string
     * @throws RangeException
     */
    function safeEncrypt($message, $key = false)
    {
        if(!$key) {
            $key = self::ENC_KEY;
        }

        return openssl_encrypt($message, self::AES_METHOD, $key);
    }

    /**
     * Decrypt a message
     *
     * @param string $encrypted - message encrypted with safeEncrypt()
     * @param string $key - encryption key
     * @return string
     * @throws Exception
     */
    function safeDecrypt($encrypted, $key = false)
    {
        if(!$key) {
            $key = self::ENC_KEY;
        }

        return openssl_decrypt($encrypted, self::AES_METHOD, $key);
    }

}
