<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Domain\TopLevel\ImportItem;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * WHMCS Module Version
 */
define("IBS_MODULE_VERSION", "4.0.13"); // update whmcs.json as well
/**
 * live api server url
 */
define("API_SERVER_URL", "https://api.internet.bs/");
/**
 * api test server url, when $params["TestMode"]="on" is used, then this url will be used
 */
define("API_TESTSERVER_URL", "https://testapi.internet.bs/"); // 99.81.115.213 testapi.internet.bs

function ibs_call($params, $script, $data = [])
{
    $apiServerUrl = ($params["TestMode"] === "on") ?
        API_TESTSERVER_URL :
        API_SERVER_URL;

    $data = array_merge([
        "apikey" => $params["Username"],
        "password" => $params["Password"]
    ], $data);

    $commandUrl = $apiServerUrl . $script;
    $postFields = http_build_query($data);
    $conn = curl_init();
    // Note: Passing an array to CURLOPT_POSTFIELDS will encode the data as multipart/form-data,
    // while passing a URL-encoded string will encode the data as application/x-www-form-urlencoded.
    curl_setopt_array($conn, [
        CURLOPT_URL => $commandUrl,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => "WHMCS (IBS v" . IBS_MODULE_VERSION . ")",
        CURLOPT_HTTPHEADER => [
            "Expect:",
            "Content-Type: application/x-www-form-urlencoded", //UTF-8 implied
            "Content-Length: " . strlen($postFields)
        ]
    ]);
    $result = curl_exec($conn);

    $log = [
        "action" => $commandUrl,
        "environment" => " @ " . ($params["TestMode"] === "on") ? "DEV" : "LIVE",
        "requestParam" => $data,
        // hide password, find auth code handling later
        "replace" => [$data["password"]]
    ];

    if ($result === false) {
        $ibs_last_error = curl_error($conn);
        curl_close($conn); // keep it after curl_error!
        ibs_debugLog(array_merge($log, [
            "responseParam" => $ibs_last_error
        ]));
        return [
            "status" => "FAILURE",
            "message" => "Cannot connect to server. [" . $ibs_last_error . "]"
        ];
    }

    curl_close($conn);
    $parsed = ibs_parseResult($result);
    // hide auth codes
    if (isset($parsed["transferauthinfo"]) && strlen($parsed["transferauthinfo"])) {
        $log["replace"][] = $parsed["transferauthinfo"];
    }
    ibs_debugLog(array_merge($log, [
        "responseParam" => $result
    ]));
    return $parsed;
}

function ibs_billableOperationErrorHandler($params, $subject, $message)
{
    //get dept id
    if (preg_match("/.+\((\d+)\)/ix", $params["NotifyOnError"], $regs)) {
        localAPI("OpenTicket", [
            "deptid" => $regs[1],
            "subject" => "IBS MODULE ERROR: " . $subject,
            "message" => $message,
            "priority" => "High",
            "email" => "info@support.internet.bs",
            "name" => "Internet.bs registrar module"
        ]);
    }
}
function ibs_getInputDomain($params)
{
    if (!isset($params["domainname"])) {
        return $params["sld"] . "." . $params["tld"];
    }
    if (!isset($params["original"]["domainname"])) {
        return $params["domainname"];
    }
    return $params["original"]["domainname"];
}
/**
 * Returns whois status of domain
 * @param $params
 * @return array
 */
function ibs_getwhois($params)
{
    $domainName = ibs_getInputDomain($params);

    $resourcePath = "Domain/PrivateWhois/Status";
    if (isset($_POST["status"])) {
        $resourcePath = (strtolower($_POST["status"]) === "disabled") ?
            "Domain/PrivateWhois/enable" :
            "Domain/PrivateWhois/disable";
    }

    $result = ibs_call($params, $resourcePath, [
        "domain" => $domainName
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        $errormessage = $result["message"];
        $idStatus = "unknown";
    } elseif (!$result["status"]) {
        $errormessage = "Id Protection is not supported";
    } else {
        $low = strtolower($result["privatewhoisstatus"]);
        $idStatus = ($low === "disable" || $low === "disabled") ?
            "disabled" :
            "enabled";
        if (isset($_POST["status"])) {
            $successmessage = "Data saved successfully";
        }
    }
    if (!$idStatus) {
        if (!$errormessage) {
            $errormessage = "Id Protection is not supported.";
        }
    }
    return [
        "templatefile" => "whois",
        "breadcrumb" => [
            "clientarea.php?action=domaindetails&id=" . $params["domainid"] . "&modop=custom&a=whois" => "whois"
        ],
        "vars" => [
            "domain" => $domainName,
            "status" => $idStatus,
            "errormessage" => $errormessage,
            "successmessage" => $successmessage,
        ]
    ];
}

function ibs_additionalfields($params)
{
    $tld = $params["tld"];
    $domainName = ibs_getInputDomain($params);

    /* For whmcs version below 7 has additionaldomain fields at different location*/
    if (file_exists(ROOTDIR . "/includes/additionaldomainfields.php")) {
        include(ROOTDIR . "/includes/additionaldomainfields.php");
    } else {
        include(ROOTDIR . "resources/domains/dist.additionalfields.php");
    }
    include(ROOTDIR . "/modules/registrars/ibs/ibs_additionaldomainfields.php");

    global $additionaldomainfields;
    /* Additional Domain Fields for tld from additionaldimainfields file */
    $additionalfields = $additionaldomainfields["." . $tld];

    if (isset($_POST) && count($_POST) > 0) {
        $whoisData = $_POST;
        unset($whoisData["token"]);
        unset($whoisData["modop"]);
        unset($whoisData["a"]);
        unset($whoisData["id"]);
        foreach ($whoisData as $key => $value) {
            if (strpos($key, "other_") !== false) {
                $newKey = str_replace("other_", "", $key);
                $whoisData[$newKey] = $whoisData[$key];
                unset($whoisData[$key]);
            }
        }
        if ($tld == "nl") {
            $whoisData["registrant_clientip"] = ibs_getClientIp();
            if ($whoisData["registrant_nlterm"] != "") {
                $whoisData["registrant_nlterm"] = "YES";
            } else {
                $whoisData["registrant_nlterm"] = "NO";
            }
        }
        #if ($tld == "us") {
        #    $usDomainPurpose = $whoisData["registrant_uspurpose"];
        #}
        if ($tld == "de") {
            if ($whoisData["registrant_restricted_publication"] == "on") {
                $whoisData["registrant_discloseName"] = $whoisData["registrant_discloseContact"] = $whoisData["registrant_discloseAddress"] = "Yes";
            } else {
                $whoisData["registrant_discloseName"] = $whoisData["registrant_discloseContact"] = $whoisData["registrant_discloseAddress"] = "No";
            }
            unset($whoisData["registrant_restricted_publication"]);
            if ($whoisData["admin_restricted_publication"] == "on") {
                $whoisData["admin_discloseName"] = $whoisData["admin_discloseContact"] = $whoisData["admin_discloseAddress"] = "Yes";
            } else {
                $whoisData["admin_discloseName"] = $whoisData["admin_discloseContact"] = $whoisData["admin_discloseAddress"] = "No";
            }
            unset($whoisData["admin_restricted_publication"]);
            if ($whoisData["technical_restricted_publication"] == "on") {
                $whoisData["technical_discloseName"] = $whoisData["technical_discloseContact"] = $whoisData["technical_discloseAddress"] = "Yes";
            } else {
                $whoisData["technical_discloseName"] = $whoisData["technical_discloseContact"] = $whoisData["technical_discloseAddress"] = "No";
            }
            unset($whoisData["technical_restricted_publication"]);
            if ($whoisData["zone_restricted_publication"] == "on") {
                $whoisData["zone_discloseName"] = $whoisData["zone_discloseContact"] = $whoisData["zone_discloseAddress"] = "Yes";
            } else {
                $whoisData["zone_discloseName"] = $whoisData["zone_discloseContact"] = $whoisData["zone_discloseAddress"] = "No";
            }
            unset($whoisData["zone_restricted_publication"]);
            $whoisData["clientip"] = ibs_getClientip();
        }
        if ($tld == "it") {
            $entityTypes = [
                "1. Italian and foreign natural persons" => 1,
                "2. Companies/one man companies" => 2,
                "3. Freelance workers/professionals" => 3,
                "4. non-profit organizations" => 4,
                "5. public organizations" => 5,
                "6. other subjects" => 6,
                "7. foreigners who match 2 - 6" => 7
            ];
            $whoisData["registrant_dotitentitytype"] = $entityTypes[$whoisData["registrant_dotitentitytype"]];
            if (strlen($whoisData["registrant_dotitnationality"]) > 2) {
                $whoisData["registrant_dotitnationality"] = ibs_getCountryCodeByName($whoisData["registrant_dotitnationality"]);
            }
            if ($whoisData["registrant_itterms"] == "on") {
                $whoisData["registrant_dotitterm1"] = "Yes";
                $whoisData["registrant_dotitterm2"] = "Yes";
                $whoisData["registrant_dotitterm3"] = "Yes";
                $whoisData["registrant_dotitterm4"] = "Yes";
                unset($whoisData["registrant_itterms"]);
            } else {
                $whoisData["registrant_dotitterm1"] = "No";
                $whoisData["registrant_dotitterm2"] = "No";
                $whoisData["registrant_dotitterm3"] = "No";
                $whoisData["registrant_dotitterm4"] = "No";
            }
            if ($whoisData["registrant_dotithidewhois"] == "on" && $whoisData["registrant_dotitentitytype"] == 1) {
                $whoisData["registrant_dotithidewhois"] = "Yes";
            } else {
                $whoisData["registrant_dotithidewhois"] = "No";
            }
            if ($whoisData["admin_dotithidewhois"] == "on") {
                $whoisData["admin_dotithidewhois"] = "Yes";
            } else {
                $whoisData["admin_dotithidewhois"] = "No";
            }
            if ($whoisData["technical_dotithidewhois"] == "on") {
                $whoisData["technical_dotithidewhois"] = "Yes";
            } else {
                $whoisData["technical_dotithidewhois"] = "No";
            }
        }
        $data = array_merge([
            "domain" => $domainName,
            "registrant_clientip" => ibs_getClientIp()
        ], $whoisData);
        $result = ibs_call($params, "Domain/Update", $data);

        # If error, return the error message in the value below
        if ($result["status"] === "FAILURE") {
            $errormessage = $result["message"];
        } else {
            $successmessage = "Data Saved Successfully";
        }
    }

    //Change the name of the tld specific fields
    switch ($tld) {
        case "fr":
        case "re":
        case "pm":
        case "yt":
        case "wf":
            foreach ($additionalfields as $key => &$value) {
                if ($value["Name"] == "Holder Type") {
                    $value["Name"] = "dotfrcontactentitytype";
                }
                if ($value["Name"] == "Birth Date YYYY-MM-DD") {
                    $value["Name"] = "dotfrcontactentitybirthdate";
                }
                if ($value["Name"] == "Birth Country Code") {
                    $value["Name"] = "dotfrcontactentitybirthplacecountrycode";
                }
                if ($value["Name"] == "Birth City") {
                    $value["Name"] = "dotfrcontactentitybirthcity";
                }
                if ($value["Name"] == "Birth Postal code") {
                    $value["Name"] = "dotfrcontactentitybirthplacepostalcode";
                }
                if ($value["Name"] == "Restricted Publication") {
                    $value["Name"] = "dotfrcontactentityrestrictedpublication";
                }
                if ($value["Name"] == "Siren") {
                    $value["Name"] = "dotfrcontactentitysiren";
                }
                if ($value["Name"] == "Trade Mark") {
                    $value["Name"] = "dotfrcontactentitytradeMark";
                }
                if ($value["Name"] == "Waldec") {
                    $value["Name"] = "dotfrcontactentitywaldec";
                }
                if ($value["Name"] == "Date of Association YYYY-MM-DD") {
                    $value["Name"] = "dotfrcontactentitydateofassociation";
                }
                if ($value["Name"] == "Date of Publication YYYY-MM-DD") {
                    $value["Name"] = "dotfrcontactentitydateofpublication";
                }
                if ($value["Name"] == "Announce No") {
                    $value["Name"] = "dotfrcontactentityannounceno";
                }
                if ($value["Name"] == "Page No") {
                    $value["Name"] = "dotfrcontactentitypageno";
                }
                if ($value["Name"] == "Other Legal Status") {
                    $value["Name"] = "dotfrothercontactentity";
                }
                if ($value["Name"] == "VATNO") {
                    $value["Name"] = "dotfrcontactentityvat";
                }
                if ($value["Name"] == "DUNSNO") {
                    $value["Name"] = "dotfrcontactentityduns";
                }
            }
            break;
        case "asia":
            foreach ($additionalfields as $key => &$value) {
                if ($value["Name"] == "Locality") {
                    $value["Name"] = "dotasiacedlocality";
                }
                if ($value["Name"] == "Legal Entity Type") {
                    $value["Name"] = "dotasiacedentity";
                }
                if ($value["Name"] == "Identification Form") {
                    $value["Name"] = "dotasiacedidform";
                }
                if ($value["Name"] == "Identification Number") {
                    $value["Name"] = "dotasiacedidnumber";
                }
                if ($value["Name"] == "Other legal entity type") {
                    $value["Name"] = "dotasiacedentityother";
                }
                if ($value["Name"] == "Other identification form") {
                    $value["Name"] = "dotasiacedidformother";
                }
            }
            break;
        case "us":
            foreach ($additionalfields as $key => &$value) {
                $value["contactType"] = ["registrant"];
                if ($value["Name"] == "Nexus Category") {
                    $value["DisplayName"] = $value["Name"];
                    $value["Name"] = "usnexuscategory";
                }
                if ($value["Name"] == "Nexus Country") {
                    $value["DisplayName"] = $value["Name"];
                    $value["Name"] = "usnexuscountry";
                }
                if ($value["Name"] == "Application Purpose") {
                    $value["DisplayName"] = $value["Name"];
                    $value["Name"] = "uspurpose";
                }
            }
            break;
        case "de":
            foreach ($additionalfields as $key => &$value) {
                if (strtolower($value["Name"]) == "tosagree") {
                    $value["contactType"] = ["other"];
                }
                if (strtolower($value["Name"]) == "role") {
                    $value["contactType"] = ["registrant"];
                }
                if (strtolower($value["Name"]) == "restricted publication") {
                    $value["contactType"] = ["registrant", "admin"];
                }
            }
            array_unshift($additionalfields, [
                "Name" => "role",
                "DisplayName" => "Role",
                "Type" => "dropdown",
                "Options" => "person|Person",
                "contactType" => ["admin"]
            ]);
            array_unshift($additionalfields, [
                "Name" => "role",
                "DisplayName" => "Role",
                "Type" => "dropdown",
                "Options" => "person|Person,role|Role",
                "contactType" => ["technical"]
            ]);
            array_unshift($additionalfields, [
                "Name" => "role",
                "DisplayName" => "Role",
                "Type" => "dropdown",
                "Options" => "person|Person,role|Role",
                "contactType" => ["zone"]
            ]);
            break;
        case "nl":
            foreach ($additionalfields as $key => &$value) {
                if (strtolower($value["Name"]) == "nlterm") {
                    $value["contactType"] = ["registrant"];
                }
            }
            break;
        case "it":
            foreach ($additionalfields as $key => &$value) {
                if ($value["Name"] == "Legal Entity Type") {
                    $value["Name"] = "dotitentitytype";
                    $value["contactType"] = ["registrant"];
                }
                if ($value["Name"] == "Nationality") {
                    $value["Name"] = "dotitnationality";
                    $value["contactType"] = ["registrant"];
                }
                if ($value["Name"] == "VATTAXPassportIDNumber") {
                    $value["Name"] = "dotitregcode";
                    $value["contactType"] = ["registrant"];
                }
                if ($value["Name"] == "Hide data in public WHOIS") {
                    $value["Name"] = "dotithidewhois";
                }
                if ($value["Name"] == "itterms") {
                    $value["contactType"] = ["registrant"];
                }
            }
            break;
        case "eu":
            foreach ($additionalfields as $key => &$value) {
                if ($value["Name"] == "Language") {
                    $value["Name"] = "language";
                }
            }
            break;
    }
    //Get Domain information
    $result = ibs_call($params, "Domain/Info", [
        "domain" => $domainName
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        $errormessage = $result["message"];
    } else {
        //assign additional fields to new array to display it to the users
        $contactIndex = 0;
        $contacts = [];
        $contactData = [];
        foreach ($result as $resultKey => $resultValue) {
            foreach ($additionalfields as $extrakey => $extravalue) {
                if (strpos($resultKey, "contacts_") !== false) {
                    $newKey = str_replace("contacts_", "", $resultKey);
                    $newKey = explode("_", $newKey);
                    if (strtolower($extravalue["Name"]) == strtolower($newKey[1])) {
                        $contactData[$newKey[0]][$newKey[1]] = $result[$resultKey];
                        if (!in_array($newKey[0], $contacts)) {
                            $contacts[$contactIndex] = $newKey[0];
                            $contactIndex++;
                        }
                    }
                }
            }
        }
        //tld specific modifications to additional fields values obtained from api
        if ($tld == "de") {
            if (strtolower($result["contacts_registrant_disclosename"]) == "yes" || strtolower($result["contacts_registrant_disclosecontact"]) == "yes" || strtolower($result["contacts_registrant_discloseaddress"]) == "yes") {
                $contactData["registrant"]["restricted publication"] = "Yes";
            } else {
                $contactData["registrant"]["restricted publication"] = "No";
            }
            if (strtolower($result["contacts_admin_disclosename"]) == "yes" || strtolower($result["contacts_admin_disclosecontact"]) == "yes" || strtolower($result["contacts_admin_discloseaddress"]) == "yes") {
                $contactData["admin"]["restricted publication"] = "Yes";
            } else {
                $contactData["admin"]["restricted publication"] = "No";
            }
            $contacts[$contactIndex] = "other";
            $contactData["other"]["tosagree"] = "NO";
        }
    }
    return [
        "templatefile" => "additionalfields",
        "breadcrumb" => [
            "clientarea.php?action=domaindetails&id=" . $params["domainid"] . "&modop=custom&a=additionalfields" => "additionalfields"
        ],
        "vars" => [
            "additionalfields" => $additionalfields,
            "domainName" => $domainName,
            "whoisContacts" => $contacts,
            "additionalFieldValue" => $contactData,
            "errormessage" => $errormessage,
            "successmessage" => $successmessage
        ]
    ];
}

function ibs_ClientAreaCustomButtonArray($params)
{
    $tld = $params["tld"];
    $domainName = ibs_getInputDomain($params);

    $buttonArray = [];

    // fetch TLD Info
    $result = ibs_call($params, "Domain/Tldinfo", [
        "tld" => $params["tld"]
    ]);
    // DNSSEC support
    if (
        $params["DNSSEC"] === "on"
        && $result["status"] === "SUCCESS"
        && $result["feature_dnssec"] === "YES"
    ) {
        $buttonArray["DNSSEC Management"] = "dnssec";
    }
    // Private WHOIS Support
    if ($result["status"] === "SUCCESS" && $result["feature_privatewhois"] === "YES") {
        $buttonArray["Manage Id Protection"] = "getwhois";
    }
    // For Email Verification
    $data = ibs_getEmailVerificationDetails($params);
    if (strtoupper($data["currentstatus"]) !== "VERIFIED") {
        $buttonArray["Verify Email"] = "verify";
        #$buttonArray[""] = "send";
    }

    $addflds = new \WHMCS\Domains\AdditionalFields();
    $fields = $addflds
        ->setDomain($domainName)
        ->setDomainType("register")
        ->getAsNameValueArray();

    if (count($fields) > 0 && $tld !== "tel") {
        $notTmch = false;
        foreach ($fields as $key => $value) {
            if (!strstr($key, "tmch")) {
                $notTmch = true;
            }
        }
        if ($notTmch) {
            $buttonArray["Domain Configurations"] = "additionalfields";
        }
    }

    return array_merge($buttonArray, [
        "URL Forwarding" => "domainurlforwarding"
    ]);
}

function ibs_debugLog($log)
{
    // $module The name of the module
    // $action The name of the action being performed
    // $requestString The input parameters for the API call
    // $responseData The response data from the API call
    // $processedData The resulting data after any post processing (eg. json decode, xml decode, etc...)
    // $replaceVars An array of strings for replacement
    $ep = preg_replace("/^http(s)?:\/\/[^\/]+\//", "", $log["action"]);
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);
    $action = $trace[0];
    do {
        $t = array_shift($trace);
        if ($t !== null && preg_match("/^ibs_(.+)$/i", $t["function"], $m) && $m[1] !== "call") {
            $action = $m[1];
        }
    } while (!empty($trace));

    $reqStr = "";
    foreach ($log["requestParam"] as $key => $val) {
        $reqStr .= $key . "=" . $val . "\n";
    }

    $request = $ep . " @ " . $log["environment"] . " Environment \n\n" . $reqStr;
    logModuleCall(
        "Internet.bs Registrar Module",
        $action,
        $request,
        $log["responseParam"],
        "",
        $log["replace"]
    );
}

/**
 * Undocumented function to validate user inputs in getConfigArray's form - only invoked if configuration settings are submitted
 * @link https://www.whmcs.com/members/viewticket.php?tid=ESD-183344&c=wjZ1LjOs #ESD-183344
 * @param array $params common module parameters
 * @throws Exception if estabilishing the API connection failed
 */
function ibs_config_validate($params)
{
    $system = ($params["TestMode"] === "on") ? "TEST" : "LIVE";
    $result = ibs_call($params, "Account/Balance/Get");

    if ($result["status"] === "FAILURE") {
        $error = $result["message"];
        $url = "https://internetbs.net/en/contact.html";
        throw new \Exception(
            <<<HTML
                <h2>Connecting to the {$system} Environment failed. <small>({$error})</small></h2>
                <p>Read <a href="{$url}" target="_blank" class="alert-link" style="text-decoration:underline">here</a> for possible reasons.
HTML
        );
    } else {
        unset($_SESSION["ConfigurationWarning"]);
    }
}

/**
 * Return Registrar Module Configuration Settings
 * NOTE: for some reason, WHMCS is invoking the function twice
 * @param array $params Standard WHMCS Input
 * @return array
 */
function ibs_getConfigArray($params)
{
    $configarray = [
        "FriendlyName" => [
            "Type" => "System",
            "Value" => "\0 Internet.bs v" . IBS_MODULE_VERSION
        ],
        "Description" => [
            "Type" => "System",
            "Value" => "The Official Internet.bs Registrar Module. Get an account here: <a style=\"text-decoration:underline;\" target=\"blank_\" href=\"https://internetbs.net/newaccount.html\">https://internetbs.net/newaccount.html</a>"
        ],
        "Username" => [
            "Type" => "text",
            "Size" => "50",
            "Description" => "Enter your Internet.bs ApiKey here"
        ],
        "Password" => [
            "Type" => "password",
            "Size" => "50",
            "Description" => "Enter your Internet.bs password here"
        ],
        "TestMode" => [
            "Type" => "yesno",
            "Description" => "Check this checkbox if you want to connect to the test server"
        ],
        "HideWhoisData" => [
            "Type" => "yesno",
            "Description" => "Tick this box if you want to hide the information in the public whois for Admin/Billing/Technical contacts (.it)"
        ],
        "SyncNextDueDate" => [
            "Type" => "yesno",
            "Description" => "Tick this box if you want the expiry date sync script to update both expiry and next due dates (cron must be configured). If left unchecked it will only update the domain expiration date."
        ],
        "RenewAfterTransfer" => [
            "Type" => "yesno",
            "Description" => "Tick this box if you want to add renewal after transferring .de and .nl domain"
        ],
        "DNSSEC" => [
            "FriendlyName" => "Allow DNSSEC",
            "Type" => "yesno",
            "Default" => false,
            "Description" => "Enables DNSSEC configuration on Client Area"
        ]
    ];

    $results = localAPI("GetSupportDepartments", []);
    $departments = ["-"];
    if ($results["result"] === "success" && $results["totalresults"] > 0) {
        foreach ($results["departments"]["department"] as $dept) {
            $departments[] = $dept["name"] . " (" . $dept["id"] . ")";
        }
        $configarray["NotifyOnError"] = [
            "FriendlyName" => "Notify department ",
            "Type" => "dropdown",
            "Description" => "Please chose a department, if you want to have a ticket opened in case of errors returned by our API.",
            "Options" => implode(",", $departments)
        ];
    }

    global $CONFIG;
    $parts = parse_url($CONFIG["SystemURL"]);
    $ip = gethostbyname($parts["host"]);

    $configarray[""] = [
        "Type" => "system",
        "Description" => (<<<HTML
                <div class="alert alert-info" style="font-size:medium;margin-bottom:0px;">
                    Click on Save for testing your connection to the configured IBS Backend System. Only in case it fails, an error will be shown.<br/><b>Your Server IP Address</b>: {$ip}
                </div>
HTML
        )
    ];

    return $configarray;
}

/**
 * parse result
 * format: ["name" => value]
 *
 * @param string $data
 * @return array
 */
function ibs_parseResult($data)
{
    if (preg_match("/403 Forbidden/", $data)) {
        return [
            "status" => "FAILURE",
            "message" => "403 Forbidden"
        ];
    }

    $result = [];
    $arr = explode("\n", $data);
    foreach ($arr as $str) {
        list($varName, $value) = explode("=", $str, 2);
        $varName = trim($varName);
        $value = trim($value);
        $result[$varName] = $value;
    }
    return $result;
}

/**
 * Expiration date sync
 * @param $parameters
 */
function ibs_Sync($params)
{
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/Info", [
        "domain" => $domainName
    ]);

    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }

    //success
    $values = [];
    if ($result["domainstatus"] === "EXPIRED") {
        $values["expired"] = true;
    } elseif ($result["domainstatus"] !== "PENDING TRANSFER") {
        $values["active"] = true;
    }
    if (isset($result["paiduntil"]) && $result["paiduntil"] !== "n/a") {
        $values["expirydate"] = str_replace("/", "-", $result["paiduntil"]);
    } elseif (isset($result["expirationdate"]) && $result["expirationdate"] !== "n/a") {
        $values["expirydate"] = str_replace("/", "-", $result["expirationdate"]);
    }

    return $values;
}

/**
 * Expiration date sync
 * @param $parameters
 */
function ibs_TransferSync($params)
{
    return ibs_Sync($params);
}

/**
 * gets list of nameservers for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetNameservers($params)
{
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/Info", [
        "domain" => $domainName
    ]);
    if ($result["status"] === "FAILURE") {
        return [];
    }

    // possible number of hosts exists
    $values = [];
    $i = 0;
    while (isset($result["nameserver_" . $i])) {
        $values["ns" . ($i + 1)] = $result["nameserver_" . $i];
        ++$i;
    }

    return $values;
}

/**
 * attach nameserver to a domain by Domain/Update command
 *
 * @param array $params
 * @return array
 */
function ibs_SaveNameservers($params)
{
    # code to save the nameservers
    $paramsData = (isset($params["original"])) ?
        $params["original"] :
        $params;

    $nslist = [];
    for ($i = 1; $i <= 5; $i++) {
        if (isset($paramsData["ns$i"])) {
            if (isset($paramsData["ns" . $i . "_ip"]) && strlen($paramsData["ns" . $i . "_ip"])) {
                $paramsData["ns$i"] .= " " . $paramsData["ns" . $i . "_ip"];
            }
            array_push($nslist, $paramsData["ns$i"]);
        }
    }

    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/Update", [
        "domain" => $domainName,
        "ns_list" => trim(implode(",", $nslist), ",")
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }
    return ["success" => true];
}

/**
 * gets registrar lock status of a domain
 *
 * @param array $params
 * @return string
 */
function ibs_GetRegistrarLock($params)
{
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/RegistrarLock/Status", [
        "domain" => $domainName
    ]);

    if ($result["status"] !== "SUCCESS") { // FAILURE ?!
        return [
            "error" => $result["message"]
        ];
    }
    if (strtolower($result["registrar_lock_status"]) === "locked") {
        return "locked";
    }
    return "unlocked";
}

/**
 * enable/disable registrar lock for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_SaveRegistrarLock($params)
{
    # code to save the registrar lock
    $domainName = ibs_getInputDomain($params);
    // if lockenabled is set, we need to run lock enable command
    if (strtolower($params["lockenabled"]) === "locked") {
        $status = "Enable"; // locked
    } else {
        $status = "Disable"; // unlocked
    }

    $result = ibs_call($params, "Domain/RegistrarLock/" . $status, [
        "domain" => $domainName
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }
    return [
        "success" => true
    ];
}

/**
 * This function is called to toggle Id protection status
 * @param $params
 */

function ibs_IDProtectToggle($params)
{
    # code to get the WHOIS status
    $domainName = ibs_getInputDomain($params);

    //if protectenable is set, we need to enable whois
    $status = ($params["protectenable"]) ? "enable" : "disable";

    $result = ibs_call($params, "Domain/PrivateWhois/" . $status, [
        "domain" => $domainName
    ]);

    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }
    return [
        "success" => true
    ];
}

/**
 * gets email forwarding rules list of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetEmailForwarding($params)
{
    # code to get email forwarding - the result should be an array of prefixes and forward to emails (max 10)
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/EmailForward/List", [
        "domain" => $domainName
    ]);

    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }

    $values = [];
    $totalRules = $result["total_rules"];
    for ($i = 1; $i <= $totalRules; $i++) {
        // prefix is the first part before @ at email addrss
        list($prefix, $domainName) = explode("@", $result["rule_" . $i . "_source"]);
        if (empty($prefix)) {
            $prefix = "@";
        }
        $values[$i]["prefix"] = $prefix;
        $values[$i]["forwardto"] = $result["rule_" . $i . "_destination"];
    }
    return $values;
}

/**
 * saves email forwarding rules of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_SaveEmailForwarding($params)
{
    #code to save email forwarders
    $domainName = ibs_getInputDomain($params);

    $errorMessages = "";
    $rules = ibs_GetEmailForwarding($params);
    if (is_array($rules)) {
        foreach ($rules as $rule) {
            $source = trim($rule["prefix"], "@ ") . "@" . $domainName;
            ibs_call($params, "Domain/EmailForward/Remove", [
                "source" => urlencode($source)
            ]);
        }
    }

    if (!isset($params["original"]["prefix"])) {
        $prefix = $params["prefix"];
    } else {
        $prefix = $params["original"]["prefix"];
    }

    if (!isset($params["original"]["forwardto"])) {
        $forwardto = $params["forwardto"];
    } else {
        $forwardto = $params["original"]["forwardto"];
    }

    foreach ($prefix as $key => $value) {
        $to = $forwardto[$key];
        if (trim($to) === "") {
            continue;
        }
        $from = $prefix[$key];

        // try to add rule
        $result = ibs_call($params, "Domain/EmailForward/Add", [
            "source" => urlencode(trim($from, "@ ") . "@" . $domainName),
            "destination" => urlencode($to)
        ]);

        if ($result["status"] === "FAILURE") {
            $errorMessages .= $result["message"];
        }
    }
    // error occurs
    if (strlen($errorMessages)) {
        return [
            "error" => $errorMessages
        ];
    }
    return [];
}

/**
 * gets DNS Record list of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetDNS($params)
{
    # code here to get the current DNS settings - the result should be an array of hostname, record type, and address
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/DnsRecord/List", [
        "domain" => $domainName
    ]);

    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }

    $totalRecords = 0;
    if (is_array($result)) {
        $keys = array_keys($result);
        foreach ($keys as $key) {
            if (strpos($key, "records_") === 0) {
                $recNo = substr($key, 8);
                $recNo = substr($recNo, 0, strpos($recNo, "_"));
                if ($recNo > $totalRecords) {
                    $totalRecords = $recNo;
                }
            }
        }
    }

    $hostrecords = [];
    for ($i = 0; $i <= $totalRecords; $i++) {
        if (
            !isset($result["records_" . $i . "_type"])
            || !preg_match("/^(a|mx|cname|txt|aaaa|txt)$/i", trim($result["records_" . $i . "_type"]))
        ) {
            continue;
        }

        if (isset($result["records_" . $i . "_name"])) {
            $recordHostname = $result["records_" . $i . "_name"];
            $dParts = explode(".", $domainName);
            $hParts = explode(".", $recordHostname);
            $recordHostname = "";
            for ($j = 0; $j < (count($hParts) - count($dParts)); $j++) {
                $recordHostname .= (empty($recordHostname) ? "" : ".") . $hParts[$j];
            }
        }
        if (isset($result["records_" . $i . "_value"])) {
            $recordAddress = $result["records_" . $i . "_value"];
        }
        if (isset($result["records_" . $i . "_name"])) {
            $hostrecords[] = [
                "hostname" => $recordHostname,
                "type" => trim($result["records_" . $i . "_type"]),
                "address" => htmlspecialchars($recordAddress),
                "priority" => $result["records_" . $i . "_priority"]
            ];
        }
    }

    $result = ibs_call($params, "Domain/UrlForward/List", [
        "domain" => $domainName
    ]);
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }

    $totalRecords = (int)$result["total_rules"];
    for ($i = 1; $i <= $totalRecords; $i++) {
        $recordType = "";
        if (isset($result["rule_" . $i . "_isframed"])) {
            $recordType = trim($result["rule_" . $i . "_isframed"]) === "YES" ? "FRAME" : "URL";
        }
        if (isset($result["rule_" . $i . "_source"])) {
            $recordHostname = $result["rule_" . $i . "_source"];
            $dParts = explode(".", $domainName);
            $hParts = explode(".", $recordHostname);
            $recordHostname = "";
            for ($j = 0; $j < (count($hParts) - count($dParts)); $j++) {
                $recordHostname .= (empty($recordHostname) ? "" : ".") . $hParts[$j];
            }
        }
        if (isset($result["rule_" . $i . "_destination"])) {
            $recordAddress = $result["rule_" . $i . "_destination"];
        }
        if (isset($result["rule_" . $i . "_source"])) {
            $hostrecords[] = [
                "hostname" => $recordHostname,
                "type" => $recordType,
                "address" => htmlspecialchars($recordAddress)
            ];
        }
    }

    return $hostrecords;
}

/**
 * saves dns records for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_SaveDNS($params)
{
    $domainName = ibs_getInputDomain($params);

    $errorMessages = "";
    $recs = ibs_GetDNS($params);
    if (is_array($recs)) {
        foreach ($recs as $r) {
            $source = trim($r["hostname"] . ".$domainName", ". ");
            if (preg_match("/^(FRAME|URL)$/", $r["type"])) {
                ibs_call($params, "Domain/UrlForward/Remove", [
                    "source" => $source
                ]);
                continue;
            }
            ibs_call($params, "Domain/DnsRecord/Remove", [
                "FullRecordName" => $source,
                "type" => $r["type"]
            ]);
        }
    }

    # Loop through the submitted records
    if (!isset($params["original"]["dnsrecords"])) {
        $dnsRecords = $params["dnsrecords"];
    } else {
        $dnsRecords = $params["original"]["dnsrecords"];
    }

    foreach ($dnsRecords as $key => $values) {
        $hostname = $values["hostname"];
        $type = $values["type"];
        $address = $values["address"];
        if (trim($hostname) === "" && trim($address) == "") {
            continue;
        }

        # code to update the record
        if (
            ($hostname != $domainName)
            && strpos($hostname, "." . $domainName) === false
        ) {
            $hostname = $hostname . "." . $domainName;
        }
        if (!preg_match("/^(URL|FRAME)$/", $type)) {
            $result = ibs_call($params, "Domain/DnsRecord/Add", [
                "fullrecordname" => trim($hostname, ". "),
                "type" => $type,
                "value" => $address,
                "priority" => intval($values["priority"])
            ]);
        } else {
            $result = ibs_call($params, "Domain/UrlForward/Add", [
                "source" => trim($hostname, ". "),
                "isFramed" => ($type === "FRAME") ? "YES" : "NO",
                "Destination" => $address
            ]);
        }

        if ($result["status"] === "FAILURE") {
            $errorMessages .= $result["message"];
        }
    }

    # If error, return the error message in the value below
    if (strlen($errorMessages)) {
        return [
            "error" => $errorMessages
        ];
    }

    return [
        "success" => "success"
    ];
}

/**
 * registers a domain
 *
 * @param array $params
 * @return array
 */
function ibs_RegisterDomain($params)
{
    $hideWhoisData = (isset($params["HideWhoisData"]) && ("on" == strtolower($params["HideWhoisData"]))) ? "YES" : "NO";
    $premiumDomainsEnabled = (bool)$params["premiumEnabled"];
    $premiumDomainsCost = $params["premiumCost"]; //this is USD because we only get the price in USD

    $tld = $params["tld"];

    $domainName = ibs_getInputDomain($params);
    $regperiod = (int)$params["regperiod"];

    # Registrant Details
    $RegistrantFirstName = $params["firstname"];
    $RegistrantLastName = $params["lastname"];
    $RegistrantCompany = trim($params["companyname"]);
    $RegistrantAddress1 = $params["address1"];
    $RegistrantAddress2 = $params["address2"];
    $RegistrantCity = $params["city"];
    $RegistrantStateProvince = $params["state"];
    $RegistrantPostalCode = $params["postcode"];
    $RegistrantCountry = $params["country"];
    $RegistrantEmailAddress = $params["email"];
    $RegistrantPhone = ibs_reformatPhone($params["phonenumber"], $params["country"]);
    # Admin Details
    $AdminFirstName = $params["adminfirstname"];
    $AdminLastName = $params["adminlastname"];
    $AdminCompany = trim($params["admincompanyname"]);
    $AdminAddress1 = $params["adminaddress1"];
    $AdminAddress2 = $params["adminaddress2"];
    $AdminCity = $params["admincity"];
    $AdminStateProvince = $params["adminstate"];
    $AdminPostalCode = $params["adminpostcode"];
    $AdminCountry = $params["admincountry"];
    $AdminEmailAddress = $params["adminemail"];
    $AdminPhone = ibs_reformatPhone($params["adminphonenumber"], $params["admincountry"]);
    #get trade details if assoiciated
    $domainid = $params["domainid"];

    // --- Get TMCH Data (probably deprecated)
    $results = DB::table("tbldomainsadditionalfields")
        ->select("name", "value")
        ->where("domainid", $domainid)
        ->get();
    if (!empty($results)) {
        foreach ($results as $row) {
            if (is_object($row)) { // laravel compatibility (whmcs 7 vs whmcs 8)
                $row = json_decode(json_encode($results), true);
            }
            if ($row["name"] === "tmchid") {
                $tmchId = $row["value"];
            } elseif ($row["name"] === "tmchnotafter") {
                $tmchNotAfter = $row["value"];
            } elseif ($row["name"] === "tmchaccepteddate") {
                $tmchAcceptedDate = $row["value"];
            }
        }
    }

    # Put your code to register domain here
    $nslist = [];
    if (isset($params["original"])) {
        $paramsData = $params["original"];
    } else {
        $paramsData = $params;
    }
    for ($i = 1; $i <= 5; $i++) {
        if (isset($paramsData["ns$i"])) {
            array_push($nslist, $paramsData["ns$i"]);
        }
    }

    $data = [
        "domain" => $domainName,

        // registrant contact data
        "registrant_firstname" => $RegistrantFirstName,
        "registrant_lastname" => $RegistrantLastName,
        "registrant_street" => $RegistrantAddress1,
        "registrant_street2" => $RegistrantAddress2,
        "registrant_city" => $RegistrantCity,
        "registrant_state" => $RegistrantStateProvince,
        "registrant_countrycode" => $RegistrantCountry,
        "registrant_postalcode" => $RegistrantPostalCode,
        "registrant_email" => $RegistrantEmailAddress,
        "registrant_phonenumber" => $RegistrantPhone,

        // technical contact data
        "technical_firstname" => $AdminFirstName,
        "technical_lastname" => $AdminLastName,
        "technical_street" => $AdminAddress1,
        "technical_street2" => $AdminAddress2,
        "technical_city" => $AdminCity,
        "technical_state" => $AdminStateProvince,
        "technical_countrycode" => $AdminCountry,
        "technical_postalcode" => $AdminPostalCode,
        "technical_email" => $AdminEmailAddress,
        "technical_phonenumber" => $AdminPhone,

        // admin contact data
        "admin_firstname" => $AdminFirstName,
        "admin_lastname" => $AdminLastName,
        "admin_street" => $AdminAddress1,
        "admin_street2" => $AdminAddress2,
        "admin_city" => $AdminCity,
        "admin_state" => $AdminStateProvince,
        "admin_countrycode" => $AdminCountry,
        "admin_postalcode" => $AdminPostalCode,
        "admin_email" => $AdminEmailAddress,
        "admin_phonenumber" => $AdminPhone,

        // billing contact data
        "billing_firstname" => $AdminFirstName,
        "billing_lastname" => $AdminLastName,
        "billing_street" => $AdminAddress1,
        "billing_street2" => $AdminAddress2,
        "billing_city" => $AdminCity,
        "billing_state" => $AdminStateProvince,
        "billing_countrycode" => $AdminCountry,
        "billing_postalcode" => $AdminPostalCode,
        "billing_email" => $AdminEmailAddress,
        "billing_phonenumber" => $AdminPhone
    ];

    if ($premiumDomainsEnabled && $premiumDomainsCost) {
        $data["confirmpricecurrency"] = "USD";
        $data["confirmpriceamount"] = $premiumDomainsCost;
    }

    if (isset($tmchId) && isset($tmchNotAfter) && isset($tmchAcceptedDate)) {
        $data["tmchid"] = $tmchId;
        $data["tmchnotafter"] = $tmchNotAfter;
        $data["tmchaccepteddate"] = $tmchAcceptedDate;
    }
    if (!empty($RegistrantCompany)) {
        $data["registrant_Organization"] = $RegistrantCompany;
    }
    if (!empty($AdminCompany)) {
        $data["technical_Organization"] = $AdminCompany;
        $data["admin_Organization"] = $AdminCompany;
        $data["billing_Organization"] = $AdminCompany;
    }
    // ns_list is optional
    if (count($nslist)) {
        $data["ns_list"] = trim(implode(",", $nslist), ",");
    }
    if ($params["idprotection"]) {
        $data["privateWhois"] = "FULL";
    }

    $extarr = explode(".", $tld);
    $ext = array_pop($extarr);

    if ($tld == "eu" || $tld == "be" || $ext == "uk") {
        $data["registrant_language"] = isset($params["additionalfields"]["Language"]) ? $params["additionalfields"]["Language"] : "en";
    }

    if ($tld === "se") {
        // Registrant ID Number
        if (isset($params["additionalfields"]["seregistrantidnumber"])) {
            $data["registrant_idnumber"] = $params["additionalfields"]["seregistrantidnumber"];
        }
        // Registrant VAT ID
        if (isset($params["additionalfields"]["seregistrantvatid"])) {
            $data["registrant_vat"] = $params["additionalfields"]["seregistrantvatid"];
        }
    }

    if ($tld === "es") {
        if (isset($params["additionalfields"]["estldidformtype"])) {
            $data["registrant_tipo-identificacion"] = $params["additionalfields"]["estldidformtype"];
            $data["admin_tipo-identificacion"] = $params["additionalfields"]["estldidformtype"];
            $data["technical_tipo-identificacion"] = $params["additionalfields"]["estldidformtype"];
            $data["billing_tipo-identificacion"] = $params["additionalfields"]["estldidformtype"];
        }
        if (isset($params["additionalfields"]["estldidformnum"])) {
            $data["registrant_identificacion"] = $params["additionalfields"]["estldidformnum"];
            $data["admin_identificacion"] = $params["additionalfields"]["estldidformnum"];
            $data["technical_identificacion"] = $params["additionalfields"]["estldidformnum"];
            $data["billing_identificacion"] = $params["additionalfields"]["estldidformnum"];
        }
        if (isset($params["additionalfields"]["estldlegalform"])) {
            $data["registrant_legalform"] = $params["additionalfields"]["estldlegalform"];
            $data["admin_legalform"] = $params["additionalfields"]["estldlegalform"];
            $data["technical_legalform"] = $params["additionalfields"]["estldlegalform"];
            $data["billing_legalform"] = $params["additionalfields"]["estldlegalform"];
        }
    }

    if ($tld === "ca") {
        // Legal Entity Type
        if (isset($params["additionalfields"]["calegalentitytype"])) {
            $data["registrant_entitytype"] = $params["additionalfields"]["calegalentitytype"];
        }
        // Trademark Number
        if (isset($params["additionalfields"]["catrademarknumber"])) {
            $data["registrant_trademarknumber"] = $params["additionalfields"]["catrademarknumber"];
        }
        // Domain Name is Trademark?
        if (isset($params["additionalfields"]["catrademark"])) {
            $data["registrant_trademark"] = $params["additionalfields"]["catrademark"];
        }
    }

    if ($tld === "com.au") {
        // Legal Entity Type
        if (isset($params["additionalfields"]["comaulegalentitytype"])) {
            $data["registrant_eligibilitytype"] = $params["additionalfields"]["comaulegalentitytype"];
        }
        // Relation Type
        if (isset($params["additionalfields"]["comaurelationtype"])) {
            $data["registrant_relationtypes"] = $params["additionalfields"]["comaurelationtype"];
        }
        // Company / Trademark ID
        if (isset($params["additionalfields"]["comaucompanynumber"])) {
            $data["registrant_companynumber"] = $params["additionalfields"]["comaucompanynumber"];
        }
        // Trademark Owner Name
        if (isset($params["additionalfields"]["comautrademarkownername"])) {
            $data["registrant_trademarkname"] = $params["additionalfields"]["comautrademarkownername"];
        }
    }

    if ($tld == "eu") {
        $europeanLanguages = ["cs", "da", "de", "el", "en", "es", "et", "fi", "fr", "hu", "it", "lt", "lv", "mt", "nl", "pl", "pt", "sk", "sl", "sv", "ro", "bg", "ga"];
        if (!in_array($data["registrant_language"], $europeanLanguages)) {
            $data["registrant_language"] = "en";
        }

        $europianCountries = ["AX", "AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "GF", "DE", "GI", "GR", "GP", "HU", "IS", "IE", "IT", "LV", "LI", "LT", "LU", "MT", "MQ", "NL", "NO", "PL", "PT", "RE", "RO", "SK", "SI", "ES", "SE", "GB"];
        if (!in_array($RegistrantCountry, $europianCountries)) {
            //let the registration fail if the registrant is not from EU
            $values["error"] = "Registration failed: Registrant must be from the European Union";
        }
        // $data["registrant_countrycode"] = $RegistrantCountry; ... already initially set?!
    }

    if ($tld == "be") {
        if (!in_array($data["registrant_language"], ["en", "fr", "nl"])) {
            $data["registrant_language"] = "en";
        }

        // Same as for .EU
        if (!in_array($RegistrantCountry, ["AF", "AX", "AL", "DZ", "AS", "AD", "AO", "AI", "AQ", "AG", "AR", "AM", "AW", "AU", "AT", "AZ", "BS", "BH", "BD", "BB", "BY", "BE", "BZ", "BJ", "BM", "BT", "BO", "BA", "BW", "BV", "BR", "IO", "VG", "BN", "BG", "BF", "BI", "KH", "CM", "CA", "CV", "KY", "CF", "TD", "CL", "CN", "CX", "CC", "CO", "KM", "CG", "CK", "CR", "HR", "CU", "CY", "CZ", "CD", "DK", "DJ", "DM", "DO", "TL", "EC", "EG", "SV", "GQ", "ER", "EE", "ET", "FK", "FO", "FM", "FJ", "FI", "FR", "GF", "PF", "TF", "GA", "GM", "GE", "DE", "GH", "GI", "GR", "GL", "GD", "GP", "GU", "GT", "GN", "GW", "GY", "HT", "HM", "HN", "HK", "HU", "IS", "IN", "ID", "IR", "IQ", "IE", "IM", "IL", "IT", "CI", "JM", "JP", "JO", "KZ", "KE", "KI", "KW", "KG", "LA", "LV", "LB", "LS", "LR", "LY", "LI", "LT", "LU", "MO", "MK", "MG", "MW", "MY", "MV", "ML", "MT", "MH", "MQ", "MR", "MU", "YT", "MX", "MD", "MC", "MN", "ME", "MS", "MA", "MZ", "MM", "NA", "NR", "NP", "NL", "AN", "NC", "NZ", "NI", "NE", "NG", "NU", "NF", "KP", "MP", "NO", "OM", "PK", "PW", "PS", "PA", "PG", "PY", "PE", "PH", "PN", "PL", "PT", "PR", "QA", "RE", "RO", "RU", "RW", "SH", "KN", "LC", "PM", "VC", "WS", "SM", "ST", "SA", "SN", "RS", "SC", "SL", "SG", "SK", "SI", "SB", "SO", "ZA", "GS", "KR", "ES", "LK", "SD", "SR", "SJ", "SZ", "SE", "CH", "SY", "TW", "TJ", "TZ", "TH", "TG", "TK", "TO", "TT", "TN", "TR", "TM", "TC", "TV", "VI", "UG", "UA", "AE", "GB", "US", "UM", "UY", "UZ", "VU", "VA", "VE", "VN", "WF", "EH", "YE", "ZM", "ZW"])) {
            //let the registration fail if the registrant is not from EU
            $values["error"] = "Registration failed: Registrant must be from the European Union";
        }
        // $data["registrant_countrycode"] = $RegistrantCountry; ... already initially set?!
    }

    // ADDED FOR .DE //

    if ($tld == "de") {
        if ($params["additionalfields"]["role"] == "ORG") {
            $data["registrant_role"] = $params["additionalfields"]["role"];
            $data["admin_role"] = "Person";
            $data["technical_role"] = "Role";
            $data["zone_role"] = "Role";
        } else {
            $data["registrant_role"] = $params["additionalfields"]["role"];
            $data["admin_role"] = "Person";
            $data["technical_role"] = "Person";
            $data["zone_role"] = "Person";
        }
        if ($params["additionalfields"]["tosAgree"] != "") {
            $data["tosAgree"] = "YES";
        } else {
            $data["tosAgree"] = "NO";
        }
        $data["registrant_sip"] = @$params["additionalfields"]["sip"];

        $data["clientip"] = ibs_getClientIp();
        if ($params["additionalfields"]["Restricted Publication"] != "") {
            $data["registrant_discloseName"] = "YES";
            $data["registrant_discloseContact"] = "YES";
            $data["registrant_discloseAddress"] = "YES";
        } else {
            $data["registrant_discloseName"] = "NO";
            $data["registrant_discloseContact"] = "NO";
            $data["registrant_discloseAddress"] = "NO";
        }

        $data["zone_firstname"] = $AdminFirstName;
        $data["zone_lastname"] = $AdminLastName;
        $data["zone_email"] = $AdminEmailAddress;
        $data["zone_phonenumber"] = ibs_reformatPhone($params["phonenumber"], $params["country"]);
        $data["zone_postalcode"] = $AdminPostalCode;
        $data["zone_city"] = $AdminCity;
        $data["zone_street"] = $AdminAddress1;
        //$data["zone_countrycode"] = "DE";
        //we should not explicity set admin country as DE
        $data["zone_countrycode"] = $AdminCountry;

        $data["technical_fax"] = @$params["additionalfields"]["fax"];
        $data["zone_fax"] = @$params["additionalfields"]["fax"];

        //removing state field for .de
        //unset($data["registrant_state"]);
        //unset($data["admin_state"]);
        //unset($data["technical_state"]);
        //unset($data["billing_state"]);
    }
    // END OF .DE //

    // ADDED FOR .NL //

    if ($tld == "nl") {
        if ($params["additionalfields"]["nlTerm"] != "") {
            $data["registrant_nlTerm"] = "YES";
        } else {
            $data["registrant_nlTerm"] = "NO";
        }
        $data["registrant_clientip"] = ibs_getClientIp();
        $data["registrant_nlLegalForm"] = $params["additionalfields"]["nlLegalForm"];
        $data["registrant_nlRegNumber"] = $params["additionalfields"]["nlRegNumber"];
        $data["technical_nlLegalForm"] = $params["additionalfields"]["nlLegalForm"];
        $data["technical_nlRegNumber"] = $params["additionalfields"]["nlRegNumber"];
        $data["admin_nlLegalForm"] = $params["additionalfields"]["nlLegalForm"];
        $data["admin_nlRegNumber"] = $params["additionalfields"]["nlRegNumber"];
        $data["billing_nlLegalForm"] = $params["additionalfields"]["nlLegalForm"];
        $data["billing_nlRegNumber"] = $params["additionalfields"]["nlRegNumber"];
    }
    //END OF .NL //

    if ($tld == "us") {
        if (isset($params["additionalfields"]["Application Purpose"])) {
            $usDomainPurpose = trim($params["additionalfields"]["Application Purpose"]);

            if (strtolower($usDomainPurpose) == strtolower("Business use for profit")) {
                $data["registrant_uspurpose"] = "P1";
            } elseif (strtolower($usDomainPurpose) == strtolower("Educational purposes")) {
                $data["registrant_uspurpose"] = "P4";
            } elseif (strtolower($usDomainPurpose) == strtolower("Personal Use")) {
                $data["registrant_uspurpose"] = "P3";
            } elseif (strtolower($usDomainPurpose) == strtolower("Government purposes")) {
                $data["registrant_uspurpose"] = "P5";
            } else {
                $data["registrant_uspurpose"] = "P2";
            }
        } else {
            $data["registrant_uspurpose"] = $params["additionalfields"]["uspurpose"];
        }
        if (isset($params["additionalfields"]["Nexus Category"])) {
            $data["registrant_usnexuscategory"] = $params["additionalfields"]["Nexus Category"];
        } else {
            $data["registrant_usnexuscategory"] = $params["additionalfields"]["usnexuscategory"];
        }
        if (isset($params["additionalfields"]["Nexus Country"])) {
            $data["registrant_usnexuscountry"] = $params["additionalfields"]["Nexus Country"];
        } else {
            $data["registrant_usnexuscountry"] = $params["additionalfields"]["usnexuscountry"];
        }
    }

    if ($ext == "uk") {
        $legalType = $params["additionalfields"]["Legal Type"];
        $dotUKOrgType = $legalType;
        switch ($legalType) {
            case "Individual":
                $dotUKOrgType = "IND";
                break;
            case "UK Limited Company":
                $dotUKOrgType = "LTD";
                break;
            case "UK Public Limited Company":
                $dotUKOrgType = "PLC";
                break;
            case "UK Partnership":
                $dotUKOrgType = "PTNR";
                break;
            case "UK Limited Liability Partnership":
                $dotUKOrgType = "LLP";
                break;
            case "Sole Trader":
                $dotUKOrgType = "STRA";
                break;
            case "Industrial/Provident Registered Company":
                $dotUKOrgType = "IP";
                break;
            case "UK School":
                $dotUKOrgType = "SCH";
                break;
            case "Government Body":
                $dotUKOrgType = "GOV";
                break;
            case "Corporation By Royal Charter":
                $dotUKOrgType = "CRC";
                break;
            case "Uk Statutory Body":
                $dotUKOrgType = "STAT";
                break;
            case "UK Registered Charity":
                $dotUKOrgType = "RCHAR";
                break;
            case "UK Entity (other)":
                $dotUKOrgType = "OTHER";
                break;
            case "Non-UK Individual":
                $dotUKOrgType = "FIND";
                break;
            case "Non-Uk Corporation":
                $dotUKOrgType = "FCORP";
                break;
            case "Other foreign entity":
                $dotUKOrgType = "FOTHER";
                break;
        }

        if (in_array($dotUKOrgType, ["LTD", "PLC", "LLP", "IP", "SCH", "RCHAR"])) {
            $data["registrant_dotUkOrgNo"] = $params["additionalfields"]["Company ID Number"];
            $data["registrant_dotUKRegistrationNumber"] = $params["additionalfields"]["Company ID Number"];
        }

        // organization type
        $data["registrant_dotUKOrgType"] = isset($params["additionalfields"]["Legal Type"]) ? $dotUKOrgType : "IND";
        if ($data["registrant_dotUKOrgType"] == "IND") {
            // hide data in private whois? (Y/N)
            $data["registrant_dotUKOptOut"] = "N";
        }

        $data["registrant_dotUKLocality"] = $AdminCountry;
    }

    if ($tld == "asia") {
        if (!isset($params["additionalfields"]["Locality"])) {
            $asianCountries = ["AF", "AQ", "AM", "AU", "AZ", "BH", "BD", "BT", "BN", "KH", "CN", "CX", "CC", "CK", "CY", "FJ", "GE", "HM", "HK", "IN", "ID", "IR", "IQ", "IL", "JP", "JO", "KZ", "KI", "KP", "KR", "KW", "KG", "LA", "LB", "MO", "MY", "MV", "MH", "FM", "MN", "MM", "NR", "NP", "NZ", "NU", "NF", "OM", "PK", "PW", "PS", "PG", "PH", "QA", "WS", "SA", "SG", "SB", "LK", "SY", "TW", "TJ", "TH", "TL", "TK", "TO", "TR", "TM", "TV", "AE", "UZ", "VU", "VN", "YE"];
            if (!in_array($RegistrantCountry, $asianCountries)) {
                //$RegistrantCountry = "BD";
                //cannot set country explicitly, let the registration fail
            }
            $data["registrant_countrycode"] = $RegistrantCountry;
            $data["registrant_dotASIACedLocality"] = $data["registrant_countrycode"];
        } else {
            $data["registrant_dotASIACedLocality"] = $params["additionalfields"]["Locality"];
        }
        $data["registrant_dotasiacedentity"] = $params["additionalfields"]["Legal Entity Type"];
        if ($data["registrant_dotasiacedentity"] == "other") {
            $data["registrant_dotasiacedentityother"] = isset($params["additionalfields"]["Other legal entity type"]) ? $params["additionalfields"]["Other legal entity type"] : "otheridentity";
        }
        $data["registrant_dotasiacedidform"] = $params["additionalfields"]["Identification Form"];
        if ($data["registrant_dotasiacedidform"] != "other") {
            $data["registrant_dotASIACedIdNumber"] = $params["additionalfields"]["Identification Number"];
        }
        if ($data["registrant_dotasiacedidform"] == "other") {
            $data["registrant_dotasiacedidformother"] = isset($params["additionalfields"]["Other identification form"]) ? $params["additionalfields"]["Other identification form"] : "otheridentity";
        }
    }

    if (in_array($ext, ["fr", "re", "pm", "tf", "wf", "yt"])) {
        $holderType = isset($params["additionalfields"]["Holder Type"]) ? $params["additionalfields"]["Holder Type"] : "individual";
        $data["registrant_dotfrcontactentitytype"] = $holderType;
        $data["admin_dotfrcontactentitytype"] = $holderType;

        switch ($holderType) {
            case "individual":
                $data["registrant_dotfrcontactentitybirthdate"] = $params["additionalfields"]["Birth Date YYYY-MM-DD"];
                $data["registrant_dotfrcontactentitybirthplacecountrycode"] = $params["additionalfields"]["Birth Country Code"];
                $data["admin_dotfrcontactentitybirthdate"] = $params["additionalfields"]["Birth Date YYYY-MM-DD"];
                $data["admin_dotfrcontactentitybirthplacecountrycode"] = $params["additionalfields"]["Birth Country Code"];
                if (strtolower($params["additionalfields"]["Birth Country Code"]) == "fr") {
                    $data["registrant_dotFRContactEntityBirthCity"] = $params["additionalfields"]["Birth City"];
                    $data["registrant_dotFRContactEntityBirthPlacePostalCode"] = $params["additionalfields"]["Birth Postal code"];
                    $data["admin_dotFRContactEntityBirthCity"] = $params["additionalfields"]["Birth City"];
                    $data["admin_dotFRContactEntityBirthPlacePostalCode"] = $params["additionalfields"]["Birth Postal code"];
                }
                $data["registrant_dotFRContactEntityRestrictedPublication"] = isset($params["additionalfields"]["Restricted Publication"]) ? 1 : 0;
                $data["admin_dotFRContactEntityRestrictedPublication"] = isset($params["additionalfields"]["Restricted Publication"]) ? 1 : 0;
                break;
            case "company":
                $data["registrant_dotFRContactEntitySiren"] = trim($params["additionalfields"]["Siren"]);
                $data["admin_dotFRContactEntitySiren"] = trim($params["additionalfields"]["Siren"]);
                break;
            case "trademark":
                $data["registrant_dotFRContactEntityTradeMark"] = $params["additionalfields"]["Trade Mark"];
                $data["admin_dotFRContactEntityTradeMark"] = $params["additionalfields"]["Trade Mark"];
                break;
            case "association":
                if (isset($params["additionalfields"]["Waldec"]) && $params["additionalfields"]["Waldec"] != "") {
                    $data["registrant_dotFRContactEntityWaldec"] = $params["additionalfields"]["Waldec"];
                    $data["admin_dotFRContactEntityWaldec"] = $params["additionalfields"]["Waldec"];
                } else {
                    $data["registrant_dotfrcontactentitydateofassociation"] = $params["additionalfields"]["Date of Association YYYY-MM-DD"];
                    $data["registrant_dotFRContactEntityDateOfPublication"] = $params["additionalfields"]["Date of Publication YYYY-MM-DD"];
                    $data["registrant_dotfrcontactentityannounceno"] = $params["additionalfields"]["Annouce No"];
                    $data["registrant_dotFRContactEntityPageNo"] = $params["additionalfields"]["Page No"];
                    $data["admin_dotfrcontactentitydateofassociation"] = $params["additionalfields"]["Date of Association YYYY-MM-DD"];
                    $data["admin_dotFRContactEntityDateOfPublication"] = $params["additionalfields"]["Date of Publication YYYY-MM-DD"];
                    $data["admin_dotfrcontactentityannounceno"] = $params["additionalfields"]["Annouce No"];
                    $data["admin_dotFRContactEntityPageNo"] = $params["additionalfields"]["Page No"];
                }
                break;
            case "other":
                $data["registrant_dotFROtherContactEntity"] = $params["additionalfields"]["Other Legal Status"];
                $data["admin_dotFROtherContactEntity"] = $params["additionalfields"]["Other Legal Status"];
                if (isset($params["additionalfields"]["Siren"])) {
                    $data["registrant_dotFRContactEntitySiren"] = $params["additionalfields"]["Siren"];
                    $data["admin_dotFRContactEntitySiren"] = $params["additionalfields"]["Siren"];
                } elseif (isset($params["additionalfields"]["Trade Mark"])) {
                    $data["registrant_dotFRContactEntityTradeMark"] = $params["additionalfields"]["Trade Mark"];
                    $data["admin_dotFRContactEntityTradeMark"] = $params["additionalfields"]["Trade Mark"];
                }
                break;
        }
        $data["registrant_dotFRContactEntitySiren"] = trim($params["additionalfields"]["Siren"]);
        $data["admin_dotFRContactEntitySiren"] = trim($params["additionalfields"]["Siren"]);
        $data["registrant_dotFRContactEntityVat"] = trim($params["additionalfields"]["VATNO"]);
        $data["admin_dotFRContactEntityVat"] = trim($params["additionalfields"]["VATNO"]);
        $data["registrant_dotFRContactEntityDuns"] = trim($params["additionalfields"]["DUNSNO"]);
        $data["admin_dotFRContactEntityDuns"] = trim($params["additionalfields"]["DUNSNO"]);

        if ($holderType != "individual") {
            $data["registrant_dotFRContactEntityName"] = empty($RegistrantCompany) ? $RegistrantFirstName . " " . $RegistrantLastName : $RegistrantCompany;
            $data["admin_dotFRContactEntityName"] = empty($AdminCompany) ? $AdminFirstName . " " . $AdminLastName : $AdminCompany;
        }
    }

    if ($tld == "tel") {
        if (isset($params["additionalfields"]["telhostingaccount"])) {
            $TelHostingAccount = $params["additionalfields"]["telhostingaccount"];
        } else {
            $TelHostingAccount = md5($RegistrantLastName . $RegistrantFirstName . time() . rand(0, 99999));
        }
        if (isset($params["additionalfields"]["telhostingpassword"])) {
            $TelHostingPassword = $params["additionalfields"]["telhostingpassword"];
        } else {
            $TelHostingPassword = "passwd" . rand(0, 99999);
        }

        $data["telHostingAccount"] = $TelHostingAccount;
        $data["telHostingPassword"] = $TelHostingPassword;
        if ($params["additionalfields"]["telhidewhoisdata"] != "") {
            $data["telHideWhoisData"] = "YES";
        } else {
            $data["telHideWhoisData"] = "NO";
        }
    }

    if ($tld == "it") {
        $EUCountries = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "GR", "HU", "IS", "IE", "IT", "LV", "LI", "LT", "LU", "MT", "NL", "NO", "PL", "PT", "RO", "SM", "SK", "SI", "ES", "SE", "CH", "GB", "VA"];
        $EntityTypes = [
            "1. Italian and foreign natural persons" => 1,
            "2. Companies/one man companies" => 2,
            "3. Freelance workers/professionals" => 3,
            "4. non-profit organizations" => 4,
            "5. public organizations" => 5,
            "6. other subjects" => 6,
            "7. foreigners who match 2 - 6" => 7
        ];
        $legalEntityType = $params["additionalfields"]["Legal Entity Type"];
        $et = $EntityTypes[$legalEntityType];
        $data["registrant_dotitentitytype"] = $et;

        $isDotIdAdminAndRegistrantSame = (1 == $et);
        if (strlen($params["additionalfields"]["Nationality"]) > 2) {
            $nationality = ibs_getCountryCodeByName($params["additionalfields"]["Nationality"]);
        } else {
            $nationality = $params["additionalfields"]["Nationality"];
        }
        if ($et >= 2 && $et <= 6) {
            $data["registrant_countrycode"] = $params["country"];
            $data["registrant_dotitnationality"] = $nationality;
        } elseif ($et == 7) {
            if (!in_array($data["registrant_countrycode"], $EUCountries)) {
                $values["error"] = "Registration failed. Registrant should be from EU.";
            }
            $data["registrant_dotitnationality"] = $data["registrant_countrycode"];
        } else {
            if (!in_array($nationality, $EUCountries) && !in_array($data["registrant_countrycode"], $EUCountries)) {
                //$nationality="IT";
                $values["error"] = "Registration failed. Registrant nationality or country of residence should be from EU.";
            }
            $data["registrant_dotitnationality"] = $nationality;
        }

        if (strtoupper($data["registrant_countrycode"]) == "IT") {
            // Extract province code from input value
            $data["registrant_dotitprovince"] = ibs_get2CharDotITProvinceCode($RegistrantStateProvince);
        } else {
            $data["registrant_dotitprovince"] = $RegistrantStateProvince;
        }
        if (strtoupper($data["admin_countrycode"]) == "IT") {
            $data["admin_dotitprovince"] = ibs_get2CharDotITProvinceCode($AdminStateProvince);
        } else {
            $data["admin_dotitprovince"] = $AdminStateProvince;
        }

        $data["technical_dotitprovince"] = $data["admin_dotitprovince"];

        $data["registrant_dotitregcode"] = $params["additionalfields"]["VATTAXPassportIDNumber"];
        $data["registrant_dotithidewhois"] = ($params["additionalfields"]["Hide data in public WHOIS"] == "on" && $et == 1) ? "YES" : "NO";
        $data["admin_dotithidewhois"] = $data["registrant_dotithidewhois"];

        // Hide or not data in public whois
        if (!$isDotIdAdminAndRegistrantSame) {
            $data["admin_dotithidewhois"] = $hideWhoisData;
        }
        $data["technical_dotithidewhois"] = $hideWhoisData;
        $data["registrant_clientip"] = ibs_getClientIp();
        $data["registrant_dotitterm1"] = "yes";
        $data["registrant_dotitterm2"] = "yes";
        $data["registrant_dotitterm3"] = ($params["additionalfields"]["Hide data in public WHOIS"] == "on" && $et == 1) ? "no" : "yes";
        $data["registrant_dotitterm4"] = "yes";
    }
    if ($tld == "ro") {
        $data["registrant_identificationnumber"] = $params["additionalfields"]["CNPFiscalCode"];

        $EUCountries = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "GR", "HU", "IS", "IE", "IT", "LV", "LI", "LT", "LU", "MT", "NL", "NO", "PL", "PT", "RO", "SM", "SK", "SI", "ES", "SE", "CH", "GB", "VA"];
        if (in_array($data["registrant_countrycode"], $EUCountries)) {
            $data["registrant_vatnumber"] = $params["additionalfields"]["Registration Number"];
        } else {
            $data["registrant_companynumber"] = $params["additionalfields"]["Registration Number"];
        }
    }
    // period is optional
    if (isset($params["regperiod"]) && $regperiod > 0) {
        $data["period"] = $regperiod . "Y";
    }
    if (!$values["error"]) {
        // create domain
        $result = ibs_call($params, "Domain/Create", $data);

        # If error, return the error message in the value below
        if ($result["status"] === "FAILURE") {
            $values["error"] = $result["message"];
        } else {
            $values["success"] = true;
            //add here chaging date of next billing and next due date
        }

        if ($result["product_0_status"] == "FAILURE") {
            if (isset($values["error"])) {
                $values["error"] .= $result["product_0_message"];
            } else {
                $values["error"] = $result["product_0_message"];
            }
        }
        if (($result["status"] == "FAILURE" || $result["product_0_status"] == "FAILURE") && (!isset($values["error"]) || empty($values["error"]))) {
            $values["error"] = "Error: cannot register domain";
        }
    }

    //There was an error registering the domain
    if ($values["error"]) {
        ibs_billableOperationErrorHandler(
            $params,
            "$domainName registration error",
            ("There was an error registering the domain $domainName: " . $values["error"] . "\n\n\n" .
                "Request parameters: " . print_r($data, true) . "\n\n" .
                "Response data: " . print_r($result, true) . "\n\n"
            )
        );
    }
    return $values;
}


/**
 * This function is called when a domain release is requested (eg. UK IPSTag Changes)
 * @param $params
 */
function ibs_ReleaseDomain($params)
{
    # code to for changing the .uk Tag (domain push)
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/ChangeTagDotUK", [
        "domain" => $domainName,
        "newtag" => $params["transfertag"]
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }
    return [
        "success" => "success"
    ];
}

/**
 * initiates transfer for a domain
 *
 * @param unknown_type $params
 * @return unknown
 */
function ibs_TransferDomain($params)
{
    $hideWhoisData = (isset($params["HideWhoisData"]) && ("on" == strtolower($params["HideWhoisData"]))) ? "YES" : "NO";

    $tld = $params["tld"];

    $transfersecret = $params["transfersecret"];

    # Registrant Details
    $RegistrantFirstName = $params["firstname"];
    $RegistrantLastName = $params["lastname"];
    $RegistrantCompany = trim($params["companyname"]);
    $RegistrantAddress1 = $params["address1"];
    $RegistrantAddress2 = $params["address2"];
    $RegistrantCity = $params["city"];
    $RegistrantStateProvince = $params["state"];
    $RegistrantPostalCode = $params["postcode"];
    $RegistrantCountry = $params["country"];
    $RegistrantEmailAddress = $params["email"];
    $RegistrantPhone = ibs_reformatPhone($params["phonenumber"], $params["country"]);
    # Admin Details
    $AdminFirstName = $params["adminfirstname"];
    $AdminLastName = $params["adminlastname"];
    $AdminAddress1 = $params["adminaddress1"];
    $AdminAddress2 = $params["adminaddress2"];
    $AdminCity = $params["admincity"];
    $AdminCompany = $params["admincompanyname"];
    $AdminStateProvince = $params["adminstate"];
    $AdminPostalCode = $params["adminpostcode"];
    $AdminCountry = $params["admincountry"];
    $AdminEmailAddress = $params["adminemail"];
    $AdminPhone = ibs_reformatPhone($params["adminphonenumber"], $params["admincountry"]);

    # code to transfer domain
    $domainName = ibs_getInputDomain($params);

    $nslist = [];
    for ($i = 1; $i <= 5; $i++) {
        if (isset($params["ns$i"])) {
            array_push($nslist, $params["ns$i"]);
        }
    }

    $data = [
        "domain" => $domainName,
        "transferAuthInfo" => $transfersecret,

        // registrant contact data
        "registrant_firstname" => $RegistrantFirstName,
        "registrant_lastname" => $RegistrantLastName,
        "registrant_street" => $RegistrantAddress1,
        "registrant_street2" => $RegistrantAddress2,
        "registrant_city" => $RegistrantCity,
        "registrant_state" => $RegistrantStateProvince,
        "registrant_countrycode" => $RegistrantCountry,
        "registrant_postalcode" => $RegistrantPostalCode,
        "registrant_email" => $RegistrantEmailAddress,
        "registrant_phonenumber" => $RegistrantPhone,

        // technical contact data
        "technical_firstname" => $AdminFirstName,
        "technical_lastname" => $AdminLastName,
        "technical_street" => $AdminAddress1,
        "technical_street2" => $AdminAddress2,
        "technical_city" => $AdminCity,
        "technical_state" => $AdminStateProvince,
        "technical_countrycode" => $AdminCountry,
        "technical_postalcode" => $AdminPostalCode,
        "technical_email" => $AdminEmailAddress,
        "technical_phonenumber" => $AdminPhone,

        // admin contact data
        "admin_firstname" => $AdminFirstName,
        "admin_lastname" => $AdminLastName,
        "admin_street" => $AdminAddress1,
        "admin_street2" => $AdminAddress2,
        "admin_city" => $AdminCity,
        "admin_state" => $AdminStateProvince,
        "admin_countrycode" => $AdminCountry,
        "admin_postalcode" => $AdminPostalCode,
        "admin_email" => $AdminEmailAddress,
        "admin_phonenumber" => $AdminPhone,

        // billing contact data
        "billing_firstname" => $AdminFirstName,
        "billing_lastname" => $AdminLastName,
        "billing_street" => $AdminAddress1,
        "billing_street2" => $AdminAddress2,
        "billing_city" => $AdminCity,
        "billing_state" => $AdminStateProvince,
        "billing_countrycode" => $AdminCountry,
        "billing_postalcode" => $AdminPostalCode,
        "billing_email" => $AdminEmailAddress,
        "billing_phonenumber" => $AdminPhone
    ];

    if (!empty($RegistrantCompany)) {
        $data["Registrant_Organization"] = $RegistrantCompany;
    }
    if (!empty($AdminCompany)) {
        $data["technical_Organization"] = $AdminCompany;
        $data["admin_Organization"] = $AdminCompany;
        $data["billing_Organization"] = $AdminCompany;
    }
    // ns_list is optional
    if (count($nslist)) {
        $data["ns_list"] = implode(",", $nslist);
    }

    // ADDED FOR .EU, .BE, .UK //
    if ((bool)preg_match("/^(eu|be|ul)$/i", $tld)) {
        $data["registrant_language"] = isset($params["Language"]) ? $params["Language"] : "en";
    }
    // END OF .EU, .BE, .UK //

    // ADDED FOR .DE //
    if ((bool)preg_match("/\.de$/i", $domainName)) {
        if (strtolower($params["RenewAfterTransfer"]) == "on") {
            $data["RenewAfterTrasnfer"] = "Yes";
        }
        if ($params["additionalfields"]["role"] == "ORG") {
            $data["registrant_role"] = $params["additionalfields"]["role"];
            $data["admin_role"] = "Person";
            $data["technical_role"] = "Role";
            $data["zone_role"] = "Role";
        } else {
            $data["registrant_role"] = $params["additionalfields"]["role"];
            $data["admin_role"] = "Person";
            $data["technical_role"] = "Person";
            $data["zone_role"] = "Person";
        }
        if ($params["additionalfields"]["tosAgree"] != "") {
            $data["tosAgree"] = "YES";
        } else {
            $data["tosAgree"] = "NO";
        }
        $data["registrant_sip"] = @$params["additionalfields"]["sip"];
        $data["clientip"] = ibs_getClientIp();
        $data["registrant_clientip"] = $data["clientip"];
        if ($params["additionalfields"]["Restricted Publication"] != "") {
            $data["registrant_discloseName"] = "YES";
            $data["registrant_discloseContact"] = "YES";
            $data["registrant_discloseAddress"] = "YES";
        } else {
            $data["registrant_discloseName"] = "NO";
            $data["registrant_discloseContact"] = "NO";
            $data["registrant_discloseAddress"] = "NO";
        }
        $data["zone_firstname"] = $AdminFirstName;
        $data["zone_lastname"] = $AdminLastName;
        $data["zone_email"] = $AdminEmailAddress;
        $data["zone_phonenumber"] = ibs_reformatPhone($params["phonenumber"], $params["country"]);
        $data["zone_postalcode"] = $AdminPostalCode;
        $data["zone_city"] = $AdminCity;
        $data["zone_street"] = $AdminAddress1;
        $data["zone_countrycode"] = $AdminCountry;
    }
    // END OF .DE //

    // ADDED FOR .NL //
    if ((bool)preg_match("/\.nl$/i", $domainName)) {
        if (strtolower($params["RenewAfterTransfer"]) == "on") {
            $data["renewAfterTrasnfer"] = "Yes";
        }
        if ($params["additionalfields"]["nlTerm"] != "") {
            $data["registrant_nlTerm"] = "YES";
        } else {
            $data["registrant_nlTerm"] = "NO";
        }
        $data["registrant_clientip"] = ibs_getClientIp();
        $data["registrant_nlLegalForm"] = $params["additionalfields"]["nlLegalForm"];
        $data["registrant_nlRegNumber"] = $params["additionalfields"]["nlRegNumber"];
    }
    // END OF .NL //

    // ADDED FOR .US //
    if ((bool)preg_match("/\.us$/i", $domainName)) {
        if (isset($params["additionalfields"]["Application Purpose"])) {
            $usDomainPurpose = trim($params["additionalfields"]["Application Purpose"]);

            if (strtolower($usDomainPurpose) == strtolower("Business use for profit")) {
                $data["registrant_uspurpose"] = "P1";
            } elseif (strtolower($usDomainPurpose) == strtolower("Educational purposes")) {
                $data["registrant_uspurpose"] = "P4";
            } elseif (strtolower($usDomainPurpose) == strtolower("Personal Use")) {
                $data["registrant_uspurpose"] = "P3";
            } elseif (strtolower($usDomainPurpose) == strtolower("Government purposes")) {
                $data["registrant_uspurpose"] = "P5";
            } else {
                $data["registrant_uspurpose"] = "P2";
            }
        } else {
            $data["registrant_uspurpose"] = $params["additionalfields"]["uspurpose"];
        }
        if (isset($params["additionalfields"]["Nexus Category"])) {
            $data["registrant_usnexuscategory"] = $params["additionalfields"]["Nexus Category"];
        } else {
            $data["registrant_usnexuscategory"] = $params["additionalfields"]["usnexuscategory"];
        }
        if (isset($params["additionalfields"]["Nexus Country"])) {
            $data["registrant_usnexuscountry"] = $params["additionalfields"]["Nexus Country"];
        } else {
            $data["registrant_usnexuscountry"] = $params["additionalfields"]["usnexuscountry"];
        }
    }
    // END OF .US //

    // ADDED FOR .ASIA //
    if ((bool)preg_match("/\.asia$/i", $domainName)) {
        $data["registrant_dotASIACedLocality"] = $AdminCountry;
        $data["registrant_dotasiacedentity"] = $params["additionalfields"]["Legal Entity Type"];
        if ($data["registrant_dotasiacedentity"] == "other") {
            $data["registrant_dotasiacedentityother"] = isset($params["additionalfields"]["Other legal entity type"]) ? $params["additionalfields"]["Other legal entity type"] : "otheridentity";
        }
        $data["registrant_dotasiacedidform"] = $params["additionalfields"]["Identification Form"];
        if ($data["registrant_dotasiacedidform"] != "other") {
            $data["registrant_dotASIACedIdNumber"] = $params["additionalfields"]["Identification Number"];
        }
        if ($data["registrant_dotasiacedidform"] == "other") {
            $data["registrant_dotasiacedidformother"] = isset($params["additionalfields"]["Other identification form"]) ? $params["additionalfields"]["Other identification form"] : "otheridentity";
        }
    }
    // END OF .ASIA //

    // ADDED FOR AFNIC TLDs //
    if ((bool)preg_match("/\.(fr|re|pm|tf|wf|yt$/i", $domainName)) {
        $holderType = isset($params["additionalfields"]["Holder Type"]) ? $params["additionalfields"]["Holder Type"] : "individual";

        if ($tld == "fr") {
            $holderType = isset($params["additionalfields"]["Holder Type"]) ? $params["additionalfields"]["Holder Type"] : "individual";
            //$data["admin_countrycode"] = "FR";
            if ($data["admin_countrycode"] != "FR") {
                return [
                    "error" => "Registration failed. Administrator should be from France."
                ];
            }
        } elseif ($tld == "re") {
            $holderType = isset($params["additionalfields"]["Holder Type"]) ? $params["additionalfields"]["Holder Type"] : "other";
            //$data["registrant_countrycode"] = "RE";
            if ($data["registrant_countrycode"] = "RE") {
                return [
                    "error" => "Registration failed. Registrant should be from Reunion."
                ];
            }
            $frenchTerritoryCountries = ["GP", "MQ", "GF", "RE", "FR", "PF", "MQ", "YT", "NC", "PM", "WF", "MF", "BL", "TF"];
            if (!in_array($data["admin_countrycode"], $frenchTerritoryCountries)) {
                //$data["admin_countrycode"]="RE";
                return [
                    "error" => "Registration failed. Administrator should be from Reunion."
                ];
            }
        }

        $data["registrant_dotfrcontactentitytype"] = $holderType;
        $data["admin_dotfrcontactentitytype"] = $holderType;

        switch ($holderType) {
            case "individual":
                $data["registrant_dotfrcontactentitybirthdate"] = $params["additionalfields"]["Birth Date YYYY-MM-DD"];
                $data["registrant_dotfrcontactentitybirthplacecountrycode"] = $params["additionalfields"]["Birth Country Code"];
                $data["admin_dotfrcontactentitybirthdate"] = $params["additionalfields"]["Birth Date YYYY-MM-DD"];
                $data["admin_dotfrcontactentitybirthplacecountrycode"] = $params["additionalfields"]["Birth Country Code"];
                $data["registrant_dotFRContactEntityBirthCity"] = $params["additionalfields"]["Birth City"];
                $data["registrant_dotFRContactEntityBirthPlacePostalCode"] = $params["additionalfields"]["Birth Postal code"];
                $data["admin_dotFRContactEntityBirthCity"] = $params["additionalfields"]["Birth City"];
                $data["admin_dotFRContactEntityBirthPlacePostalCode"] = $params["additionalfields"]["Birth Postal code"];

                $data["registrant_dotFRContactEntityRestrictedPublication"] = isset($params["additionalfields"]["Restricted Publication"]) ? 1 : 0;
                $data["admin_dotFRContactEntityRestrictedPublication"] = isset($params["additionalfields"]["Restricted Publication"]) ? 1 : 0;
                break;
            case "company":
                $data["registrant_dotFRContactEntitySiren"] = $params["additionalfields"]["Siren"];
                $data["admin_dotFRContactEntitySiren"] = $params["additionalfields"]["Siren"];
                break;
            case "trademark":
                $data["registrant_dotFRContactEntityTradeMark"] = $params["additionalfields"]["Trade Mark"];
                $data["admin_dotFRContactEntityTradeMark"] = $params["additionalfields"]["Trade Mark"];
                break;
            case "association":
                if (isset($params["Waldec"])) {
                    $data["registrant_dotFRContactEntityWaldec"] = $params["additionalfields"]["Waldec"];
                    $data["admin_dotFRContactEntityWaldec"] = $params["additionalfields"]["Waldec"];
                } else {
                    $data["registrant_dotfrcontactentitydateofassociation"] = $params["additionalfields"]["Date of Association YYYY-MM-DD"];
                    $data["registrant_dotFRContactEntityDateOfPublication"] = $params["additionalfields"]["Date of Publication YYYY-MM-DD"];
                    $data["registrant_dotfrcontactentityannounceno"] = $params["additionalfields"]["Annouce No"];
                    $data["registrant_dotFRContactEntityPageNo"] = $params["additionalfields"]["Page No"];
                    $data["admin_dotfrcontactentitydateofassociation"] = $params["additionalfields"]["Date of Association YYYY-MM-DD"];
                    $data["admin_dotFRContactEntityDateOfPublication"] = $params["additionalfields"]["Date of Publication YYYY-MM-DD"];
                    $data["admin_dotfrcontactentityannounceno"] = $params["additionalfields"]["Annouce No"];
                    $data["admin_dotFRContactEntityPageNo"] = $params["additionalfields"]["Page No"];
                }

                break;
            case "other":
                $data["registrant_dotFROtherContactEntity"] = $params["additionalfields"]["Other Legal Status"];
                $data["admin_dotFROtherContactEntity"] = $params["additionalfields"]["Other Legal Status"];
                if (isset($params["additionalfields"]["Siren"])) {
                    $data["registrant_dotFRContactEntitySiren"] = $params["additionalfields"]["Siren"];
                    $data["admin_dotFRContactEntitySiren"] = $params["additionalfields"]["Siren"];
                } elseif (isset($params["additionalfields"]["Trade Mark"])) {
                    $data["registrant_dotFRContactEntityTradeMark"] = $params["additionalfields"]["Trade Mark"];
                    $data["admin_dotFRContactEntityTradeMark"] = $params["additionalfields"]["Trade Mark"];
                }
                break;
        }
        $data["registrant_dotFRContactEntitySiren"] = trim($params["additionalfields"]["Siren"]);
        $data["admin_dotFRContactEntitySiren"] = trim($params["additionalfields"]["Siren"]);
        $data["registrant_dotFRContactEntityVat"] = trim($params["additionalfields"]["VATNO"]);
        $data["admin_dotFRContactEntityVat"] = trim($params["additionalfields"]["VATNO"]);
        $data["registrant_dotFRContactEntityDuns"] = trim($params["additionalfields"]["DUNSNO"]);
        $data["admin_dotFRContactEntityDuns"] = trim($params["additionalfields"]["DUNSNO"]);

        if ($holderType != "individual") {
            $data["registrant_dotFRContactEntityName"] = empty($RegistrantCompany) ? $RegistrantFirstName . " " . $RegistrantLastName : $RegistrantCompany;
            $data["admin_dotFRContactEntityName"] = empty($AdminCompany) ? $AdminFirstName . " " . $AdminLastName : $AdminCompany;
        }
    }
    // END OF AFNIC TLDs //

    // ADDED FOR .TEL //
    if ((bool)preg_match("/\.tel$/i", $domainName)) {
        if (isset($params["additionalfields"]["telhostingaccount"])) {
            $TelHostingAccount = $params["additionalfields"]["telhostingaccount"];
        } else {
            $TelHostingAccount = md5($RegistrantLastName . $RegistrantFirstName . time() . rand(0, 99999));
        }
        if (isset($params["additionalfields"]["telhostingpassword"])) {
            $TelHostingPassword = $params["additionalfields"]["telhostingpassword"];
        } else {
            $TelHostingPassword = "passwd" . rand(0, 99999);
        }

        $data["telHostingAccount"] = $TelHostingAccount;
        $data["telHostingPassword"] = $TelHostingPassword;
        if ($params["additionalfields"]["telhidewhoisdata"] != "") {
            $data["telHideWhoisData"] = "YES";
        } else {
            $data["telHideWhoisData"] = "NO";
        }
    }
    // END OF .TEL //

    // ADDED FOR .IT //
    if ((bool)preg_match("/\.it$/i", $domainName)) {
        $EUCountries = ibs_getEuContries(true);
        $EntityTypes = [
            "1. Italian and foreign natural persons" => 1,
            "2. Companies/one man companies" => 2,
            "3. Freelance workers/professionals" => 3,
            "4. non-profit organizations" => 4,
            "5. public organizations" => 5,
            "6. other subjects" => 6,
            "7. foreigners who match 2 - 6" => 7
        ];
        // --- legal entity type
        $et = $EntityTypes[$params["additionalfields"]["Legal Entity Type"]];
        $data["registrant_dotitentitytype"] = $et;
        // error cases first - exit as early as possible
        if ($et === 7 && !in_array($data["registrant_countrycode"], $EUCountries)) {
            return [
                "error" => "Transfer failed. Registrant should be from EU."
            ];
        }

        // --- nationality
        $data["registrant_dotitnationality"] = $params["additionalfields"]["Nationality"];
        if (strlen($data["registrant_dotitnationality"]) > 2) {
            $data["registrant_dotitnationality"] = ibs_getCountryCodeByName($data["registrant_dotitnationality"]);
        }
        if ($et === 7) {
            $data["registrant_dotitnationality"] = $data["registrant_countrycode"];
        }

        // error cases first - exit as early as possible
        if ($et === 1) {
            if (
                !in_array($data["registrant_dotitnationality"], $EUCountries)
                && !in_array($data["registrant_countrycode"], $EUCountries)
            ) {
                return [
                    "error" => "Transfer failed. Registrant country of residence of nationality should be from EU."
                ];
            }
        }

        $data["registrant_dotitprovince"] = $RegistrantStateProvince;
        if (strtoupper($data["registrant_countrycode"]) === "IT") {
            $data["registrant_dotitprovince"] = ibs_get2CharDotITProvinceCode($RegistrantStateProvince);
        }
        $data["admin_dotitprovince"] = $AdminStateProvince;
        if (strtoupper($data["admin_countrycode"]) === "IT") {
            $data["admin_dotitprovince"] = ibs_get2CharDotITProvinceCode($AdminStateProvince);
        }

        $data["technical_dotitprovince"] = $data["admin_dotitprovince"];
        $data["registrant_dotitregcode"] = $params["additionalfields"]["VATTAXPassportIDNumber"];

        // Hide or not data in public whois
        $data["registrant_dotithidewhois"] = (
            $params["additionalfields"]["Hide data in public WHOIS"] === "on" && $et === 1
        ) ? "YES" : "NO";
        $data["admin_dotithidewhois"] = $data["registrant_dotithidewhois"];
        $isDotIdAdminAndRegistrantSame = (1 === $et);
        if (!$isDotIdAdminAndRegistrantSame) {
            $data["admin_dotithidewhois"] = $hideWhoisData;
        }
        $data["technical_dotithidewhois"] = $hideWhoisData;

        $data["registrant_clientip"] = ibs_getClientIp();
        $data["registrant_dotitterm1"] = "yes";
        $data["registrant_dotitterm2"] = "yes";
        $data["registrant_dotitterm3"] = strtolower($data["registrant_dotithidewhois"]);
        $data["registrant_dotitterm4"] = "yes";
    }
    // END OF .IT //

    if ($params["idprotection"]) {
        $data["privateWhois"] = "FULL";
    }
    // initiate domain transfer
    $result = ibs_call($params, "Domain/Transfer/Initiate", $data);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        $values["error"] = $result["message"];
    }
    if ($result["product_0_status"] === "FAILURE") {
        if (isset($values["error"])) {
            $values["error"] .= $result["product_0_message"];
        } else {
            $values["error"] = $result["product_0_message"];
        }
    }
    if (
        ($result["status"] == "FAILURE" || $result["product_0_status"] == "FAILURE")
        && (!isset($values["error"]) || empty($values["error"]))
    ) {
        $values["error"] = "Error: cannot start transfer domain";
    }
    //There was an error transferring the domain
    if ($values["error"]) {
        ibs_billableOperationErrorHandler(
            $params,
            "$domainName transfer error",
            ("There was an error starting transfer for $domainName: " . $values["error"] . "\n\n\n" .
                "Request parameters: " . print_r($data, true) . "\n\n" .
                "Response data: " . print_r($result, true) . "\n\n"
            )
        );
    }

    return $values;
}

/**
 * renews a domain
 *
 * @param array $params
 * @return array
 */
function ibs_RenewDomain($params)
{
    # code to renew domain
    $domainName = ibs_getInputDomain($params);

    $data = [
        "domain" => $domainName
    ];

    // period is optional
    $regperiod = (int)$params["regperiod"];
    if (isset($params["regperiod"]) && $regperiod > 0) {
        $data["period"] = $regperiod . "Y";
    }

    $response = DB::table("tbldomains")
        ->where("id", $params["domainid"])
        ->pluck("expirydate");

    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }

    $expirydate = trim($response[0]);

    // Normally we expect from mysql to get result like YYYY-MM-DD, if it"s not then we try to autofix it
    if (!preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/is", $expirydate)) {
        $expirydate = date("Y-m-d", strtotime($expirydate));
    }

    $data["currentexpiration"] = $expirydate;

    // identify process type: restore or renewal
    $process = (isset($params["isInRedemptionGracePeriod"]) && $params["isInRedemptionGracePeriod"]) ? "restore" : "renew";
    $result = ibs_call($params, "Domain/" . ucfirst($process), $data);

    # If error, return the error message in the value below
    if ($result["status"] !== "FAILURE") {
        return [
            "success" => true
        ];
    }

    //There was an error renewing the domain
    ibs_billableOperationErrorHandler(
        $params,
        "$domainName $process error",
        ("There was an error with domain $process of domain $domainName: " .
            $result["message"] . "\n\n\n" .
            "Request parameters: " . print_r($data, true) . "\n\n" .
            "Response data: " . print_r($result, true) . "\n\n"
        )
    );

    return [
        "error" => $result["message"]
    ];
}

/**
 * gets contact details for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetContactDetails($params)
{
    # code to get WHOIS data
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/Info", [
        "domain" => $domainName
    ]);
    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }

    # Data should be returned in an array as follows
    $values = [
        "Registrant" => [],
        "Admin" => []
    ];
    $values["Registrant"]["First Name"] = $result["contacts_registrant_firstname"];
    $values["Registrant"]["Last Name"] = $result["contacts_registrant_lastname"];
    $values["Registrant"]["Company"] = $result["contacts_registrant_organization"];
    $values["Registrant"]["Email"] = $result["contacts_registrant_email"];
    $values["Registrant"]["Phone Number"] = $result["contacts_registrant_phonenumber"];
    $values["Registrant"]["Address1"] = $result["contacts_registrant_street"];
    $values["Registrant"]["Address2"] = $result["contacts_registrant_street1"];
    $values["Registrant"]["State"] = $result["contacts_registrant_state"];
    $values["Registrant"]["Postcode"] = $result["contacts_registrant_postalcode"];
    $values["Registrant"]["City"] = $result["contacts_registrant_city"];
    $values["Registrant"]["Country"] = $result["contacts_registrant_country"];
    $values["Registrant"]["Country Code"] = $result["contacts_registrant_countrycode"];

    $values["Admin"]["First Name"] = $result["contacts_admin_firstname"];
    $values["Admin"]["Last Name"] = $result["contacts_admin_lastname"];
    $values["Admin"]["Company"] = $result["contacts_admin_organization"];
    $values["Admin"]["Email"] = $result["contacts_admin_email"];
    $values["Admin"]["Phone Number"] = $result["contacts_admin_phonenumber"];
    $values["Admin"]["Address1"] = $result["contacts_admin_street"];
    $values["Admin"]["Address2"] = $result["contacts_admin_street1"];
    $values["Admin"]["State"] = $result["contacts_admin_state"];
    $values["Admin"]["Postcode"] = $result["contacts_admin_postalcode"];
    $values["Admin"]["City"] = $result["contacts_admin_city"];
    $values["Admin"]["Country"] = $result["contacts_admin_country"];
    $values["Admin"]["Country Code"] = $result["contacts_admin_countrycode"];

    if (isset($result["contacts_technical_email"])) {
        $values["Tech"] = [];
        $values["Tech"]["First Name"] = $result["contacts_technical_firstname"];
        $values["Tech"]["Last Name"] = $result["contacts_technical_lastname"];
        $values["Tech"]["Company"] = $result["contacts_technical_organization"];
        $values["Tech"]["Email"] = $result["contacts_technical_email"];
        $values["Tech"]["Phone Number"] = $result["contacts_technical_phonenumber"];
        $values["Tech"]["Address1"] = $result["contacts_technical_street"];
        $values["Tech"]["Address2"] = $result["contacts_technical_street1"];
        $values["Tech"]["State"] = $result["contacts_technical_state"];
        $values["Tech"]["Postcode"] = $result["contacts_technical_postalcode"];
        $values["Tech"]["City"] = $result["contacts_technical_city"];
        $values["Tech"]["Country"] = $result["contacts_technical_country"];
        $values["Tech"]["Country Code"] = $result["contacts_technical_countrycode"];
    }

    return $values;
}

/**
 * saves contact details
 *
 * @param array $params
 * @return array
 */
function ibs_SaveContactDetails($params)
{
    $tld = $params["tld"];

    # Data is returned as specified in the GetContactDetails() function
    $firstname = $params["contactdetails"]["Registrant"]["First Name"];
    $lastname = $params["contactdetails"]["Registrant"]["Last Name"];
    $company = $params["contactdetails"]["Registrant"]["Company"];
    $email = $params["contactdetails"]["Registrant"]["Email"];
    $address1 = $params["contactdetails"]["Registrant"]["Address1"];
    if (!$address1) {
        $address1 = $params["contactdetails"]["Registrant"]["Address 1"];
    }
    $address2 = $params["contactdetails"]["Registrant"]["Address2"];
    if (!$address2) {
        $address2 = $params["contactdetails"]["Registrant"]["Address 2"];
    }
    $state = $params["contactdetails"]["Registrant"]["State"];
    $postalcode = $params["contactdetails"]["Registrant"]["Postcode"];
    $city = $params["contactdetails"]["Registrant"]["City"];
    $country = $params["contactdetails"]["Registrant"]["Country"];
    $countrycode = $params["contactdetails"]["Registrant"]["Country Code"];
    if (!$countrycode) {
        if (strlen($country) == 2) {
            $countrycode = $country;
        } else {
            $countrycode = ibs_getCountryCodeByName($country);
        }
    }
    $phonenumber = ibs_reformatPhone($params["contactdetails"]["Registrant"]["Phone Number"], $countrycode);

    $adminfirstname = $params["contactdetails"]["Admin"]["First Name"];
    $adminlastname = $params["contactdetails"]["Admin"]["Last Name"];
    $adminCompany = $params["contactdetails"]["Admin"]["Company"];
    $adminemail = $params["contactdetails"]["Admin"]["Email"];
    $adminaddress1 = $params["contactdetails"]["Admin"]["Address1"];
    if (!$adminaddress1) {
        $adminaddress1 = $params["contactdetails"]["Admin"]["Address 1"];
    }
    $adminaddress2 = $params["contactdetails"]["Admin"]["Address2"];
    if (!$adminaddress2) {
        $adminaddress2 = $params["contactdetails"]["Admin"]["Address 2"];
    }
    $adminstate = $params["contactdetails"]["Admin"]["State"];
    $adminpostalcode = $params["contactdetails"]["Admin"]["Postcode"];
    $admincity = $params["contactdetails"]["Admin"]["City"];
    $admincountry = $params["contactdetails"]["Admin"]["Country"];
    $admincountrycode = $params["contactdetails"]["Admin"]["Country Code"];
    if (!$admincountrycode) {
        if (strlen($admincountry) == 2) {
            $admincountrycode = $admincountry;
        } else {
            $admincountrycode = ibs_getCountryCodeByName($admincountry);
        }
    }
    $adminphonenumber = ibs_reformatPhone($params["contactdetails"]["Admin"]["Phone Number"], $admincountrycode);

    $techfirstname = $params["contactdetails"]["Tech"]["First Name"];
    $techlastname = $params["contactdetails"]["Tech"]["Last Name"];
    $techCompany = $params["contactdetails"]["Tech"]["Company"];
    $techemail = $params["contactdetails"]["Tech"]["Email"];
    $techaddress1 = $params["contactdetails"]["Tech"]["Address1"];
    if (!$techaddress1) {
        $techaddress1 = $params["contactdetails"]["Tech"]["Address 1"];
    }
    $techaddress2 = $params["contactdetails"]["Tech"]["Address2"];
    if (!$techaddress2) {
        $techaddress2 = $params["contactdetails"]["Tech"]["Address 2"];
    }
    $techstate = $params["contactdetails"]["Tech"]["State"];
    $techpostalcode = $params["contactdetails"]["Tech"]["Postcode"];
    $techcity = $params["contactdetails"]["Tech"]["City"];
    $techcountry = $params["contactdetails"]["Tech"]["Country"];
    $techcountrycode = $params["contactdetails"]["Tech"]["Country Code"];
    if (!$techcountrycode) {
        if (strlen($techcountry) == 2) {
            $techcountrycode = $techcountry;
        } else {
            $techcountrycode = ibs_getCountryCodeByName($techcountry);
        }
    }
    $techphonenumber = ibs_reformatPhone($params["contactdetails"]["Tech"]["Phone Number"], $techcountrycode);

    # Put your code to save new WHOIS data here

    $domainName = ibs_getInputDomain($params);

    $data = [
        "domain" => $domainName,

        // registrant contact data
        "registrant_firstname" => $firstname,
        "registrant_lastname" => $lastname,
        "registrant_organization" => $company,
        "registrant_street" => $address1,
        "registrant_street2" => $address2,
        "registrant_city" => $city,
        "registrant_state" => $state,
        "registrant_countrycode" => $countrycode,
        "registrant_postalcode" => $postalcode,
        "registrant_email" => $email,
        "registrant_phonenumber" => $phonenumber,

        // technical contact data
        "technical_firstname" => $techfirstname,
        "technical_lastname" => $techlastname,
        "technical_organization" => $techCompany,
        "technical_street" => $techaddress1,
        "technical_street2" => $techaddress2,
        "technical_city" => $techcity,
        "technical_state" => $techstate,
        "technical_countrycode" => $techcountrycode,
        "technical_postalcode" => $techpostalcode,
        "technical_email" => $techemail,
        "technical_phonenumber" => $techphonenumber,

        // admin contact data
        "admin_firstname" => $adminfirstname,
        "admin_lastname" => $adminlastname,
        "admin_organization" => $adminCompany,
        "admin_street" => $adminaddress1,
        "admin_street2" => $adminaddress2,
        "admin_city" => $admincity,
        "admin_state" => $adminstate,
        "admin_countrycode" => $admincountrycode,
        "admin_postalcode" => $adminpostalcode,
        "admin_email" => $adminemail,
        "admin_phonenumber" => $adminphonenumber,

        // billing contact data: why not billing but admin?
        "billing_firstname" => $adminfirstname,
        "billing_lastname" => $adminlastname,
        "billing_organization" => $adminCompany,
        "billing_street" => $adminaddress1,
        "billing_street2" => $adminaddress2,
        "billing_city" => $admincity,
        "billing_state" => $adminstate,
        "billing_countrycode" => $admincountrycode,
        "billing_postalcode" => $adminpostalcode,
        "billing_email" => $adminemail,
        "billing_phonenumber" => $adminphonenumber
    ];
    foreach ($data as $key => $val) {
        if ($key !== "domain") {
            $data[$key] = html_entity_decode($val, ENT_QUOTES | ENT_XML1, "UTF-8"); // GitHub #254
        }
    }

    $extarr = explode(".", $tld);
    $ext = array_pop($extarr);


    // Unset params which is not possible update for domain
    if ("it" == $ext) {
        $data["registrant_clientip"] = ibs_getClientIp();
        $data["registrant_dotitterm1"] = "yes";
        $data["registrant_dotitterm2"] = "yes";
        $data["registrant_dotitterm3"] = $params["additionalfields"]["Hide data in public WHOIS"] == "on" ? "no" : "yes";
        $data["registrant_dotitterm4"] = "yes";
        //unset($data["registrant_countrycode"]);
        //unset($data["registrant_organization"]);
        //unset($data["registrant_countrycode"]);
        //unset($data["registrant_country"]);
        //unset($data["registrant_dotitentitytype"]);
        $data["registrant_dotitentitytype"] = $params["additionalfields"]["Legal Entity Type"];
        //unset($data["registrant_dotitnationality"]);
        $data["registrant_dotitnationality"] = $params["additionalfields"]["Nationality"];
        //unset($data["registrant_dotitregcode"]);
        $data["registrant_dotitregcode"] = $params["additionalfields"]["VATTAXPassportIDNumber"];
    }

    //if ($ext == "eu" || $ext == "be") {
    //if (!strlen(trim($data["registrant_organization"]))) {
    // unset($data["registrant_firstname"]);
    // unset($data["registrant_lastname"]);
    //}
    // unset($data["registrant_organization"]);
    //}

    //if ($ext == "co.uk" || $ext == "org.uk" || $ext == "me.uk" || $ext == "uk") {
    //    unset($data["registrant_firstname"]);
    //    unset($data["registrant_lastname"]);
    //}

    if ($ext == "fr" || $ext == "re" || $ext == "tf" || $ext == "pm" || $ext == "yt" || $ext == "wf") {
        //unset($data["registrant_firstname"]);
        //unset($data["registrant_lastname"]);
        //unset($data["registrant_countrycode"]);

        if (!strlen(trim($data["admin_dotfrcontactentitysiren"]))) {
            unset($data["admin_dotfrcontactentitysiren"]);
        }

        //if (trim(strtolower($data["admin_dotfrcontactentitytype"])) == "individual") {
        //    unset($data["admin_countrycode"]);
        //}
    }

    if ($ext == "de") {
        //unset($data["registrant_state"]);
        //unset($data["admin_state"]);
        //unset($data["technical_state"]);
        //unset($data["billing_state"]);
        $data["zone_firstname"] = $adminfirstname;
        $data["zone_lastname"] = $adminlastname;
        $data["zone_email"] = $adminemail;
        $data["zone_phonenumber"] = $adminphonenumber;
        $data["zone_postalcode"] = $adminpostalcode;
        $data["zone_city"] = $admincity;
        $data["zone_street"] = $adminaddress1;
        //$data["zone_countrycode"] = "DE";
        //we should not explicity set admin country as DE
        $data["zone_countrycode"] = $admincountrycode;
        $data["tosagree"] = "Yes";
    }
    if ($ext == "nl") {
        $data["registrant_clientip"] = ibs_getClientIp();
        $data["registrant_nlTerm"] = ($params["additionalfields"]["nlTerm"] != "") ? "YES" : "NO";
        $data["registrant_nllegalform"] = $params["additionalfields"]["nlLegalForm"];
        $data["registrant_nlregnumber"] = $params["additionalfields"]["nlRegNumber"];
    }
    $data["clientip"] = ibs_getClientIp();
    $data["registrant_clientip"] = ibs_getClientIp();

    $result = ibs_call($params, "Domain/Update", $data);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }
    return [
        "success" => true
    ];
}

/**
 * gets domain secret/ transfer auth info of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetEPPCode($params)
{
    // code to request the EPP code
    // if the API returns it, [ "eppcode" => ... ]
    // otherwise return empty array and it will assume code is emailed
    // in case of error return [ "error" => ... ] as usual
    $domainName = ibs_getInputDomain($params);

    if ((bool)preg_match("/\.eu$/i", $domainName)) {
        $result = ibs_call($params, "Domain/AuthInfo/Get", [
            "domain" => $domainName,
        ]);
        if ($result["status"] === "SUCCESS") {
            $result["transferauthinfo"] = $result["password"] ?? "";
        }
    } else {
        $result = ibs_call($params, "Domain/Info", [
            "domain" => $domainName
        ]);
    }

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }
    if (empty($result["transferauthinfo"])) {
        return []; // shows to endcustomer that it is send to registrant by email
    }
    return [
        "eppcode" => $result["transferauthinfo"]
    ];
}

/**
 * creates a host for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_RegisterNameserver($params)
{
    if (!isset($params["original"]["nameserver"])) {
        $nameserver = $params["nameserver"];
    } else {
        $nameserver = $params["original"]["nameserver"];
    }
    $ipaddress = $params["ipaddress"];

    # code to register the nameserver
    $domainName = ibs_getInputDomain($params);

    if (($nameserver != $domainName) && strpos($nameserver, "." . $domainName) === false) {
        $nameserver = $nameserver . "." . $domainName;
    }

    $result = ibs_call($params, "Domain/Host/Create", [
        "host" => $nameserver,
        "ip_list" => $ipaddress
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => isset($result["message"]) ?
                $result["message"] :
                "Due to some technical issue nameserver cannot be registered."
        ];
    }
    return [
        "success" => "success"
    ];
}

/**
 * updates host of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_ModifyNameserver($params)
{
    if (!isset($params["original"]["nameserver"])) {
        $nameserver = $params["nameserver"];
    } else {
        $nameserver = $params["original"]["nameserver"];
    }
    #$currentipaddress = $params["currentipaddress"];
    $newipaddress = $params["newipaddress"];

    # code to update the nameserver
    $domainName = ibs_getInputDomain($params);
    if (($nameserver != $domainName) && strpos($nameserver, "." . $domainName) === false) {
        $nameserver = $nameserver . "." . $domainName;
    }

    $result = ibs_call($params, "Domain/Host/Update", [
        "host" => $nameserver,
        "ip_list" => $newipaddress
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }

    return [
        "success" => "success"
    ];
}

/**
 * deletes a host
 *
 * @param array $params
 * @return array
 */
function ibs_DeleteNameserver($params)
{
    if (!isset($params["original"]["nameserver"])) {
        $nameserver = $params["nameserver"];
    } else {
        $nameserver = $params["original"]["nameserver"];
    }

    # code to delete the nameserver
    $domainName = ibs_getInputDomain($params);
    if (($nameserver != $domainName) && strpos($nameserver, "." . $domainName) === false) {
        $nameserver = $nameserver . "." . $domainName;
    }

    $result = ibs_call($params, "Domain/Host/Delete", [
        "host" => $nameserver
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }

    return [
        "success" => "success"
    ];
}

function ibs_mapCountry($countryCode)
{
    $mapc = ["US" => 1, "CA" => 1, "AI" => 1, "AG" => 1, "BB" => 1, "BS" => 1, "VG" => 1, "VI" => 1, "KY" => 1, "BM" => 1, "GD" => 1, "TC" => 1, "MS" => 1, "MP" => 1, "GU" => 1, "LC" => 1, "DM" => 1, "VC" => 1, "PR" => 1, "DO" => 1, "TT" => 1, "KN" => 1, "JM" => 1, "EG" => 20, "MA" => 212, "DZ" => 213, "TN" => 216, "LY" => 218, "GM" => 220, "SN" => 221, "MR" => 222, "ML" => 223, "GN" => 224, "CI" => 225, "BF" => 226, "NE" => 227, "TG" => 228, "BJ" => 229, "MU" => 230, "LR" => 231, "SL" => 232, "GH" => 233, "NG" => 234, "TD" => 235, "CF" => 236, "CM" => 237, "CV" => 238, "ST" => 239, "GQ" => 240, "GA" => 241, "CG" => 242, "CD" => 243, "AO" => 244, "GW" => 245, "IO" => 246, "AC" => 247, "SC" => 248, "SD" => 249, "RW" => 250, "ET" => 251, "SO" => 252, "DJ" => 253, "KE" => 254, "TZ" => 255, "UG" => 256, "BI" => 257, "MZ" => 258, "ZM" => 260, "MG" => 261, "RE" => 262, "ZW" => 263, "NA" => 264, "MW" => 265, "LS" => 266, "BW" => 267, "SZ" => 268, "KM" => 269, "YT" => 269, "ZA" => 27, "SH" => 290, "ER" => 291, "AW" => 297, "FO" => 298, "GL" => 299, "GR" => 30, "NL" => 31, "BE" => 32, "FR" => 33, "ES" => 34, "GI" => 350, "PT" => 351, "LU" => 352, "IE" => 353, "IS" => 354, "AL" => 355, "MT" => 356, "CY" => 357, "FI" => 358, "BG" => 359, "HU" => 36, "LT" => 370, "LV" => 371, "EE" => 372, "MD" => 373, "AM" => 374, "BY" => 375, "AD" => 376, "MC" => 377, "SM" => 378, "VA" => 379, "UA" => 380, "CS" => 381, "YU" => 381, "HR" => 385, "SI" => 386, "BA" => 387, "EU" => 388, "MK" => 389, "IT" => 39, "RO" => 40, "CH" => 41, "CZ" => 420, "SK" => 421, "LI" => 423, "AT" => 43, "GB" => 44, "DK" => 45, "SE" => 46, "NO" => 47, "PL" => 48, "DE" => 49, "FK" => 500, "BZ" => 501, "GT" => 502, "SV" => 503, "HN" => 504, "NI" => 505, "CR" => 506, "PA" => 507, "PM" => 508, "HT" => 509, "PE" => 51, "MX" => 52, "CU" => 53, "AR" => 54, "BR" => 55, "CL" => 56, "CO" => 57, "VE" => 58, "GP" => 590, "BO" => 591, "GY" => 592, "EC" => 593, "GF" => 594, "PY" => 595, "MQ" => 596, "SR" => 597, "UY" => 598, "AN" => 599, "MY" => 60, "AU" => 61, "CC" => 61, "CX" => 61, "ID" => 62, "PH" => 63, "NZ" => 64, "SG" => 65, "TH" => 66, "TL" => 670, "AQ" => 672, "NF" => 672, "BN" => 673, "NR" => 674, "PG" => 675, "TO" => 676, "SB" => 677, "VU" => 678, "FJ" => 679, "PW" => 680, "WF" => 681, "CK" => 682, "NU" => 683, "AS" => 684, "WS" => 685, "KI" => 686, "NC" => 687, "TV" => 688, "PF" => 689, "TK" => 690, "FM" => 691, "MH" => 692, "RU" => 7, "KZ" => 7, "XF" => 800, "XC" => 808, "JP" => 81, "KR" => 82, "VN" => 84, "KP" => 850, "HK" => 852, "MO" => 853, "KH" => 855, "LA" => 856, "CN" => 86, "XS" => 870, "XE" => 871, "XP" => 872, "XI" => 873, "XW" => 874, "XU" => 878, "BD" => 880, "XG" => 881, "XN" => 882, "TW" => 886, "TR" => 90, "IN" => 91, "PK" => 92, "AF" => 93, "LK" => 94, "MM" => 95, "MV" => 960, "LB" => 961, "JO" => 962, "SY" => 963, "IQ" => 964, "KW" => 965, "SA" => 966, "YE" => 967, "OM" => 968, "PS" => 970, "AE" => 971, "IL" => 972, "BH" => 973, "QA" => 974, "BT" => 975, "MN" => 976, "NP" => 977, "XR" => 979, "IR" => 98, "XT" => 991, "TJ" => 992, "TM" => 993, "AZ" => 994, "GE" => 995, "KG" => 996, "UZ" => 998];

    if (isset($mapc[$countryCode])) {
        return ($mapc[$countryCode]);
    } else {
        return (1);
    }
}

function ibs_mapCountryCode($countryCode)
{
    $mapc = ["US" => 1, "CA" => 1, "AI" => 1, "AG" => 1, "BB" => 1, "BS" => 1, "VG" => 1, "VI" => 1, "KY" => 1, "BM" => 1, "GD" => 1, "TC" => 1, "MS" => 1, "MP" => 1, "GU" => 1, "LC" => 1, "DM" => 1, "VC" => 1, "PR" => 1, "DO" => 1, "TT" => 1, "KN" => 1, "JM" => 1, "EG" => 20, "MA" => 212, "DZ" => 213, "TN" => 216, "LY" => 218, "GM" => 220, "SN" => 221, "MR" => 222, "ML" => 223, "GN" => 224, "CI" => 225, "BF" => 226, "NE" => 227, "TG" => 228, "BJ" => 229, "MU" => 230, "LR" => 231, "SL" => 232, "GH" => 233, "NG" => 234, "TD" => 235, "CF" => 236, "CM" => 237, "CV" => 238, "ST" => 239, "GQ" => 240, "GA" => 241, "CG" => 242, "CD" => 243, "AO" => 244, "GW" => 245, "IO" => 246, "AC" => 247, "SC" => 248, "SD" => 249, "RW" => 250, "ET" => 251, "SO" => 252, "DJ" => 253, "KE" => 254, "TZ" => 255, "UG" => 256, "BI" => 257, "MZ" => 258, "ZM" => 260, "MG" => 261, "RE" => 262, "ZW" => 263, "NA" => 264, "MW" => 265, "LS" => 266, "BW" => 267, "SZ" => 268, "KM" => 269, "YT" => 269, "ZA" => 27, "SH" => 290, "ER" => 291, "AW" => 297, "FO" => 298, "GL" => 299, "GR" => 30, "NL" => 31, "BE" => 32, "FR" => 33, "ES" => 34, "GI" => 350, "PT" => 351, "LU" => 352, "IE" => 353, "IS" => 354, "AL" => 355, "MT" => 356, "CY" => 357, "FI" => 358, "BG" => 359, "HU" => 36, "LT" => 370, "LV" => 371, "EE" => 372, "MD" => 373, "AM" => 374, "BY" => 375, "AD" => 376, "MC" => 377, "SM" => 378, "VA" => 379, "UA" => 380, "CS" => 381, "YU" => 381, "HR" => 385, "SI" => 386, "BA" => 387, "EU" => 388, "MK" => 389, "IT" => 39, "RO" => 40, "CH" => 41, "CZ" => 420, "SK" => 421, "LI" => 423, "AT" => 43, "GB" => 44, "DK" => 45, "SE" => 46, "NO" => 47, "PL" => 48, "DE" => 49, "FK" => 500, "BZ" => 501, "GT" => 502, "SV" => 503, "HN" => 504, "NI" => 505, "CR" => 506, "PA" => 507, "PM" => 508, "HT" => 509, "PE" => 51, "MX" => 52, "CU" => 53, "AR" => 54, "BR" => 55, "CL" => 56, "CO" => 57, "VE" => 58, "GP" => 590, "BO" => 591, "GY" => 592, "EC" => 593, "GF" => 594, "PY" => 595, "MQ" => 596, "SR" => 597, "UY" => 598, "AN" => 599, "MY" => 60, "AU" => 61, "CC" => 61, "CX" => 61, "ID" => 62, "PH" => 63, "NZ" => 64, "SG" => 65, "TH" => 66, "TL" => 670, "AQ" => 672, "NF" => 672, "BN" => 673, "NR" => 674, "PG" => 675, "TO" => 676, "SB" => 677, "VU" => 678, "FJ" => 679, "PW" => 680, "WF" => 681, "CK" => 682, "NU" => 683, "AS" => 684, "WS" => 685, "KI" => 686, "NC" => 687, "TV" => 688, "PF" => 689, "TK" => 690, "FM" => 691, "MH" => 692, "RU" => 7, "KZ" => 7, "XF" => 800, "XC" => 808, "JP" => 81, "KR" => 82, "VN" => 84, "KP" => 850, "HK" => 852, "MO" => 853, "KH" => 855, "LA" => 856, "CN" => 86, "XS" => 870, "XE" => 871, "XP" => 872, "XI" => 873, "XW" => 874, "XU" => 878, "BD" => 880, "XG" => 881, "XN" => 882, "TW" => 886, "TR" => 90, "IN" => 91, "PK" => 92, "AF" => 93, "LK" => 94, "MM" => 95, "MV" => 960, "LB" => 961, "JO" => 962, "SY" => 963, "IQ" => 964, "KW" => 965, "SA" => 966, "YE" => 967, "OM" => 968, "PS" => 970, "AE" => 971, "IL" => 972, "BH" => 973, "QA" => 974, "BT" => 975, "MN" => 976, "NP" => 977, "XR" => 979, "IR" => 98, "XT" => 991, "TJ" => 992, "TM" => 993, "AZ" => 994, "GE" => 995, "KG" => 996, "UZ" => 998];

    if (in_array($countryCode, $mapc)) {
        return true;
    } else {
        return false;
    }
}

function ibs_checkPhone($phoneNumber)
{
    $phoneNumber = str_replace(" ", "", $phoneNumber);
    $phoneNumber = str_replace("\t", "", $phoneNumber);
    return (bool)preg_match("/^\+[0-9]{1,4}\.[0-9 ]+$/is", $phoneNumber);
}

function ibs_reformatPhone($phoneNumber, $countryCode)
{
    $scontrol = trim($phoneNumber);

    /* If empty phone number, return as it is*/
    $l = strlen($scontrol);
    if (!$l) {
        return $phoneNumber;
    }

    $plus = ($scontrol[0] === "+");
    $countryPhoneCode = ibs_mapCountry($countryCode);

    /* check if first character is + */
    if ($plus) {
        $phoneExplode = explode(".", $scontrol);
        if (count($phoneExplode) > 1) {
            /* IF country code is added in phone numnber*/
            $countryPhoneCode = ltrim($phoneExplode[0], "+");
            $scontrol = $phoneExplode[1];
            $plus = false;
        }
    }

    /* Remove non-digit character from string */
    $scontrol = preg_replace("#\D*#si", "", $scontrol);

    /*if original phone number has + sign, add it again*/
    if ($plus) {
        $scontrol = "+" . $scontrol;
    }

    /* check if first 2 digit is 00, replace 00 with +*/
    if (strncmp($scontrol, "00", 2) === 0) {
        $scontrol = "+" . substr($scontrol, 2);
        /* If only 00 is entered, pass it to api and it will return invalid */
        if (strlen($scontrol) === 1) {
            return $phoneNumber;
        }
    }

    /* If first digit is +, find countrycode from that string, and prepend it in phone number */
    if ($scontrol[0] === "+") {
        for ($i = 2; $i < strlen($scontrol); $i++) {
            $first = substr($scontrol, 1, $i - 1);
            if ($first === $countryPhoneCode) {
                return "+" . $first . "." . substr($scontrol, $i);
            }
        }
        $scontrol = trim($scontrol, "+");
    }

    $rphone = "+" . $countryPhoneCode . "." . $scontrol;
    if (ibs_checkPhone($rphone)) {
        $countryCodeLength = strlen($countryPhoneCode);
        if (substr($phoneNumber, 0, $countryCodeLength) === $countryPhoneCode) {
            $myPhoneNumber = substr($phoneNumber, $countryCodeLength);
            $myPhoneNumber = preg_replace("/[^0-9,.]/", "", $myPhoneNumber);
            $rphone = "+" . $countryPhoneCode . "." . $myPhoneNumber;
        }
        return $rphone;
    }
    return $phoneNumber;
}

// see hooks.php:
// function ibs_getCountryCodeByName($countryName)

function ibs_getClientIp()
{
    foreach (["HTTP_X_FORWARDED_FOR", "REMOTE_ADDR"] as $key) {
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
    }
    return null;
}

function ibs_get2CharDotITProvinceCode($province)
{
    $provinceFiltered = trim($province);
    // Check if we need to search province code
    if (strlen($provinceFiltered) === 2) {
        // Looks we already have 2 char province code
        return strtoupper($provinceFiltered);
    }

    $provinceNamesInPossibleVariants = [
        "Agrigento" => "AG",
        "Alessandria" => "AL",
        "Ancona" => "AN",
        "Aosta, Aoste (fr)" => "AO",
        "Aosta, Aoste" => "AO",
        "Aosta" => "AO",
        "Aoste" => "AO",
        "Arezzo" => "AR",
        "Ascoli Piceno" => "AP",
        "Ascoli-Piceno" => "AP",
        "Asti" => "AT",
        "Avellino" => "AV",
        "Bari" => "BA",
        "Barletta-Andria-Trani" => "BT",
        "Barletta Andria Trani" => "BT",
        "Belluno" => "BL",
        "Benevento" => "BN",
        "Bergamo" => "BG",
        "Biella" => "BI",
        "Bologna" => "BO",
        "Bologna (bo)" => "BO",
        "Bolzano, Bozen (de)" => "BZ",
        "Bolzano, Bozen" => "BZ",
        "Bolzano" => "BZ",
        "Bozen" => "BZ",
        "Brescia" => "BS",
        "Brindisi" => "BR",
        "Cagliari" => "CA",
        "Caltanissetta" => "CL",
        "Campobasso" => "CB",
        "Carbonia-Iglesias" => "CI",
        "Carbonia Iglesias" => "CI",
        "Carbonia" => "CI",
        "Caserta" => "CE",
        "Catania" => "CT",
        "Catanzaro" => "CZ",
        "Chieti" => "CH",
        "Como" => "CO",
        "Cosenza" => "CS",
        "Cremona" => "CR",
        "Crotone" => "KR",
        "Cuneo" => "CN",
        "Enna" => "EN",
        "Fermo" => "FM",
        "Ferrara" => "FE",
        "Firenze" => "FI",
        "Foggia" => "FG",
        "Forli-Cesena" => "FC",
        "Forli Cesena" => "FC",
        "Forli" => "FC",
        "Frosinone" => "FR",
        "Genova" => "GE",
        "Gorizia" => "GO",
        "Grosseto" => "GR",
        "Imperia" => "IM",
        "Isernia" => "IS",
        "La Spezia" => "SP",
        "L'Aquila" => "AQ",
        "LAquila" => "AQ",
        "L-Aquila" => "AQ",
        "L Aquila" => "AQ",
        "Latina" => "LT",
        "Lecce" => "LE",
        "Lecco" => "LC",
        "Livorno" => "LI",
        "Lodi" => "LO",
        "Lucca" => "LU",
        "Macerata" => "MC",
        "Mantova" => "MN",
        "Massa-Carrara" => "MS",
        "Massa Carrara" => "MS",
        "Massa" => "MS",
        "Matera" => "MT",
        "Medio Campidano" => "VS",
        "Medio-Campidano" => "VS",
        "Medio" => "VS",
        "Messina" => "ME",
        "Milano" => "MI",
        "Modena" => "MO",
        "Monza e Brianza" => "MB",
        "Monza-e-Brianza" => "MB",
        "Monza-Brianza" => "MB",
        "Monza Brianza" => "MB",
        "Monza" => "MB",
        "Napoli" => "NA",
        "Novara" => "NO",
        "Nuoro" => "NU",
        "Ogliastra" => "OG",
        "Olbia-Tempio" => "OT",
        "Olbia Tempio" => "OT",
        "Olbia" => "OT",
        "Oristano" => "OR",
        "Padova" => "PD",
        "Palermo" => "PA",
        "Parma" => "PR",
        "Pavia" => "PV",
        "Perugia" => "PG",
        "Pesaro e Urbino" => "PU",
        "Pesaro-e-Urbino" => "PU",
        "Pesaro-Urbino" => "PU",
        "Pesaro Urbino" => "PU",
        "Pesaro" => "PU",
        "Pescara" => "PE",
        "Piacenza" => "PC",
        "Pisa" => "PI",
        "Pistoia" => "PT",
        "Pordenone" => "PN",
        "Potenza" => "PZ",
        "Prato" => "PO",
        "Ragusa" => "RG",
        "Ravenna" => "RA",
        "Reggio Calabria" => "RC",
        "Reggio-Calabria" => "RC",
        "Reggio" => "RC",
        "Reggio Emilia" => "RE",
        "Reggio-Emilia" => "RE",
        "Reggio" => "RE",
        "Rieti" => "RI",
        "Rimini" => "RN",
        "Roma" => "RM",
        "Rovigo" => "RO",
        "Salerno" => "SA",
        "Sassari" => "SS",
        "Savona" => "SV",
        "Siena" => "SI",
        "Siracusa" => "SR",
        "Sondrio" => "SO",
        "Taranto" => "TA",
        "Teramo" => "TE",
        "Terni" => "TR",
        "Torino" => "TO",
        "Trapani" => "TP",
        "Trento" => "TN",
        "Treviso" => "TV",
        "Trieste" => "TS",
        "Udine" => "UD",
        "Varese" => "VA",
        "Venezia" => "VE",
        "Verbano-Cusio-Ossola" => "VB",
        "Verbano Cusio Ossola" => "VB",
        "Verbano" => "VB",
        "Verbano-Cusio" => "VB",
        "Verbano-Ossola" => "VB",
        "Vercelli" => "VC",
        "Verona" => "VR",
        "Vibo Valentia" => "VV",
        "Vibo-Valentia" => "VV",
        "Vibo" => "VV",
        "Vicenza" => "VI",
        "Viterbo" => "VT",
    ];

    $provinceFiltered = strtolower(preg_replace("/[^a-z]/i", "", $provinceFiltered));
    foreach ($provinceNamesInPossibleVariants as $name => $code) {
        if (strtolower(preg_replace("/[^a-z]/i", "", $name)) == $provinceFiltered) {
            return $code;
        }
    }

    return $province;
}

function ibs_getItProvinceCode($inputElementValue)
{
    preg_match("/\[\s*([a-z]{2})\s*\]$/i", $inputElementValue, $m);
    return (isset($m[1])) ? $m[1] : "RM";
}

function ibs_GetDomainSuggestions($params)
{
    return new ResultsList();
}

function ibs_CheckAvailability($params)
{
    $tlds = $params["tldsToInclude"];
    $results = new ResultsList();

    foreach ($tlds as $tld) {
        $params["domainname"] = $params["searchTerm"] . $tld;
        $res = ibs_domainCheck($params);
        $sld = $params["searchTerm"]; //$params ["sld"];
        $sr = new SearchResult($sld, $tld);
        if ($res["status"] === "AVAILABLE") {
            $sr->setStatus($sr::STATUS_NOT_REGISTERED);
            if ($res["price_ispremium"] == "YES") {
                $sr->setPremiumDomain(true);
                $sr->setPremiumCostPricing([
                    "register" => $res["price_registration_1"],
                    "renew" => $res["price_renewal_1"],
                    "CurrencyCode" => $res["price_currency"],
                ]);
            }
        } elseif (isset($res["error"])) {
            $sr->setStatus($sr::STATUS_TLD_NOT_SUPPORTED);
        } else {
            $sr->setStatus($sr::STATUS_REGISTERED);
        }
        $results->append($sr);
    }
    return $results;
}

function ibs_domainCheck($params)
{
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/Check", [
        "domain" => $domainName,
        "currency" => "USD"
    ]);

    # If error, return the error message in the value below
    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }
    return $result;
}

/* Custom function for email verification*/
function ibs_verify($params)
{
    $domainid = $params["domainid"];
    $data = ibs_getEmailVerificationDetails($params);
    $email = $data["email"];
    $currentStatus = $data["currentstatus"];
    return [
        "templatefile" => "verify",
        "breadcrumb" => [
            "clientarea.php?action=domaindetails&domainid=" . $domainid . "&modop=custom&a=verify" => "Verify Email"
        ],
        "vars" => [
            "email" => $email,
            "status" => $currentStatus,
            "domainid" => $domainid
        ]
    ];
}

function ibs_getEmailVerificationDetails($params)
{
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/RegistrantVerification/Info", [
        "domain" => $domainName
    ]);

    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }
    return $result;
}

/* Custom function for email verification*/
function ibs_send($params)
{
    $domainid = $params["domainid"];

    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/RegistrantVerification/Send", [
        "domain" => $domainName
    ]);

    $defaultErrMsg = "Due to some technical reason email cannot be sent.";
    if ($result["status"] === "FAILURE") {
        $errormessage = (empty($result["message"])) ? $defaultErrMsg : $result["message"];
    } elseif ($result["status"] === "SUCCESS") {
        $values = $result;
        $operation = $values["operation"];
        $successmessage = "Verification email has been " . $operation . ". Please check your mail box within a couple of minutes. Make sure you also check the spam folder.";
    } else {
        $errormessage = $defaultErrMsg;
    }
    return [
        "templatefile" => "send",
        "breadcrumb" => [
            "clientarea.php?action=domaindetails&domainid=" . $domainid . "&modop=custom&a=send" => "Resend Email"
        ],
        "vars" => [
            "status" => $result["currentstatus"],
            "domainid" => $domainid,
            "errormessage" => $errormessage,
            "successmessage" => $successmessage
        ]
    ];
}

/*Custom Url Forwarding*/
function ibs_domainurlforwarding($params)
{
    $domainid = $params["domainid"];
    $error = "";

    $domainName = ibs_getInputDomain($params);
    $data = ibs_GetUrlForwarding($params);
    if (isset($_POST) && count($_POST) > 0) {
        for ($i = 0, $iMax = count($data); $i < $iMax; $i++) {
            $params["source"] = trim(trim($data[$i]["hostname"], " .") . "." . $domainName, " .");
            $result = ibs_RemoveUrlForwarding($params);
        }

        for ($i = 0, $iMax = count($_POST["dnsrecordaddress"]); $i < $iMax; $i++) {
            $params["hostName"] = $_POST["dnsrecordhost"][$i];
            $params["type"] = $_POST["dnsrecordtype"][$i];
            $params["address"] = $_POST["dnsrecordaddress"][$i];
            $result = ibs_SaveUrlForwarding($params);
            if ($result) {
                $error .= $result . "\n";
            }
        }
    }
    $data = ibs_GetUrlForwarding($params);
    return [
        "templatefile" => "domainurlforwarding",
        "breadcrumb" => [
            "clientarea.php?action=domaindetails&domainid=" . $domainid . "&modop=custom&a=domainurlforwarding" => "URL Forwarding"
        ],
        "vars" => [
            "status" => $result["currentstatus"],
            "domainName" => $domainName,
            "domainid" => $domainid,
            "data" => $data,
            "errormessage" => $error,
            "successmessage" => ""
        ]
    ];
}


function ibs_GetUrlForwarding($params)
{
    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/UrlForward/List", [
        "domain" => $domainName
    ]);

    if ($result["status"] === "FAILURE") {
        return [
            "error" => $result["message"]
        ];
    }

    $hostrecords = [];
    $totalRecords = (int)$result["total_rules"];
    for ($i = 1; $i <= $totalRecords; $i++) {
        $recordType = "";
        if (isset($result["rule_" . $i . "_isframed"])) {
            $recordType = trim($result["rule_" . $i . "_isframed"]) == "YES" ? "FRAME" : "URL";
        }
        if (isset($result["rule_" . $i . "_source"])) {
            $recordHostname = $result["rule_" . $i . "_source"];

            $dParts = explode(".", $domainName);
            $hParts = explode(".", $recordHostname);
            $recordHostname = "";
            for ($j = 0; $j < (count($hParts) - count($dParts)); $j++) {
                $recordHostname .= (empty($recordHostname) ? "" : ".") . $hParts[$j];
            }
        }
        if (isset($result["rule_" . $i . "_destination"])) {
            $recordAddress = $result["rule_" . $i . "_destination"];
        }
        if (isset($result["rule_" . $i . "_source"])) {
            $hostrecords[] = [
                "hostname" => $recordHostname,
                "type" => $recordType,
                "address" => htmlspecialchars($recordAddress)
            ];
        }
    }

    return $hostrecords;
}

function ibs_SaveUrlForwarding($params)
{
    $destination = trim($params["address"], " .");
    if (empty($destination)) {
        return false;
    }

    $result = ibs_call($params, "Domain/UrlForward/Add", [
        "source" => trim(trim($params["hostName"], ". ") . "." . $params["domainname"], "."),
        "isFramed" => ($params["type"] === "FRAME") ? "YES" : "NO",
        "Destination" => $destination
    ]);

    if ($result["status"] === "FAILURE") {
        return $result["message"];
    }
    return false;
}

function ibs_RemoveUrlForwarding($params)
{
    $result = ibs_call($params, "Domain/UrlForward/Remove", [
        "source" => $params["source"]
    ]);

    if ($result["status"] === "FAILURE") {
        return $result["message"];
    }
    return false;
}


function ibs_GetTldPricing(array $params)
{
    $results = localAPI("GetCurrencies", []);
    $defaultCurrency = $results["currencies"]["currency"][0]["code"];
    $currency = "USD";
    if (in_array($defaultCurrency, ["USD", "CAD", "AUD", "JPY", "EUR", "GBP"])) {
        $currency = $defaultCurrency;
    }

    $r = ibs_call($params, "Account/PriceList/Get", [
        "version" => "5",
        "currency" => $currency
    ]);
    $i = 0;
    $extensionData = [];
    while ($r["product_" . $i . "_tld"]) {
        list($tld, $product) = explode(" ", $r["product_" . $i . "_name"]);
        $tld = $r["product_" . $i . "_tld"];
        if (!$extensionData[$tld]) {
            $extensionData[$tld] = [];
        }
        $extensionData[$tld]["currencyCode"] = $r["product_" . $i . "_currency"];
        $extensionData[$tld]["registrationPrice"] = $r["product_" . $i . "_registration"];
        $extensionData[$tld]["renewalPrice"] = $r["product_" . $i . "_renewal"];
        $extensionData[$tld]["transferPrice"] = $r["product_" . $i . "_transfer"];
        $extensionData[$tld]["redemptionFee"] = $r["product_" . $i . "_restore"];
        $extensionData[$tld]["redemptionDays"] = ((int)trim($r["product_" . $i . "_rgp"])) / 24;
        $extensionData[$tld]["transferSecretRequired"] = (strtolower($r["product_" . $i . "_authinforequired"]) === "yes");
        $extensionData[$tld]["minPeriod"] = $r["product_" . $i . "_minperiod"];
        $extensionData[$tld]["maxPeriod"] = $r["product_" . $i . "_maxperiod"];
        $extensionData[$tld]["inc"] = $r["product_" . $i . "_inc"];
        $i++;
    }
    // Perform API call to retrieve extension information
    // A connection error should return a simple array with error key and message
    // return ["error" => "This error occurred",];

    $results = new ResultsList();
    #ibs_debugLog([
    #    "action" => "parsed data",
    #    "requestParam" => "",
    #    "responseParam" => $extensionData,
    #    "replace" => []
    #]);
    foreach ($extensionData as $tld => $extension) {
        // All the set methods can be chained and utilised together.
        $item = (new ImportItem())
            ->setExtension($tld)
            ->setMinYears($extension["minPeriod"])
            ->setMaxYears($extension["maxPeriod"])
            ->setRegisterPrice($extension["registrationPrice"])
            ->setRenewPrice($extension["renewalPrice"])
            ->setTransferPrice($extension["transferPrice"])
            ->setRedemptionFeeDays($extension["redemptionDays"])
            ->setRedemptionFeePrice($extension["redemptionFee"])
            ->setCurrency($extension["currencyCode"])
            ->setEppRequired($extension["transferSecretRequired"]);

        $results[] = $item;
    }
    return $results;
}

/*Get TMCH details*/
/*function ibs_TmchInfo($lookupkey)
{
    $registrar = new \WHMCS\Module\Registrar();
    if (
        !$registrar->load("ibs")
        || !$registrar->isActivated()
    ) {
        return [
            "error" => "Unable to load IBS Registrar Module Settings."
        ];
    }
    $params = $registrar->getSettings();

    $domainName = ibs_getInputDomain($params);

    $result = ibs_call($params, "Domain/Tmch/Info", [
        "lookupkey" => $lookupkey,
        "domain" => $domainName
    ]);

    # If error, return the error message in the value below
    if ($result["status"] == "FAILURE") {
        $values["error"] = $result["message"];
    } else {
        $values = $result;
    }
    return $values;
}*/

/**
 * DNSSEC Management
 *
 * @param array<string, mixed> $params common module parameters
 * @return array<string, mixed> an array with a template name
 */
function ibs_dnssec(array $params): array
{
    $domain = ibs_getInputDomain($params);

    $tpl = [
        "templatefile" => "tpl_ca_dnssec",
        "vars" => [
            "flagOptions" => [
                "" => "Please Select",
                256 => "Zone Signing Key",
                257 => "Secure Entry Point"
            ],
            "protocolOptions" => [
                "" => "Please Select",
                "3" => "DNSSEC"
            ],
            "algOptions" => [
                "" => "Please Select",
                8 => "RSA/SHA256",
                10 => "RSA/SHA512",
                12 => "GOST R 34.10-2001",
                13 => "ECDSA/SHA-256",
                14 => "ECDSA/SHA-384",
                15 => "Ed25519",
                16 => "Ed448"
            ],
            "digestOptions" => [
                "" => "Please Select",
                2 => "SHA-256",
                3 => "GOST R 34.11-94",
                4 => "SHA-384"
            ],
            "dnszone" => $domain . ".",
            "defaultttl" => "3600",
            "secdnsds" => [],
            "secdnskey" => [],
            "successful" => false,
            "error" => false,
            "disabled" => false
        ]
    ];

    // disable & cleanup dnssec
    if (isset($_POST["submit"]) && $_POST["submit"] === "0") {
        //process domain update
        $result = ibs_call($params, "Domain/Update", [
            "domain" => $domain,
            "dnssec" => ""
        ]);

        if ($result["status"] === "FAILURE") {
            // domain update failed!
            $tpl["vars"]["error"] = "<b>" . $result["message"] . "</b>" . (empty($dnssecrr) ? "" : "<pre style=\"text-align:left;\">" . implode("\n", $dnssecrr) . "</pre>");
        } elseif ($result["status"] === "SUCCESS") {
            // domain update ok!
            $tpl["vars"]["successful"] = "disabled";
        }
    }

    // save dnssec records
    if (isset($_POST["submit"]) && $_POST["submit"] === "1") {
        // DS record can be generated from DNSKEY, but not the other way around
        // most registries want just DS records
        // but registries like .cz, .de, be, eu want DNSKEY
        // all the others need DS
        // Q: Couldn't that then the DS Record auto-generated by API and submitted on-demand?
        // A: Yes, that could be done eventually. For now, we skip this and submit both
        //    DS and DNSKEY as this is said to work

        //add DS and KEY records
        $dnssecrr = [];
        foreach (["SECDNS-DS", "SECDNS-KEY"] as $keyname) {
            if (isset($_POST[$keyname])) {
                foreach ($_POST[$keyname] as $record) {
                    $everything_empty = true;
                    foreach ($record as $attribute) {
                        if (!empty($attribute)) {
                            $everything_empty = false;
                        }
                    }
                    if ($everything_empty) {
                        continue;
                    }

                    if ($keyname === "SECDNS-KEY") {
                        // <zone> <ttl> IN DNSKEY <flags> <protocol> <alg> <pubkey>
                        $dnssecrr[] = implode(" ", [
                            $domain . ".",
                            $record["ttl"],
                            "IN DNSKEY",
                            $record["flags"],
                            $record["protocol"],
                            $record["alg"],
                            preg_replace("/[\r\n\s]+/", "", $record["pubkey"])
                        ]);
                        continue;
                    }

                    // keyname === "SECDNS-DS"
                    // <zone> <ttk> IN DS <key tag> <alg> <digest-type> <digest>
                    $dnssecrr[] = implode(" ", [
                        $domain . ".",
                        $record["ttl"],
                        "IN DS",
                        $record["keytag"],
                        $record["alg"],
                        $record["digesttype"],
                        preg_replace("/[\r\n\s]+/", "", $record["digest"])
                    ]);
                }
            }
        }

        //process domain update
        $result = ibs_call($params, "Domain/Update", [
            "domain" => $domain,
            "dnssec" => empty($dnssecrr) ? "" : implode("\n", $dnssecrr)
        ]);

        if ($result["status"] === "FAILURE") {
            // domain update failed!
            $tpl["vars"]["error"] = "<b>" . $result["message"] . "</b>" . (empty($dnssecrr) ? "" : "<pre style=\"text-align:left;\">" . implode("\n", $dnssecrr) . "</pre>");
        } elseif ($result["status"] === "SUCCESS") {
            // domain update ok!
            $tpl["vars"]["successful"] = "updated";
        }
    }

    $result = ibs_call($params, "Domain/Info", [
        "domain" => $params["sld"] . "." . $params["tld"]
    ]);

    // domain info failed
    if ($result["status"] === "FAILURE") {
        if (!$tpl["vars"]["error"]) {
            $tpl["vars"]["error"] = $result["message"];
        }
        return $tpl;
    }

    $tpl["vars"]["disabled"] = ($result["dnssec"] === "disabled");
    if ($tpl["vars"]["disabled"]) {
        return $tpl;
    }

    // fetched domain info successfully
    // parase dnssec data and keys
    $keys = preg_grep("/^dnssec[0-9]+/", array_keys($result));
    if (empty($keys)) {
        return $tpl;
    }

    $dsData = [];
    $keyData = [];
    foreach ($keys as $key) {
        $record = $result[$key];
        $split = explode(" ", $record);
        if ($split === false) {
            continue;
        }
        // DS Records
        if (preg_match("/ IN DS /", $record)) {
            $dsData[] = [
                // zone at index 0
                "ttl" => $split[1],
                // IN at index 2
                // DS at index 3
                "keytag" => $split[4],
                "alg" => $split[5],
                "digesttype" => $split[6],
                "digest" => $split[7]
            ];
            continue;
        }
        // DNSKEY Records
        $keyData[] = [
            // zone at index 0
            "ttl" => $split[1],
            // IN at index 2
            // DS at index 3
            "flags" => $split[4],
            "protocol" => $split[5],
            "alg" => $split[6],
            "pubkey" => $split[7]
        ];
    }

    $tpl["vars"]["secdnsds"] = $dsData;
    $tpl["vars"]["secdnskey"] = $keyData;

    return $tpl;
}

function ibs_getEuContries($codesOnly = false)
{
    // https://ec.europa.eu/eurostat/statistics-explained/index.php?title=Glossary:Country_codes
    // https://www.destatis.de/Europa/EN/Country/Country-Codes.html
    $map = [
        "AT" => "Austria",
        "BE" => "Belgium",
        "BG" => "Bulgaria",
        "CY" => "Cyprus",
        "CZ" => "Czechia",
        "DE" => "Germany",
        "DK" => "Denmark",
        "EE" => "Estonia",
        "GR" => "Greece",
        "ES" => "Spain",
        "FI" => "Finland",
        "FR" => "France",
        "HU" => "Hungary",
        "HR" => "Croatia",
        "IE" => "Ireland",
        "IT" => "Italia",
        "LT" => "Lithuania",
        "LU" => "Luxembourg",
        "LV" => "Latvia",
        "MT" => "Malta",
        "NL" => "Netherlands",
        "PL" => "Poland",
        "PT" => "Portugal",
        "SE" => "Sweden",
        "SI" => "Slovenia",
        "SK" => "Slovakia",
        "RO" => "Romania"
    ];
    return ($codesOnly ? array_keys($map) : $map);
}
