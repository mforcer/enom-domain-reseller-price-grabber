<?php
/**
 * ENOM Domain Reseller Price Grabber
 * Version: 0.2 (Beta)
 *
 * This script will grab domain prices from your ENOM reseller account and
 * insert them into your WHMCS database eliminating the need to continuously
 * update prices on ENOM and WHMCS.
 *
 * How to use:
 * 	1. Define your WHMCS database connection settings
 *  2. Define your ENOM user/API settings (make sure ENOM integration is activated)
 *  3. Add some domains in WHMCS -> Setup -> Products/Services -> Domain Pricing
 *  4. This script works best setup as a daily/weekly cron but can be run manually
 *
 * ** Currency Conversion **
 * If you would like to save prices in a different currency to USD, set
 * USE_EXCHANGE_RATE to 1 and set EXCHANGE_TO to the currency you want to
 * convert to e.g. GBP, EUR.
 *
 * ** IMPORTANT **
 * This script DOES NOT download the pricing for EVERY domain, it only grabs
 * pricing for domains you have added into 'Domain Pricing', if you want all domains
 * you will need to add ALL manually. This is so it only grabs prices for domains
 * that you want to use.
 *
 * ** NEED HELP?
 * Email: support@xtmhost.com
 * Web: https://www.xtmhost.com
 * Twitter: @xtmhost
 */

// Database connection settings
define('DB_NAME', 'database_name');
define('DB_USER', 'database_username');
define('DB_PASSWORD', 'database_password');
define('DB_HOST', 'localhost');

// Enom API settings
define('ENOM_USER', 'enom_username');
define('ENOM_PASS', 'enom_password');

// Exchange Rate settings
define('USE_EXCHANGE_RATE', 0);
define('EXCHANGE_FROM', 'USD');
define('EXCHANGE_TO', 'GBP');

// Ensure the output is text/plain
header('Content-Type: text/plain');

// in_array for multidimensional arrays
function in_array_r($needle, $haystack) {
    foreach ($haystack as $item) {
        if ($item == $needle || (is_array($item) && in_array_r($needle, $item))) {
            return true;
        }
    }

    return false;
}

// Initialise database connection
$db = null;
try {
	$opt = array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	);
	$db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASSWORD, $opt);
} catch (Exception $e) {
	die("Database connection failed\n" . $e->getTraceAsString() . "\n");
}

echo "\n-- Connected to Database";
echo "\n-- Grabbing list of TLDs from WHMCS";

// Execute the select query to grab the current list of TLDs that we want to use
$stmtSelect = $db->prepare("SELECT `id`, `extension` FROM `tbldomainpricing` WHERE `autoreg` = 'enom'");
$stmtSelect->execute();
$whmcsTLDs = $stmtSelect->fetchAll();

// If some domain TLDs exist in WHMCS we can build an array otherwise exit
$whmcsTLDsCount = count($whmcsTLDs);
if ($whmcsTLDsCount == 0)
	die("\n-- Couldn't find any TLDs in WHMCS. Add some by going to Setup -> Products/Services -> Domain Pricing");

for ($i = 0; $i < $whmcsTLDsCount; $i++) {
	$ext = ltrim($whmcsTLDs[$i]['extension'], '.');
	$tldsList[$ext] = ['extension' => $ext, 'id' => $whmcsTLDs[$i]['id']];
}

if (USE_EXCHANGE_RATE == 1) {
    echo "\n-- Getting the latest exchange rate";

    $jsonFeed = file_get_contents('http://api.fixer.io/latest?base=' . EXCHANGE_FROM . '&symbols=' . EXCHANGE_TO);
    $exchangeData = json_decode($jsonFeed);
    $currency = constant('EXCHANGE_TO');
    $exchangeRate = $exchangeData->rates->{$currency};

    echo "\n-- Current exchange rate from " . EXCHANGE_FROM . ' to ' . EXCHANGE_TO . ' is ' . $exchangeRate;
}

echo "\n-- Grabbing list of TLDs from ENOM";

// Grab domain pricing data from ENOM
$apiUrl = 'http://reseller.enom.com/interface.asp?command=PE_GetRetailPricing&TLDOnly=1&years=1&uid=' . ENOM_USER . '&pw=' . ENOM_PASS . '&responsetype=xml';

$xmlFeed = simplexml_load_file($apiUrl);
$enomData = $xmlFeed->pricestructure->tld;

$enomDataCount = count($xmlFeed->pricestructure->tld);
if ($enomDataCount > 0) {
	// We now have our data ready to insert, it's safe to clear the existing data
	$stmtDelete = $db->prepare("DELETE FROM `tblpricing` WHERE `type` IN ('domainregister', 'domainrenew', 'domaintransfer')");
	$stmtDelete->execute();

	echo "\n-- Deleted existing data from pricing table";

    // Prepare our insert statements
	$stmtInsertRegisterPrice = $db->prepare("INSERT INTO `tblpricing` (`type`, `currency`, `relid`, `msetupfee`, `qsetupfee`, `ssetupfee`, `asetupfee`, `bsetupfee`, `tsetupfee`, `monthly`, `quarterly`, `semiannually`, `annually`, `biennially`, `triennially`) VALUES (:type, :currency, :relid, :msetupfee, :qsetupfee, :ssetupfee, :asetupfee, :bsetupfee, :tsetupfee, :monthly, :quarterly, :semiannually, :annually, :biennially, :triennially)");

	$stmtInsertRenewPrice = $db->prepare("INSERT INTO `tblpricing` (`type` ,`currency` ,`relid` ,`msetupfee` ,`qsetupfee` ,`ssetupfee` ,`asetupfee` ,`bsetupfee` ,`tsetupfee` ,`monthly` ,`quarterly` ,`semiannually` ,`annually` ,`biennially` ,`triennially`) VALUES (:type, :currency, :relid, :msetupfee, :qsetupfee, :ssetupfee, :asetupfee, :bsetupfee, :tsetupfee, :monthly, :quarterly, :semiannually, :annually, :biennially, :triennially)");

	$stmtInsertTransferPrice = $db->prepare("INSERT INTO `tblpricing` (`type` ,`currency` ,`relid` ,`msetupfee` ,`qsetupfee` ,`ssetupfee` ,`asetupfee` ,`bsetupfee` ,`tsetupfee` ,`monthly` ,`quarterly` ,`semiannually` ,`annually` ,`biennially` ,`triennially`) VALUES (:type, :currency, :relid, :msetupfee, :qsetupfee, :ssetupfee, :asetupfee, :bsetupfee, :tsetupfee, :monthly, :quarterly, :semiannually, :annually, :biennially, :triennially)");

	echo "\n-- Queries have been prepared, lets begin the insert...";

	$count = 0;
    // Loop through each domain in WHMCS and grab the pricing from ENOM
	for ($i = 0; $i < $enomDataCount; $i++) {
		$tldFromEnom = (string)$enomData[$i]->tld;

		if (in_array_r($tldFromEnom, $tldsList)) {
            if (USE_EXCHANGE_RATE == 1) {
                $registerPrice  = bcmul($enomData[$i]->registerprice, $exchangeRate, 2);
                $renewPrice     = bcmul($enomData[$i]->renewprice, $exchangeRate, 2);
                $transferPrice  = bcmul($enomData[$i]->transferprice, $exchangeRate, 2);
            } else {
                $registerPrice  = $enomData[$i]->registerprice;
                $renewPrice     = $enomData[$i]->renewprice;
                $transferPrice  = $enomData[$i]->transferprice;
            }

			echo "\n-- Found domain '{$tldFromEnom}' and grabbed prices";

			$relid = $tldsList[$tldFromEnom]['id'];

			echo "\n-- Found WHMCS id: " . $relid;

			$stmtInsertRegisterPrice->execute([
				'type'		=> 'domainregister',
				'currency'	=> 1,
				'relid'		=> $relid,
				'msetupfee'	=> $registerPrice,
				'qsetupfee' => 0.00,
				'ssetupfee' => 0.00,
				'asetupfee' => 0.00,
				'bsetupfee' => 0.00,
				'tsetupfee' => 0.00,
				'monthly'	=> 0.00,
				'quarterly'	=> 0.00,
				'semiannually' => 0.00,
				'annually' => 0.00,
				'biennially' => 0.00,
				'triennially' => 0.00,
			]);

			echo "\n-- Inserted register price for: " . $relid;

			$stmtInsertRenewPrice->execute([
				'type'		=> 'domainrenew',
				'currency'	=> 1,
				'relid'		=> $relid,
				'msetupfee'	=> $renewPrice,
				'qsetupfee' => 0.00,
				'ssetupfee' => 0.00,
				'asetupfee' => 0.00,
				'bsetupfee' => 0.00,
				'tsetupfee' => 0.00,
				'monthly'	=> 0.00,
				'quarterly'	=> 0.00,
				'semiannually' => 0.00,
				'annually' => 0.00,
				'biennially' => 0.00,
				'triennially' => 0.00,
			]);

			echo "\n-- Inserted renew price for: " . $relid;

			$stmtInsertTransferPrice->execute([
				'type'		=> 'domaintransfer',
				'currency'	=> 1,
				'relid'		=> $relid,
				'msetupfee'	=> $transferPrice,
				'qsetupfee' => 0.00,
				'ssetupfee' => 0.00,
				'asetupfee' => 0.00,
				'bsetupfee' => 0.00,
				'tsetupfee' => 0.00,
				'monthly'	=> 0.00,
				'quarterly'	=> 0.00,
				'semiannually' => 0.00,
				'annually' => 0.00,
				'biennially' => 0.00,
				'triennially' => 0.00,
			]);

			echo "\n-- Inserted transfer price for: " . $relid;
			$count++;
		}
	}

	echo "\n-- {$count} domain(s) have been inserted into `tblpricing`";
	echo "\n-- Finished :)";
}
