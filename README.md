# Enom Domain Reseller Price Grabber
This script will grab domain prices from your ENOM reseller account and insert them into your WHMCS database eliminating the need to continuously update prices on both platforms. Simply change pricing on ENOM and this script will pull them into your WHMCS installation.

## Disclaimer
This script is still in beta. Do not use this on a production database until you fully understand what it does and how it works. All domain prices are deleted before they are re-inserted, there is a possibility for data loss if used incorrectly.

## How to use
* Activate ENOM Reseller in WHMCS
* Define your WHMCS database connection settings
* Define your ENOM user/API settings
* Add some domain TLDs in **WHMCS** -> **Setup** -> **Products/Services** -> **Domain Pricing**
* Make sure 'Auto Registration' is set to ENOM
* access http://your-whmcs-site.com/enom-price-grabber.php in your browser to run the script

**Bonus:** Setup this script as a daily/weekly cron and forget about it :)

## Currency Conversion
If you would like to save prices in a different currency to USD, set **USE_EXCHANGE_RATE** to 1 and set **EXCHANGE_TO** to the currency you want to convert to e.g. GBP, EUR.

You can find a full list of supported currencies [here](http://api.fixer.io/latest?base=USD).

Since ENOM only serves pricing in USD you should never change **EXCHANGE_FROM**

## Important

This script **DOES NOT** grab prices for ALL domains in ENOM.

It only grabs prices for domains you have added into 'Domain Pricing'. If you want all domains you will need to add them ALL manually. This is so it only grabs prices for domains that you want to sell.

If you do want work with ALL domains but don't want to add them to WHMCS you should be able to easily modify the script.

## Need help?

Create an issue or contact me directly:

* Email: support@xtmhost.com
* Web: https://www.xtmhost.com
* Twitter: @xtmhost

## What next?

* Find a way to pull in multiple year pricing.
* If this script becomes popular I may make it into an official WHMCS addon.
