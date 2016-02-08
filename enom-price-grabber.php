<?php
/**
 * ENOM Domain Reseller Price Grabber
 *
 * This script will grab domain prices from your ENOM reseller account and
 * insert them into your WHMCS database eliminating the need to continually
 * update prices on both platforms. Simply change pricing on ENOM and this
 * script will pull them into your WHMCS installation.
 *
 * How to use:
 * 1. Activate ENOM Reseller in WHMCS
 * 2. Define your WHMCS database connection settings
 * 3. Define your ENOM user/API settings
 * 4. Add some domain TLDs in **WHMCS** -> **Setup** -> **Products/Services** -> **Domain Pricing**
 * 5. Make sure 'Auto Registration' is set to ENOM
 * 6. Access http://your-whmcs-site.com/enom-price-grabber.php in your browser to run the script
 * Bonus: Setup this script as a daily/weekly cron and forget about it :)
 *
 * ** IMPORTANT **
 * This script **DOES NOT** grab prices for ALL domains in ENOM.
 * It only grabs prices for domains you have added into 'Domain Pricing'.
 * If you want all domains you will need to add them ALL manually. This is so
 * it only grabs prices for domains that you want to sell.
 *
 * If you do want work with ALL domains but don't want to add them to WHMCS
 * you should be able to easily modify the script.
 *
 * ** NEED HELP?
 * Email: support@xtmhost.com
 * Web: https://www.xtmhost.com
 * Twitter: @xtmhost
 */

// Database config settings
define('DB_NAME', 'database_name');
define('DB_USER', 'database_username');
define('DB_PASSWORD', 'database_password');
define('DB_HOST', 'localhost');

// Enom API settings
define('ENOM_USER', 'enom_username');
define('ENOM_PASS', 'enom_password');

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

echo "\n-- Grabbing list of TLDs from ENOM";

// Grab domain pricing data from ENOM
$apiUrl = 'http://reseller.enom.com/interface.asp?command=PE_GetRetailPricing&TLDOnly=1&years=1&uid=' . ENOM_USER . '&pw=' . ENOM_PASS . '&responsetype=xml';

$xmlFeed = simplexml_load_file($apiUrl);
$enomData = $xmlFeed->pricestructure->tld;

$enomDataCount = count($xmlFeed->pricestructure->tld);
if ($enomDataCount > 0) {
	// We now have our data ready to insert, it's safe to clear the existing data.
	$stmtDelete = $db->prepare("DELETE FROM `tblpricing` WHERE `type` IN ('domainregister', 'domainrenew', 'domaintransfer')");
	$stmtDelete->execute();

	echo "\n-- Deleted existing data from pricing table";

	$stmtInsertRegisterPrice = $db->prepare("INSERT INTO `tblpricing` (`type`, `currency`, `relid`, `msetupfee`, `qsetupfee`, `ssetupfee`, `asetupfee`, `bsetupfee`, `tsetupfee`, `monthly`, `quarterly`, `semiannually`, `annually`, `biennially`, `triennially`) VALUES (:type, :currency, :relid, :msetupfee, :qsetupfee, :ssetupfee, :asetupfee, :bsetupfee, :tsetupfee, :monthly, :quarterly, :semiannually, :annually, :biennially, :triennially)");

	$stmtInsertRenewPrice = $db->prepare("INSERT INTO `tblpricing` (`type` ,`currency` ,`relid` ,`msetupfee` ,`qsetupfee` ,`ssetupfee` ,`asetupfee` ,`bsetupfee` ,`tsetupfee` ,`monthly` ,`quarterly` ,`semiannually` ,`annually` ,`biennially` ,`triennially`) VALUES (:type, :currency, :relid, :msetupfee, :qsetupfee, :ssetupfee, :asetupfee, :bsetupfee, :tsetupfee, :monthly, :quarterly, :semiannually, :annually, :biennially, :triennially)");

	$stmtInsertTransferPrice = $db->prepare("INSERT INTO `tblpricing` (`type` ,`currency` ,`relid` ,`msetupfee` ,`qsetupfee` ,`ssetupfee` ,`asetupfee` ,`bsetupfee` ,`tsetupfee` ,`monthly` ,`quarterly` ,`semiannually` ,`annually` ,`biennially` ,`triennially`) VALUES (:type, :currency, :relid, :msetupfee, :qsetupfee, :ssetupfee, :asetupfee, :bsetupfee, :tsetupfee, :monthly, :quarterly, :semiannually, :annually, :biennially, :triennially)");

	echo "\n-- Queries have been prepared, lets begin the insert...";

	$count = 0;

	for ($i = 0; $i < $enomDataCount; $i++) {
		$tldFromEnom = (string)$enomData[$i]->tld;

		if (in_array_r($tldFromEnom, $tldsList)) {
			$registerPrice = $enomData[$i]->registerprice;
			$renewPrice    = $enomData[$i]->renewprice;
			$transferPrice = $enomData[$i]->transferprice;

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
