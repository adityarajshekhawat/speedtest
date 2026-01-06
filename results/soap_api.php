<?php
// soap_api.php - SOAP API wrapper with correct authentication format
function getUserDetailsByIP($ip , $fromDate, $toDate) {
    echo "[START] getUserDetailsByIP function called for IP: $ip\n";
    echo "----------------------------------------\n";
    
    try {
        // SOAP endpoint
        $wsdl = "https://unify.spectra.co/unifyejb/UnifyWS?wsdl";
        echo "[INFO] SOAP WSDL URL: $wsdl\n";
        
        // Create SOAP client with stream context for custom headers
        echo "[STEP 1] Creating SOAP client with authentication...\n";
        
        // Create stream context with custom HTTP headers
        $opts = array(
            'http' => array(
                'header' => "username: admin\r\n" .
                           "password: admin\r\n"
            )
        );
        $context = stream_context_create($opts);
        
        $client = new SoapClient($wsdl, [
            'trace' => 1,
            'exceptions' => true,
            'connection_timeout' => 10,
            'default_socket_timeout' => 10,
            'stream_context' => $context
        ]);
        
        echo "[SUCCESS] SOAP client created with authentication headers\n";
        
        // Set date range (last 24 hours)
        // $fromDate = date('Y-m-d\TH:i:s', strtotime('-1 day'));
        // $toDate = date('Y-m-d\TH:i:s');
        echo "[INFO] Date range: $fromDate to $toDate\n";
        
        // Prepare parameters
        $params = [
            'ipaddr' => $ip,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'start' => 0,
            'limit' => 1  // We only need one result
        ];
        echo "[INFO] Request parameters:\n";
        print_r($params);
        
        // Call the SOAP method
        echo "\n[STEP 2] Calling SOAP method getDetailsByIp...\n";
        echo "[WAITING] This may take a few seconds...\n";
        $response = $client->getDetailsByIp($params);
        echo "[SUCCESS] SOAP call completed\n";
        
        // Debug: Show raw response
        echo "\n[DEBUG] Raw SOAP Response:\n";
        print_r($response);
        echo "\n";
        
        // Check if we got results
        if (isset($response->return)) {
            echo "[INFO] Response contains data\n";
            
            // Take the first result (they're usually duplicates)
            $data = is_array($response->return) ? $response->return[0] : $response->return;
            
            echo "[INFO] Extracted data:\n";
            echo "  Account Name: " . ($data->actName ?? 'N/A') . "\n";
            echo "  Account ID: " . ($data->actid ?? 'N/A') . "\n";
            echo "  Domain ID: " . ($data->domId ?? 'N/A') . "\n";
            echo "  Controller: " . ($data->accessController ?? 'N/A') . "\n";
            echo "  MAC Address: " . ($data->macaddr ?? 'N/A') . "\n";
            echo "  SNAT IP: " . ($data->snatIp ?? 'N/A') . "\n";
            echo "  Service Plan: " . ($data->pkgDescription ?? 'N/A') . "\n";
            echo "  Bandwidth: " . ($data->bandwidthPolicy ?? 'N/A') . "\n";
            echo "  Address: " . ($data->address ?? 'N/A') . "\n";
            echo "  City: " . ($data->city ?? 'N/A') . "\n";
            
            // Map SOAP response to our database fields
            $result = [
                'account_name' => $data->actName ?? null,
                'service_group_id' => $data->actid ?? null,
                'domain_id' => $data->domId ?? null,
                'access_controller' => $data->accessController ?? null,
                'mac_address' => $data->macaddr ?? null,
                'snat_ip' => $data->snatIp ?? null,
                'service_plan' => $data->pkgDescription ?? null,
                'bandwidth_policy_id' => $data->bandwidthPolicy ?? null,
                'address' => $data->address ?? null,
                'city' => $data->city ?? null
            ];
            
            echo "\n[SUCCESS] Data mapped successfully\n";
            echo "----------------------------------------\n";
            return $result;
        } else {
            echo "[WARNING] No data found in response\n";
            echo "----------------------------------------\n";
            return null; // No data found
        }
        
    } catch (SoapFault $e) {
        echo "\n[SOAP ERROR] " . $e->getMessage() . "\n";
        echo "[ERROR CODE] " . $e->getCode() . "\n";
        if (isset($e->faultstring)) {
            echo "[ERROR DETAIL] " . $e->faultstring . "\n";
        }
        
        // Debug: Show the last request/response if available
        if (isset($client)) {
            echo "\n[DEBUG] Last Request Headers:\n";
            echo $client->__getLastRequestHeaders();
            echo "\n[DEBUG] Last Request:\n";
            echo $client->__getLastRequest();
            echo "\n[DEBUG] Last Response:\n";
            echo $client->__getLastResponse();
        }
        
        echo "----------------------------------------\n";
        return false; // SOAP error occurred
    } catch (Exception $e) {
        echo "\n[GENERAL ERROR] " . $e->getMessage() . "\n";
        echo "[ERROR TYPE] " . get_class($e) . "\n";
        echo "----------------------------------------\n";
        return false; // Error occurred
    }
}
?>